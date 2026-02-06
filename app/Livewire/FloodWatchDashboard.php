<?php

namespace App\Livewire;

use App\Flood\Services\FloodEnrichmentService;
use App\Models\SystemActivity;
use App\Roads\IncidentIcon;
use App\Services\FloodWatchService;
use App\Services\FloodWatchTrendService;
use App\Services\LocationResolver;
use App\Services\RiskService;
use App\Services\SearchMessageBuilder;
use App\Support\OpenAiErrorHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

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

    /** @var array{index: int, label: string, summary: string}|null */
    public ?array $risk = null;

    public function mount(RiskService $riskService, FloodWatchService $floodWatchService, FloodEnrichmentService $floodEnrichment): void
    {
        $this->mapCenter = [
            'lat' => config('flood-watch.default_lat'),
            'long' => config('flood-watch.default_long'),
        ];

        $defaultLat = config('flood-watch.default_lat');
        $defaultLong = config('flood-watch.default_long');
        $mapData = Cache::remember("flood-watch:map-data:{$defaultLat}:{$defaultLong}", 300, function () use ($floodWatchService, $defaultLat, $defaultLong) {
            try {
                return $floodWatchService->getMapDataUncached($defaultLat, $defaultLong, null);
            } catch (\Throwable) {
                return ['floods' => [], 'incidents' => [], 'riverLevels' => [], 'lastChecked' => null];
            }
        });
        $this->floods = $floodEnrichment->enrichWithDistance($mapData['floods'] ?? [], $defaultLat, $defaultLong);
        $this->incidents = IncidentIcon::enrich($mapData['incidents'] ?? []);
        $this->riverLevels = $mapData['riverLevels'] ?? [];
        $this->lastChecked = $mapData['lastChecked'] ?? null;

        $this->risk = Cache::remember('flood-watch-risk-gauge', 900, function () use ($riskService) {
            try {
                $result = $riskService->calculate();

                return [
                    'index' => $result['index'],
                    'label' => $result['label'],
                    'summary' => $result['summary'],
                ];
            } catch (\Throwable) {
                return null;
            }
        });

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

    public function search(FloodWatchService $assistant, LocationResolver $locationResolver, FloodWatchTrendService $trendService, FloodEnrichmentService $floodEnrichment, SearchMessageBuilder $messageBuilder, OpenAiErrorHandler $errorHandler): void
    {
        $this->reset(['assistantResponse', 'floods', 'incidents', 'forecast', 'weather', 'riverLevels', 'hasUserLocation', 'lastChecked', 'error', 'retryAfterTimestamp']);
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

        $message = $messageBuilder->build($locationTrimmed, $validation);
        $cacheKey = $locationTrimmed !== '' ? $locationTrimmed : null;
        $userLat = $validation['lat'] ?? null;
        $userLong = $validation['long'] ?? null;
        $region = $validation['region'] ?? ($validation === null ? 'somerset' : null);

        try {
            $onProgress = fn (string $status) => $streamStatus($status);
            $result = $assistant->chat($message, [], $cacheKey, $userLat, $userLong, $region, $onProgress);
            $this->assistantResponse = $result['response'];
            $this->floods = $floodEnrichment->enrichWithDistance(
                $result['floods'],
                $userLat,
                $userLong
            );
            $this->incidents = IncidentIcon::enrich($result['incidents']);
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
            if ($errorHandler->isRateLimitError($e)) {
                $errorHandler->logRateLimit($e);
                $this->retryAfterTimestamp = now()->addSeconds(60)->timestamp;
            }
            $this->error = $errorHandler->formatErrorMessage($e);
        } finally {
            $this->loading = false;
        }
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
        $this->incidents = IncidentIcon::enrich(is_array($data['incidents'] ?? null) ? $data['incidents'] : []);
        $this->forecast = is_array($data['forecast'] ?? null) ? $data['forecast'] : [];
        $this->weather = is_array($data['weather'] ?? null) ? $data['weather'] : [];
        $this->riverLevels = is_array($data['riverLevels'] ?? null) ? $data['riverLevels'] : [];
        $this->mapCenter = is_array($data['mapCenter'] ?? null) ? $data['mapCenter'] : null;
        $this->hasUserLocation = (bool) ($data['hasUserLocation'] ?? false);
        $this->lastChecked = is_string($data['lastChecked'] ?? null) ? $data['lastChecked'] : null;
    }

    public function render()
    {
        return view('livewire.flood-watch-dashboard', [
            'activities' => SystemActivity::recent(10),
        ]);
    }
}
