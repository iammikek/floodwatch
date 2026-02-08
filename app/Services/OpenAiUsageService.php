<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches LLM usage and cost data from the OpenAI Usage API.
 * Requires an Admin API key (sk-admin-...) with Usage API access.
 *
 * @see https://platform.openai.com/docs/api-reference/usage
 */
class OpenAiUsageService
{
    private const string USAGE_BASE_URL = 'https://api.openai.com/v1/organization/usage';

    private const int TIMEOUT = 15;

    /**
     * @return array{requests_today: int, requests_this_month: int, input_tokens_this_month: int, output_tokens_this_month: int, cost_this_month: float|null, remaining_budget: float|null, chart_daily: array<int, array{date: string, requests: int, input_tokens: int, output_tokens: int, cost: float}>, error: string|null}
     */
    public function getUsage(): array
    {
        $apiKey = config('openai.org_api_key');

        if (empty($apiKey)) {
            return [
                'requests_today' => 0,
                'requests_this_month' => 0,
                'input_tokens_this_month' => 0,
                'output_tokens_this_month' => 0,
                'cost_this_month' => null,
                'remaining_budget' => null,
                'chart_daily' => [],
                'error' => 'Admin API key (OPENAI_ORG_ADMIN_KEY) not configured. Usage API requires an org admin key.',
            ];
        }

        $cacheKey = 'openai_usage:'.now()->format('Y-m-d');
        $cacheMinutes = 5;

        return Cache::remember($cacheKey, $cacheMinutes * 60, fn () => $this->fetchUsage($apiKey));
    }

