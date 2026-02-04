<?php

namespace App\Livewire;

use App\Services\FloodWatchService;
use App\Services\FloodWatchTrendService;
use App\Services\LocationResolver;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use Psr\Http\Message\ResponseInterface;

#[Layout('layouts.app')]
class FloodWatchDashboard extends Component
{
    public string $location = '';

    public bool $loading = false;

    public ?string $assistantResponse = null;

    public array $floods = [];

    public array $incidents = [];

    public array $forecast = [];

    public array $weather = [];

    public array $riverLevels = [];

    public ?array $mapCenter = null;

    public bool $hasUserLocation = false;

    public ?string $lastChecked = null;

    public ?string $error = null;

    public ?int $retryAfterTimestamp = null;

    public function canRetry(): bool
    {
        if ($this->retryAfterTimestamp === null) {
            return true;
        }

        return time() >= $this->retryAfterTimestamp;
    }

    public function checkRetry(): void
    {
        if ($this->canRetry() && $this->retryAfterTimestamp !== null) {
            $this->retryAfterTimestamp = null;
        }
    }

    public function search(FloodWatchService $assistant, LocationResolver $locationResolver, FloodWatchTrendService $trendService): void
    {
        $this->reset(['assistantResponse', 'floods', 'incidents', 'forecast', 'weather', 'riverLevels', 'mapCenter', 'hasUserLocation', 'lastChecked', 'error', 'retryAfterTimestamp']);
        $this->loading = true;

        $locationTrimmed = trim($this->location);
        $statusMessages = [];

        $streamStatus = function (string $message) use (&$statusMessages): void {
            $statusMessages[] = $message;
            $content = implode('<br>', $statusMessages);
            $this->stream(to: 'searchStatus', content: $content, replace: true);
        };

        $validation = null;
        if ($locationTrimmed !== '') {
            $streamStatus('Looking up location...');
            $validation = $locationResolver->resolve($locationTrimmed);
            if (! $validation['valid']) {
                $this->error = $validation['error'] ?? 'Invalid location.';
                $this->loading = false;

                return;
            }
            if (! $validation['in_area']) {
                $this->error = $validation['error'] ?? 'This location is outside the South West.';
                $this->loading = false;

                return;
            }
        }

        $message = $this->buildMessage($locationTrimmed, $validation);
        $cacheKey = $locationTrimmed !== '' ? $locationTrimmed : null;
        $userLat = $validation['lat'] ?? null;
        $userLong = $validation['long'] ?? null;
        $region = $validation['region'] ?? null;

        try {
            $onProgress = fn (string $status) => $streamStatus($status);
            $result = $assistant->chat($message, [], $cacheKey, $userLat, $userLong, $region, $onProgress);
            $this->assistantResponse = $result['response'];
            $this->floods = $this->enrichFloodsWithDistance(
                $result['floods'],
                $userLat,
                $userLong
            );
            $this->incidents = $result['incidents'];
            $this->forecast = $result['forecast'] ?? [];
            $this->weather = $result['weather'] ?? [];
            $this->riverLevels = $result['riverLevels'] ?? [];
            $lat = $userLat ?? config('flood-watch.default_lat');
            $long = $userLong ?? config('flood-watch.default_long');
            $this->mapCenter = ['lat' => $lat, 'long' => $long];
            $this->hasUserLocation = $userLat !== null && $userLong !== null;
            $this->lastChecked = $result['lastChecked'] ?? null;

            $trendService->record(
                $locationTrimmed !== '' ? $locationTrimmed : null,
                $userLat,
                $userLong,
                $region,
                count($this->floods),
                count($this->incidents),
                $this->lastChecked
            );

            $this->dispatch('search-completed');
        } catch (\Throwable $e) {
            report($e);
            if ($this->isAiRateLimitError($e)) {
                $this->logOpenAiRateLimit($e);
                $this->retryAfterTimestamp = now()->addSeconds(60)->timestamp;
            }
            $this->error = $this->formatErrorMessage($e);
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Enrich floods with distance from user location and sort by proximity (closest first).
     *
     * @param  array<int, array<string, mixed>>  $floods
     * @return array<int, array<string, mixed>>
     */
    private function enrichFloodsWithDistance(array $floods, ?float $userLat, ?float $userLong): array
    {
        $hasCenter = $userLat !== null && $userLong !== null;

        $enriched = array_map(function (array $flood) use ($userLat, $userLong, $hasCenter) {
            $floodLat = $flood['lat'] ?? null;
            $floodLong = $flood['long'] ?? null;
            $flood['distanceKm'] = null;
            if ($hasCenter && $floodLat !== null && $floodLong !== null) {
                $flood['distanceKm'] = round($this->haversineDistanceKm($userLat, $userLong, (float) $floodLat, (float) $floodLong), 1);
            }

            return $flood;
        }, $floods);

        if ($hasCenter) {
            return collect($enriched)
                ->sortBy(fn (array $f) => $f['distanceKm'] ?? PHP_FLOAT_MAX)
                ->values()
                ->all();
        }

        return collect($enriched)
            ->sortByDesc(fn (array $f) => $f['timeMessageChanged'] ?? $f['timeRaised'] ?? '')
            ->values()
            ->all();
    }

    private function haversineDistanceKm(float $lat1, float $long1, float $lat2, float $long2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLong = deg2rad($long2 - $long1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLong / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private function logOpenAiRateLimit(\Throwable $e): void
    {
        $response = null;
        if ($e instanceof RateLimitException) {
            $response = $e->response;
        }
        if ($e instanceof ErrorException) {
            $response = $e->response;
        }

        $context = [
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ];

        if ($response instanceof ResponseInterface) {
            $context['rate_limit'] = $this->extractRateLimitHeaders($response);
            $context['retry_after'] = $response->getHeaderLine('Retry-After');
            $context['status_code'] = $response->getStatusCode();
            try {
                $body = (string) $response->getBody();
                if ($body !== '') {
                    $context['response_body'] = $body;
                }
            } catch (\Throwable) {
                // Stream may already be consumed
            }
        }

        Log::warning('OpenAI rate limit exceeded', $context);
    }

    /**
     * @return array<string, string>
     */
    private function extractRateLimitHeaders(ResponseInterface $response): array
    {
        $headers = [
            'x-ratelimit-limit-requests' => $response->getHeaderLine('x-ratelimit-limit-requests'),
            'x-ratelimit-remaining-requests' => $response->getHeaderLine('x-ratelimit-remaining-requests'),
            'x-ratelimit-reset-requests' => $response->getHeaderLine('x-ratelimit-reset-requests'),
            'x-ratelimit-limit-tokens' => $response->getHeaderLine('x-ratelimit-limit-tokens'),
            'x-ratelimit-remaining-tokens' => $response->getHeaderLine('x-ratelimit-remaining-tokens'),
            'x-ratelimit-reset-tokens' => $response->getHeaderLine('x-ratelimit-reset-tokens'),
        ];

        return array_filter($headers, fn (string $v) => $v !== '');
    }

    private function isAiRateLimitError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'rate limit') || str_contains($message, '429');
    }

    private function formatErrorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (str_contains(strtolower($message), 'rate limit') || str_contains(strtolower($message), '429')) {
            return 'AI service rate limit exceeded. Please wait a minute and try again.';
        }
        if (str_contains($message, 'timed out') || str_contains($message, 'cURL error 28') || str_contains($message, 'Operation timed out')) {
            return 'The request took too long. The AI service may be busy. Please try again in a moment.';
        }
        if (str_contains($message, 'Connection') && (str_contains($message, 'refused') || str_contains($message, 'reset'))) {
            return 'Unable to reach the service. Please check your connection and try again.';
        }
        if (config('app.debug')) {
            return $message;
        }

        return 'Unable to get a response. Please try again.';
    }

    /**
     * @param  array{lat?: float, long?: float, outcode?: string, display_name?: string}|null  $validation
     */
    private function buildMessage(string $location, ?array $validation): string
    {
        if ($location === '') {
            return 'Check flood and road status for the South West (Bristol, Somerset, Devon, Cornwall).';
        }

        $label = $validation['display_name'] ?? $location;
        $coords = '';
        if ($validation !== null && isset($validation['lat'], $validation['long'])) {
            $coords = sprintf(' (lat: %.4f, long: %.4f)', $validation['lat'], $validation['long']);
        }

        return "Check flood and road status for {$label}{$coords} in the South West.";
    }

    /**
     * Restore component state from client-side storage (e.g. localStorage).
     * Called by the frontend when cached results exist.
     *
     * @param  array{assistantResponse?: string, floods?: array, incidents?: array, forecast?: array, weather?: array, riverLevels?: array, mapCenter?: array, hasUserLocation?: bool, lastChecked?: string}  $data
     */
    public function restoreFromStorage(array $data): void
    {
        $this->assistantResponse = is_string($data['assistantResponse'] ?? null) ? $data['assistantResponse'] : null;
        $this->floods = is_array($data['floods'] ?? null) ? $data['floods'] : [];
        $this->incidents = is_array($data['incidents'] ?? null) ? $data['incidents'] : [];
        $this->forecast = is_array($data['forecast'] ?? null) ? $data['forecast'] : [];
        $this->weather = is_array($data['weather'] ?? null) ? $data['weather'] : [];
        $this->riverLevels = is_array($data['riverLevels'] ?? null) ? $data['riverLevels'] : [];
        $this->mapCenter = is_array($data['mapCenter'] ?? null) ? $data['mapCenter'] : null;
        $this->hasUserLocation = (bool) ($data['hasUserLocation'] ?? false);
        $this->lastChecked = is_string($data['lastChecked'] ?? null) ? $data['lastChecked'] : null;
    }

    public function render()
    {
        return view('livewire.flood-watch-dashboard');
    }
}
