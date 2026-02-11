<?php

namespace Tests\Feature\Services;

use App\Services\OpenAiUsageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiUsageServiceTest extends TestCase
{
    private function usageResponse(int $requests = 3, int $inputTokens = 1000, int $outputTokens = 200): array
    {
        return [
            'object' => 'page',
            'data' => [
                [
                    'object' => 'bucket',
                    'start_time' => now()->startOfMonth()->timestamp,
                    'end_time' => now()->timestamp,
                    'results' => [
                        [
                            'object' => 'organization.usage.completions.result',
                            'num_model_requests' => $requests,
                            'input_tokens' => $inputTokens,
                            'output_tokens' => $outputTokens,
                        ],
                    ],
                ],
            ],
            'has_more' => false,
            'next_page' => null,
        ];
    }

    public function test_returns_error_when_no_api_key(): void
    {
        Config::set('openai.org_api_key', null);

        $service = app(OpenAiUsageService::class);
        $result = $service->getUsage();

        $this->assertStringContainsString('Admin API key', $result['getError']);
        $this->assertStringContainsString('OPENAI_ORG_ADMIN_KEY', $result['getError']);
        $this->assertSame(0, $result['requests_today']);
        $this->assertSame(0, $result['requests_this_month']);
        $this->assertSame([], $result['chart_daily']);
    }

    public function test_returns_usage_data_when_api_succeeds(): void
    {
        Config::set('openai.org_api_key', 'sk-org-admin-key');

        $response = $this->usageResponse(5, 2000, 400);
        Http::fake(function ($request) use ($response) {
            if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
                return Http::response($response, 200);
            }

            return Http::response([], 404);
        });

        $service = app(OpenAiUsageService::class);
        $result = $service->getUsage();

        $this->assertNull($result['getError']);
        $this->assertSame(5, $result['requests_today']);
        $this->assertSame(5, $result['requests_this_month']);
        $this->assertSame(2000, $result['input_tokens_this_month']);
        $this->assertSame(400, $result['output_tokens_this_month']);
        $this->assertIsFloat($result['cost_this_month']);
    }

    public function test_returns_403_error_message_when_admin_key_required(): void
    {
        Config::set('openai.org_api_key', 'sk-org-key');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
                return Http::response(['getError' => 'Forbidden'], 403);
            }

            return Http::response([], 404);
        });

        $service = app(OpenAiUsageService::class);
        $result = $service->getUsage();

        $this->assertNotNull($result['getError']);
        $this->assertStringContainsString('Admin API key', $result['getError']);
        $this->assertSame(0, $result['requests_today']);
    }

    public function test_returns_generic_error_message_on_other_api_failures(): void
    {
        Config::set('openai.org_api_key', 'sk-org-admin-key');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
                return Http::response(['getError' => 'Server getError'], 500);
            }

            return Http::response([], 404);
        });

        $service = app(OpenAiUsageService::class);
        $result = $service->getUsage();

        $this->assertNotNull($result['getError']);
        $this->assertSame(0, $result['requests_today']);
    }

    public function test_caches_result_for_five_minutes(): void
    {
        Config::set('openai.org_api_key', 'sk-org-admin-key');

        $response = $this->usageResponse(1, 100, 50);
        $callCount = 0;
        Http::fake(function ($request) use ($response, &$callCount) {
            if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
                $callCount++;

                return Http::response($response, 200);
            }

            return Http::response([], 404);
        });

        $service = app(OpenAiUsageService::class);

        $service->getUsage();
        $service->getUsage();
        $service->getUsage();

        $this->assertSame(2, $callCount, 'Should fetch twice (today + this month) then serve from cache');
    }

    public function test_remaining_budget_when_llm_budget_initial_set(): void
    {
        Config::set('openai.org_api_key', 'sk-org-admin-key');
        Config::set('flood-watch.llm_budget_initial', 10);

        $response = $this->usageResponse(1, 1_000_000, 500_000);
        Http::fake(function ($request) use ($response) {
            if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
                return Http::response($response, 200);
            }

            return Http::response([], 404);
        });

        $service = app(OpenAiUsageService::class);
        $result = $service->getUsage();

        $this->assertNull($result['getError']);
        $this->assertNotNull($result['remaining_budget']);
        $this->assertLessThanOrEqual(10.0, $result['remaining_budget']);
        $this->assertGreaterThanOrEqual(0.0, $result['remaining_budget']);
    }

    public function test_chart_daily_includes_date_requests_and_cost(): void
    {
        Config::set('openai.org_api_key', 'sk-org-admin-key');

        $response = $this->usageResponse(2, 500, 100);
        Http::fake(function ($request) use ($response) {
            if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
                return Http::response($response, 200);
            }

            return Http::response([], 404);
        });

        $service = app(OpenAiUsageService::class);
        $result = $service->getUsage();

        $this->assertNotEmpty($result['chart_daily']);
        $day = $result['chart_daily'][0];
        $this->assertArrayHasKey('date', $day);
        $this->assertArrayHasKey('requests', $day);
        $this->assertArrayHasKey('input_tokens', $day);
        $this->assertArrayHasKey('output_tokens', $day);
        $this->assertArrayHasKey('cost', $day);
        $this->assertSame(2, $day['requests']);
    }

    public function test_uses_org_api_key_when_configured(): void
    {
        Config::set('openai.org_api_key', 'sk-org-admin');

        $response = $this->usageResponse();
        $requestedToken = null;
        Http::fake(function ($request) use ($response, &$requestedToken) {
            if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
                $auth = $request->header('Authorization');
                $requestedToken = is_array($auth) ? ($auth[0] ?? null) : $auth;
                if ($requestedToken) {
                    $requestedToken = substr($requestedToken, 7); // strip "Bearer "
                }

                return Http::response($response, 200);
            }

            return Http::response([], 404);
        });

        app(OpenAiUsageService::class)->getUsage();

        $this->assertSame('sk-org-admin', $requestedToken);
    }

    public function test_returns_fresh_data_when_cache_miss(): void
    {
        Config::set('openai.org_api_key', 'sk-org-admin-key');

        $response = $this->usageResponse(1, 100, 50);
        Http::fake(function ($request) use ($response) {
            if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
                return Http::response($response, 200);
            }

            return Http::response([], 404);
        });

        $service = app(OpenAiUsageService::class);

        $first = $service->getUsage();
        Cache::flush();
        $second = $service->getUsage();

        $this->assertSame($first['requests_today'], $second['requests_today']);
    }
}