    /**
     * @return array{requests_today: int, requests_this_month: int, input_tokens_this_month: int, output_tokens_this_month: int, cost_this_month: float|null, remaining_budget: float|null, chart_daily: array, error: string|null}
     */
    private function fetchUsage(string $apiKey): array
    {
        try {
            $today = $this->fetchCompletionsUsage(
                $apiKey,
                now()->startOfDay()->timestamp,
                now()->timestamp,
                '1h',
                24
            );

            $thisMonth = $this->fetchCompletionsUsage(
                $apiKey,
                now()->startOfMonth()->timestamp,
                now()->timestamp,
                '1d',
                31
            );

            $requestsToday = $this->sumRequests($today);
            $requestsThisMonth = $this->sumRequests($thisMonth);
            [$inputTokens, $outputTokens] = $this->sumTokens($thisMonth);
            $costThisMonth = $this->estimateCost($thisMonth);

            $budgetInitial = config('flood-watch.llm_budget_initial', 0);
            $remainingBudget = $budgetInitial > 0 && $costThisMonth !== null
                ? max(0.0, round($budgetInitial - $costThisMonth, 2))
                : null;

            $chartDaily = $this->buildChartDaily($thisMonth);

            return [
                'requests_today' => $requestsToday,
                'requests_this_month' => $requestsThisMonth,
                'input_tokens_this_month' => $inputTokens,
                'output_tokens_this_month' => $outputTokens,
                'cost_this_month' => $costThisMonth,
                'remaining_budget' => $remainingBudget,
                'chart_daily' => $chartDaily,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $status = $e instanceof RequestException ? $e->response?->status() : null;
            $isExpectedClientError = in_array($status, [401, 403, 429], true);

            if (! $isExpectedClientError) {
                report($e);
            }

            return [
                'requests_today' => 0,
                'requests_this_month' => 0,
                'input_tokens_this_month' => 0,
                'output_tokens_this_month' => 0,
                'cost_this_month' => null,
                'remaining_budget' => null,
                'chart_daily' => [],
                'error' => $status === 403
                    ? 'Usage API requires an Admin API key (sk-admin-...). Organization Owners: create one at platform.openai.com/settings/organization/admin-keys and set OPENAI_ORG_ADMIN_KEY or OPENAI_ORG_API_KEY.'
                    : $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, array{object: string, start_time: int, end_time: int, results: array}>
     */
    private function fetchCompletionsUsage(string $apiKey, int $startTime, int $endTime, string $bucketWidth, int $limit): array
    {
        $url = self::USAGE_BASE_URL.'/completions';
        $allData = [];
        $page = null;

        do {
            $query = http_build_query(array_filter([
                'start_time' => $startTime,
                'end_time' => $endTime,
                'bucket_width' => $bucketWidth,
                'limit' => $limit,
                'page' => $page,
            ]));

            $request = Http::withToken($apiKey)->timeout(self::TIMEOUT);

            $org = config('openai.organization');
            if (! empty($org) && ! str_starts_with($apiKey, 'sk-admin-')) {
                $request = $request->withHeaders(['OpenAI-Organization' => $org]);
            }

            $response = $request->get("{$url}?{$query}");

            $response->throw();

            $json = $response->json();
            $data = $json['data'] ?? [];
            $allData = array_merge($allData, $data);
            $page = $json['next_page'] ?? null;
        } while ($page !== null);

        return $allData;
    }

    /**
     * Build daily chart data from buckets.
     *
     * @param  array<int, array{start_time: int, end_time: int, results?: array}>  $buckets
     * @return array<int, array{date: string, requests: int, input_tokens: int, output_tokens: int, cost: float}>
     */
    private function buildChartDaily(array $buckets): array
    {
        $inputCostPerM = config('flood-watch.llm_cost_input_per_m', 0.15);
        $outputCostPerM = config('flood-watch.llm_cost_output_per_m', 0.60);
        $chart = [];

        foreach ($buckets as $bucket) {
            $requests = 0;
            $inputTokens = 0;
            $outputTokens = 0;

            foreach ($bucket['results'] ?? [] as $result) {
                $requests += (int) ($result['num_model_requests'] ?? 0);
                $inputTokens += (int) ($result['input_tokens'] ?? 0);
                $outputTokens += (int) ($result['output_tokens'] ?? 0);
            }

            $cost = ($inputTokens / 1_000_000 * $inputCostPerM) + ($outputTokens / 1_000_000 * $outputCostPerM);

            $chart[] = [
                'date' => Carbon::createFromTimestamp((int) ($bucket['start_time'] ?? 0))
                    ->timezone(config('app.timezone'))
                    ->format('M j'),
                'requests' => $requests,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost' => round($cost, 2),
            ];
        }

        return $chart;
    }

    /**
     * @param  array<int, array{results?: array}>  $buckets
     */
    private function sumRequests(array $buckets): int
    {
        $total = 0;

        foreach ($buckets as $bucket) {
            foreach ($bucket['results'] ?? [] as $result) {
                $total += (int) ($result['num_model_requests'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * @param  array<int, array{results?: array}>  $buckets
     * @return array{0: int, 1: int} [input_tokens, output_tokens]
     */
    private function sumTokens(array $buckets): array
    {
        $input = 0;
        $output = 0;

        foreach ($buckets as $bucket) {
            foreach ($bucket['results'] ?? [] as $result) {
                $input += (int) ($result['input_tokens'] ?? 0);
                $output += (int) ($result['output_tokens'] ?? 0);
            }
        }

        return [$input, $output];
    }

    /**
     * Estimate cost from token usage. Uses gpt-4o-mini pricing as default.
     *
     * @param  array<int, array{results?: array}>  $buckets
     */
    private function estimateCost(array $buckets): ?float
    {
        [$inputTokens, $outputTokens] = $this->sumTokens($buckets);

        if ($inputTokens === 0 && $outputTokens === 0) {
            return 0.0;
        }

        $inputCostPerM = config('flood-watch.llm_cost_input_per_m', 0.15);
        $outputCostPerM = config('flood-watch.llm_cost_output_per_m', 0.60);

        return round(
            ($inputTokens / 1_000_000 * $inputCostPerM) + ($outputTokens / 1_000_000 * $outputCostPerM),
            2
        );
    }
}
