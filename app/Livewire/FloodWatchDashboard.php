<?php

namespace App\Livewire;

use App\Services\FloodWatchService;
use App\Services\FloodWatchTrendService;
use App\Services\LocationResolver;
use App\Support\IncidentIcon;
use App\Support\LogMasker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use Psr\Http\Message\ResponseInterface;

#[Layout('layouts.flood-watch')]
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

    public bool $autoRefreshEnabled = false;

    public function mount(): void
    {
        if (Auth::guest()) {
            $key = 'flood-watch-guest:'.request()->ip();
            if (RateLimiter::tooManyAttempts($key, 1)) {
                $seconds = RateLimiter::availableIn($key);
                $this->error = __('flood-watch.error.guest_rate_limit');
                $this->retryAfterTimestamp = time() + $seconds;
            }
        }
    }

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

        if (Auth::guest()) {
            $key = 'flood-watch-guest:'.request()->ip();
            $decaySeconds = 900;

            if (RateLimiter::tooManyAttempts($key, 1)) {
                $seconds = RateLimiter::availableIn($key);
                $this->error = __('flood-watch.error.guest_rate_limit');
                $this->retryAfterTimestamp = time() + $seconds;
                $this->loading = false;

                return;
            }

            RateLimiter::hit($key, $decaySeconds);
        }

        $locationTrimmed = trim($this->location);
        $streamStatus = function (string $message): void {
            $this->stream(to: 'searchStatus', content: $message, replace: true);
        };

        $validation = null;
        if ($locationTrimmed !== '') {
            $streamStatus(__('flood-watch.progress.looking_up_location'));
            $validation = $locationResolver->resolve($locationTrimmed);
            if (! $validation['valid']) {
                $this->error = $validation['error'] ?? __('flood-watch.error.invalid_location');
                $this->loading = false;

                return;
            }
            if (! $validation['in_area']) {
                $this->error = $validation['error'] ?? __('flood-watch.error.outside_area');
                $this->loading = false;

                return;
            }
        }

        $message = $this->buildMessage($locationTrimmed, $validation);
        $cacheKey = $locationTrimmed !== '' ? $locationTrimmed : null;
        $userLat = $validation['lat'] ?? null;
        $userLong = $validation['lng'] ?? null;
        $region = $validation['region'] ?? ($validation === null ? 'somerset' : null);

        try {
            $onProgress = fn (string $status) => $streamStatus($status);
            $result = $assistant->chat($message, [], $cacheKey, $userLat, $userLong, $region, $onProgress);
            $this->assistantResponse = $result['response'];
            $this->floods = $this->enrichFloodsWithDistance(
                $result['floods'],
                $userLat,
                $userLong
            );
            $this->incidents = $this->enrichIncidentsWithIcons($result['incidents']);
            $this->forecast = $result['forecast'] ?? [];
            $this->weather = $result['weather'] ?? [];
            $this->riverLevels = $result['riverLevels'] ?? [];
            $lat = $userLat ?? config('flood-watch.default_lat');
            $lng = $userLong ?? config('flood-watch.default_long');
            $this->mapCenter = ['lat' => $lat, 'lng' => $lng];
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
            $floodLng = $flood['lng'] ?? null;
            $flood['distanceKm'] = null;
            if ($hasCenter && $floodLat !== null && $floodLng !== null) {
                $flood['distanceKm'] = round($this->haversineDistanceKm($userLat, $userLong, (float) $floodLat, (float) $floodLng), 1);
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
                    $context['response_body'] = LogMasker::maskResponseBody($body);
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
            return __('flood-watch.error.rate_limit');
        }
        if (str_contains($message, 'timed out') || str_contains($message, 'cURL error 28') || str_contains($message, 'Operation timed out')) {
            return __('flood-watch.error.timeout');
        }
        if (str_contains($message, 'Connection') && (str_contains($message, 'refused') || str_contains($message, 'reset'))) {
            return __('flood-watch.error.connection');
        }
        if (config('app.debug')) {
            return $message;
        }

        return __('flood-watch.error.generic');
    }

    /**
     * @param  array{lat?: float, lng?: float, outcode?: string, display_name?: string}|null  $validation
     */
    private function buildMessage(string $location, ?array $validation): string
    {
        if ($location === '') {
            return __('flood-watch.message.check_status_default');
        }

        $label = $validation['display_name'] ?? $location;
        $coords = '';
        if ($validation !== null && isset($validation['lat'], $validation['lng'])) {
            $coords = sprintf(' (lat: %.4f, lng: %.4f)', $validation['lat'], $validation['lng']);
        }

        return __('flood-watch.message.check_status_location', ['label' => $label, 'coords' => $coords]);
    }

    /**
     * Add icon and human-readable labels to each incident.
     *
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<int, array<string, mixed>>
     */
    private function enrichIncidentsWithIcons(array $incidents): array
    {
        return array_map(function (array $incident): array {
            $incident['icon'] = IncidentIcon::forIncident(
                $incident['incidentType'] ?? null,
                $incident['managementType'] ?? null
            );
            $incident['statusLabel'] = IncidentIcon::statusLabel($incident['status'] ?? null);
            $incident['typeLabel'] = IncidentIcon::typeLabel($incident['incidentType'] ?? $incident['managementType'] ?? null);

            return $incident;
        }, $incidents);
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
        $this->incidents = $this->enrichIncidentsWithIcons(is_array($data['incidents'] ?? null) ? $data['incidents'] : []);
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
