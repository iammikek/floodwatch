<?php

namespace App\Livewire;

use App\Enums\IncidentType;
use App\Models\LocationBookmark;
use App\Models\UserSearch;
use App\Services\FloodWatchService;
use App\Services\FloodWatchTrendService;
use App\Services\LocationResolver;
use App\Services\RouteCheckService;
use App\Services\UserSearchService;
use App\Support\IncidentIcon;
use App\Support\LogMasker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

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

    /**
     * Map viewport bounds (set by map moveend). When set, lists/cards show only items in view.
     *
     * @var array{n: float, s: float, e: float, w: float}|null
     */
    public ?array $mapBounds = null;

    public bool $hasUserLocation = false;

    public ?string $lastChecked = null;

    public ?string $error = null;

    public ?int $retryAfterTimestamp = null;

    public bool $autoRefreshEnabled = false;

    public string $routeFrom = '';

    public string $routeTo = '';

    public bool $routeCheckLoading = false;

    public ?array $routeCheckResult = null;

    public ?string $displayLocation = null;

    public ?string $outcode = null;

    /**
     * Which results layout to render: 'mobile' or 'desktop'. Set from client by viewport so only one layout is in the DOM (no duplicate IDs).
     */
    public string $layoutVariant = 'mobile';

    /**
     * User's location bookmarks (when logged in).
     *
     * @return array<int, array{id: int, label: string, location: string, lat: float, lng: float, region: ?string, is_default: bool}>
     */
    public function getBookmarksProperty(): array
    {
        if (Auth::guest()) {
            return [];
        }

        return Auth::user()->locationBookmarks()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get()
            ->map(fn (LocationBookmark $b) => [
                'id' => $b->id,
                'label' => $b->label,
                'location' => $b->location,
                'lat' => $b->lat,
                'lng' => $b->lng,
                'region' => $b->region,
                'is_default' => $b->is_default,
            ])
            ->values()
            ->all();
    }

    /**
     * Last 5 unique searches for the current user or session.
     * Same location (by lat/lng) appears only once, most recent first.
     *
     * @return array<int, array{location: string, lat: float, lng: float, region: ?string}>
     */
    public function getRecentSearchesProperty(): array
    {
        $query = UserSearch::query()
            ->latest('searched_at')
            ->limit(20);

        if (Auth::check()) {
            $query->where('user_id', Auth::id());
        } else {
            $query->where('session_id', session()->getId());
        }

        return $query->get()
            ->unique(fn (UserSearch $s) => round($s->lat, 4).','.round($s->lng, 4))
            ->take(5)
            ->map(fn (UserSearch $s) => [
                'location' => $s->location,
                'lat' => $s->lat,
                'lng' => $s->lng,
                'region' => $s->region,
            ])
            ->values()
            ->all();
    }

    /**
     * House risk status derived from floods (at_risk or clear).
     */
    public function getHouseRiskProperty(): string
    {
        $activeFloods = $this->getActiveFloods();

        return count($activeFloods) > 0 ? 'at_risk' : 'clear';
    }

    /**
     * Roads risk status derived from incidents (closed, delays, or clear).
     */
    public function getRoadsRiskProperty(): string
    {
        $hasBlocking = $this->hasBlockingClosure();
        $hasDelays = $this->hasDelaysOrLaneClosures();

        if ($hasBlocking) {
            return 'closed';
        }
        if ($hasDelays) {
            return 'delays';
        }

        return 'clear';
    }

    /**
     * Update map viewport bounds so lists/cards favour data in the visible area.
     */
    public function setMapBounds(float $north, float $south, float $east, float $west): void
    {
        $this->mapBounds = [
            'n' => $north,
            's' => $south,
            'e' => $east,
            'w' => $west,
        ];
    }

    /**
     * Floods to display in lists/cards: viewport-filtered when map bounds are set, otherwise all.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFloodsInViewProperty(): array
    {
        return $this->filterItemsInBounds(
            $this->floods,
            fn (array $f) => [
                isset($f['lat']) ? (float) $f['lat'] : null,
                isset($f['lng']) ? (float) $f['lng'] : (isset($f['long']) ? (float) $f['long'] : null),
            ]
        );
    }

    /**
     * Incidents to display in lists/cards: viewport-filtered when map bounds are set, otherwise all.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getIncidentsInViewProperty(): array
    {
        return $this->filterItemsInBounds(
            $this->incidents,
            fn (array $i) => [
                isset($i['lat']) ? (float) $i['lat'] : (isset($i['latitude']) ? (float) $i['latitude'] : null),
                isset($i['lng']) ? (float) $i['lng'] : (isset($i['longitude']) ? (float) $i['longitude'] : null),
            ]
        );
    }

    /**
     * Filter items to those inside mapBounds. Items without coords are included when bounds are set.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  callable(array): array{0: ?float, 1: ?float}  $getLatLng
     * @return array<int, array<string, mixed>>
     */
    private function filterItemsInBounds(array $items, callable $getLatLng): array
    {
        if ($this->mapBounds === null) {
            return $items;
        }
        $b = $this->mapBounds;
        $inBounds = function (array $item) use ($b, $getLatLng): bool {
            [$lat, $lng] = $getLatLng($item);
            if ($lat === null || $lng === null) {
                return true;
            }

            return $lat >= $b['s'] && $lat <= $b['n'] && $lng >= $b['w'] && $lng <= $b['e'];
        };

        return array_values(array_filter($items, $inBounds));
    }

    /**
     * Action steps derived from floods and incidents.
     *
     * @return array<int, string>
     */
    public function getActionStepsProperty(): array
    {
        $steps = [];
        $activeFloods = $this->getActiveFloods();
        $hasBlocking = $this->hasBlockingClosure();

        if (count($activeFloods) > 0) {
            $steps[] = 'deploy_defences';
            $steps[] = 'monitor_updates';
        }
        if ($hasBlocking) {
            $steps[] = 'avoid_routes';
        }
        if (count($steps) === 0) {
            $steps[] = 'none';
        }

        return $steps;
    }

    /**
     * Whether any flood has severityLevel === 1 (Danger to Life).
     */
    public function getHasDangerToLifeProperty(): bool
    {
        foreach ($this->floods as $flood) {
            $level = (int) ($flood['severityLevel'] ?? 4);
            if ($level === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Floods with severityLevel 1, 2, or 3 (active; 4 = inactive).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getActiveFloods(): array
    {
        return array_values(array_filter($this->floods, fn (array $f) => ((int) ($f['severityLevel'] ?? 4)) < 4));
    }

    private function hasBlockingClosure(): bool
    {
        foreach ($this->incidents as $incident) {
            $type = (string) ($incident['managementType'] ?? $incident['incidentType'] ?? $incident['incident_type'] ?? '');
            if (IncidentType::isBlockingClosure($type)) {
                return true;
            }
        }

        return false;
    }

    private function hasDelaysOrLaneClosures(): bool
    {
        foreach ($this->incidents as $incident) {
            $type = strtolower((string) ($incident['incidentType'] ?? $incident['incident_type'] ?? ''));
            if (str_contains($type, 'lane') || str_contains($type, 'closure') || ! empty($incident['delayTime'] ?? $incident['delay_time'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    public function selectRecentSearch(string $location): void
    {
        $this->location = $location === config('flood-watch.default_location_sentinel') ? '' : $location;
        $this->search(
            app(FloodWatchService::class),
            app(LocationResolver::class),
            app(FloodWatchTrendService::class),
            app(UserSearchService::class)
        );
    }

    public function selectBookmark(int $bookmarkId): void
    {
        $bookmark = Auth::user()?->locationBookmarks()->find($bookmarkId);
        if ($bookmark === null) {
            return;
        }

        $this->location = $bookmark->location;
        $validation = [
            'valid' => true,
            'in_area' => true,
            'lat' => $bookmark->lat,
            'lng' => $bookmark->lng,
            'region' => $bookmark->region ?? 'somerset',
            'display_name' => $bookmark->location,
            'outcode' => $this->extractOutcode($bookmark->location),
        ];

        $this->performSearch(
            app(FloodWatchService::class),
            app(LocationResolver::class),
            app(FloodWatchTrendService::class),
            app(UserSearchService::class),
            $validation
        );
    }

    /**
     * Guest rate limit key. Shared across search and route check for a combined
     * limit of 1 request/second (either action counts). Copy uses "request" not
     * "search"/"route check" to reflect this.
     */
    private function guestRateLimitKey(): string
    {
        return 'flood-watch-guest:'.request()->ip();
    }

    public function mount(): void
    {
        if (Auth::guest()) {
            $key = $this->guestRateLimitKey();
            if (RateLimiter::tooManyAttempts($key, 1)) {
                $seconds = RateLimiter::availableIn($key);
                $this->error = __('flood-watch.errors.guest_rate_limit', ['action' => 'request']);
                $this->retryAfterTimestamp = time() + $seconds;
            }
        } else {
            $default = Auth::user()->locationBookmarks()->where('is_default', true)->first();
            if ($default !== null && $this->location === '') {
                $this->location = $default->location;
            }
            if ($default !== null && $this->routeFrom === '') {
                $this->routeFrom = $default->location;
            }
        }
    }

    public function checkRoute(RouteCheckService $routeCheckService): void
    {
        $this->error = null;

        if (Auth::guest()) {
            $key = $this->guestRateLimitKey();
            $decaySeconds = 1;
            if (RateLimiter::tooManyAttempts($key, 1)) {
                $this->routeCheckLoading = false;
                $this->retryAfterTimestamp = time() + RateLimiter::availableIn($key);
                $this->routeCheckResult = [
                    'verdict' => 'error',
                    'summary' => __('flood-watch.errors.guest_rate_limit', ['action' => 'request']),
                    'floods_on_route' => [],
                    'incidents_on_route' => [],
                    'alternatives' => [],
                    'route_geometry' => null,
                ];

                return;
            }
            RateLimiter::hit($key, $decaySeconds);
        }

        $this->routeCheckLoading = true;
        $this->routeCheckResult = null;

        try {
            $result = $routeCheckService->check($this->routeFrom, $this->routeTo);
            $this->routeCheckResult = $result->toArray();
        } finally {
            $this->routeCheckLoading = false;
        }
    }

    #[On('location-from-gps-for-route')]
    public function setRouteFromFromGps(float $lat, float $lng, LocationResolver $locationResolver): void
    {
        $result = $locationResolver->reverseFromCoords($lat, $lng);
        if ($result['valid'] && $result['in_area']) {
            $this->routeFrom = $result['location'];
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

    public function search(FloodWatchService $assistant, LocationResolver $locationResolver, FloodWatchTrendService $trendService, UserSearchService $userSearchService): void
    {
        $locationTrimmed = trim($this->location);
        $validation = null;

        if ($locationTrimmed !== '') {
            $this->stream(to: 'searchStatus', content: __('flood-watch.progress.looking_up_location'), replace: true);
            $validation = $locationResolver->resolve($locationTrimmed);
            if (! $validation['valid']) {
                $this->reset(['assistantResponse', 'floods', 'incidents', 'forecast', 'weather', 'riverLevels', 'mapCenter', 'mapBounds', 'hasUserLocation', 'lastChecked', 'retryAfterTimestamp']);
                $this->error = $validation['error'] ?? __('flood-watch.errors.invalid_location');
                $this->loading = false;

                return;
            }
            if (! $validation['in_area']) {
                $this->reset(['assistantResponse', 'floods', 'incidents', 'forecast', 'weather', 'riverLevels', 'mapCenter', 'mapBounds', 'hasUserLocation', 'lastChecked', 'retryAfterTimestamp']);
                $this->error = $validation['error'] ?? __('flood-watch.errors.outside_area');
                $this->loading = false;

                return;
            }
        }

        $this->performSearch($assistant, $locationResolver, $trendService, $userSearchService, $validation);
    }

    #[On('location-from-gps')]
    public function searchFromGps(float $lat, float $lng, FloodWatchService $assistant, LocationResolver $locationResolver, FloodWatchTrendService $trendService, UserSearchService $userSearchService): void
    {
        $this->stream(to: 'searchStatus', content: __('flood-watch.progress.looking_up_location'), replace: true);
        $result = $locationResolver->reverseFromCoords($lat, $lng);

        if (! $result['valid']) {
            $this->reset(['assistantResponse', 'floods', 'incidents', 'forecast', 'weather', 'riverLevels', 'mapCenter', 'mapBounds', 'hasUserLocation', 'lastChecked', 'retryAfterTimestamp']);
            $this->error = $result['error'] ?? __('flood-watch.dashboard.gps_error');
            $this->loading = false;

            return;
        }

        if (! $result['in_area']) {
            $this->reset(['assistantResponse', 'floods', 'incidents', 'forecast', 'weather', 'riverLevels', 'mapCenter', 'mapBounds', 'hasUserLocation', 'lastChecked', 'retryAfterTimestamp']);
            $this->error = __('flood-watch.errors.outside_area');
            $this->loading = false;

            return;
        }

        $this->location = $result['location'];
        $validation = [
            'valid' => true,
            'in_area' => true,
            'lat' => $lat,
            'lng' => $lng,
            'region' => $result['region'],
            'display_name' => $result['location'],
        ];

        $this->performSearch($assistant, $locationResolver, $trendService, $userSearchService, $validation);
    }

    /**
     * @param  array{valid: bool, in_area: bool, lat?: float, lng?: float, region?: string, display_name?: string}|null  $validation
     */
    private function performSearch(
        FloodWatchService $assistant,
        LocationResolver $locationResolver,
        FloodWatchTrendService $trendService,
        UserSearchService $userSearchService,
        ?array $validation
    ): void {
        $this->reset(['assistantResponse', 'floods', 'incidents', 'forecast', 'weather', 'riverLevels', 'mapCenter', 'mapBounds', 'hasUserLocation', 'lastChecked', 'error', 'retryAfterTimestamp', 'displayLocation', 'outcode']);
        $this->loading = true;

        if (Auth::guest()) {
            $key = $this->guestRateLimitKey();
            $decaySeconds = 1;

            if (RateLimiter::tooManyAttempts($key, 1)) {
                $seconds = RateLimiter::availableIn($key);
                $this->error = __('flood-watch.errors.guest_rate_limit', ['action' => 'request']);
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

        $message = $this->buildMessage($locationTrimmed, $validation);
        $cacheKey = $locationTrimmed !== '' ? $locationTrimmed : null;
        $userLat = data_get($validation, 'lat');
        $userLng = data_get($validation, 'lng');
        $region = $validation === null ? 'somerset' : (data_get($validation, 'region') ?? 'somerset');

        try {
            $onProgress = fn (string $status) => $streamStatus($status);
            $result = $assistant->chat($message, [], $cacheKey, $userLat, $userLng, $region, auth()->id(), $onProgress);

            if (! empty($result['errors'])) {
                $this->error = $result['response'];
                if (($result['error_key'] ?? '') === 'rate_limit') {
                    $this->logOpenAiRateLimit(new \RuntimeException('Rate limit returned from service'));
                    $this->retryAfterTimestamp = now()->addSeconds(60)->timestamp;
                }
                $this->assistantResponse = $result['response'];
                $this->floods = [];
                $this->incidents = [];
                $this->forecast = $result['forecast'] ?? [];
                $this->weather = $result['weather'] ?? [];
                $this->riverLevels = $result['riverLevels'] ?? [];
                $this->lastChecked = $result['lastChecked'] ?? null;
                $lat = $userLat ?? config('flood-watch.default_lat');
                $lng = $userLng ?? config('flood-watch.default_lng');
                $this->mapCenter = ['lat' => $lat, 'lng' => $lng];
                $this->hasUserLocation = $userLat !== null && $userLng !== null;

                return;
            }

            $this->assistantResponse = $result['response'];
            $this->floods = $this->stripPolygonsFromFloods(
                $this->enrichFloodsWithDistance(
                    $result['floods'],
                    $userLat,
                    $userLng
                )
            );
            $this->incidents = IncidentIcon::enrichIncidents($result['incidents']);
            $this->forecast = $result['forecast'] ?? [];
            $this->weather = $result['weather'] ?? [];
            $this->riverLevels = $result['riverLevels'] ?? [];
            $lat = $userLat ?? config('flood-watch.default_lat');
            $lng = $userLng ?? config('flood-watch.default_lng');
            $this->mapCenter = ['lat' => $lat, 'lng' => $lng];
            $this->hasUserLocation = $userLat !== null && $userLng !== null;
            $this->lastChecked = $result['lastChecked'] ?? null;
            $this->displayLocation = data_get($validation, 'display_name') ?? $locationTrimmed;
            $this->outcode = data_get($validation, 'outcode');

            $trendService->record(
                $locationTrimmed !== '' ? $locationTrimmed : null,
                $userLat,
                $userLng,
                $region,
                count($this->floods),
                count($this->incidents),
                $this->lastChecked
            );

            $userSearchService->record(
                $locationTrimmed !== '' ? $locationTrimmed : config('flood-watch.default_location_sentinel'),
                $userLat,
                $userLng,
                $region,
                Auth::id(),
                Auth::guest() ? session()->getId() : null
            );

            $this->dispatch('search-completed');
        } catch (Throwable $e) {
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
     * Remove polygon GeoJSON from each flood to keep Livewire payload small; polygons are loaded from cache by the map.
     *
     * @param  array<int, array<string, mixed>>  $floods
     * @return array<int, array<string, mixed>>
     */
    private function stripPolygonsFromFloods(array $floods): array
    {
        return array_map(function (array $flood) {
            $out = $flood;
            unset($out['polygon']);

            return $out;
        }, $floods);
    }

    /**
     * Enrich floods with distance from user location and sort by proximity (closest first).
     *
     * @param  array<int, array<string, mixed>>  $floods
     * @return array<int, array<string, mixed>>
     */
    private function enrichFloodsWithDistance(array $floods, ?float $userLat, ?float $userLng): array
    {
        $hasCenter = $userLat !== null && $userLng !== null;

        $enriched = array_map(function (array $flood) use ($userLat, $userLng, $hasCenter) {
            $floodLat = $flood['lat'] ?? null;
            $floodLng = $flood['lng'] ?? $flood['long'] ?? null;
            $flood['distanceKm'] = null;
            if ($hasCenter && $floodLat !== null && $floodLng !== null) {
                $flood['distanceKm'] = round($this->haversineDistanceKm($userLat, $userLng, (float) $floodLat, (float) $floodLng), 1);
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

    private function extractOutcode(string $location): ?string
    {
        $trimmed = trim($location);
        if (preg_match('/^([A-Za-z]{1,2}[0-9][0-9A-Za-z]?)(?:\s+[0-9][A-Za-z]{2})?$/i', $trimmed, $m)) {
            return $m[1];
        }

        return null;
    }

    private function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private function logOpenAiRateLimit(Throwable $e): void
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
            } catch (Throwable) {
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

    private function isAiRateLimitError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'rate limit') || str_contains($message, '429');
    }

    private function formatErrorMessage(Throwable $e): string
    {
        $message = $e->getMessage();
        if (str_contains(strtolower($message), 'rate limit') || str_contains(strtolower($message), '429')) {
            return __('flood-watch.errors.rate_limit');
        }
        if (str_contains($message, 'timed out') || str_contains($message, 'cURL error 28') || str_contains($message, 'Operation timed out')) {
            return __('flood-watch.errors.timeout');
        }
        if (str_contains($message, 'Connection') && (str_contains($message, 'refused') || str_contains($message, 'reset'))) {
            return __('flood-watch.errors.connection');
        }
        if (config('app.debug')) {
            return $message;
        }

        return __('flood-watch.errors.generic');
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
     * Restore component state from client-side storage (e.g. localStorage).
     * Called by the frontend when cached results exist.
     *
     * @param  array{assistantResponse?: string, floods?: array, incidents?: array, forecast?: array, weather?: array, riverLevels?: array, mapCenter?: array, hasUserLocation?: bool, lastChecked?: string}  $data
     */
    public function restoreFromStorage(array $data): void
    {
        $this->assistantResponse = is_string($data['assistantResponse'] ?? null) ? $data['assistantResponse'] : null;
        $this->floods = $this->stripPolygonsFromFloods(is_array($data['floods'] ?? null) ? $data['floods'] : []);
        $this->incidents = IncidentIcon::enrichIncidents(is_array($data['incidents'] ?? null) ? $data['incidents'] : []);
        $this->forecast = is_array($data['forecast'] ?? null) ? $data['forecast'] : [];
        $this->weather = is_array($data['weather'] ?? null) ? $data['weather'] : [];
        $this->riverLevels = is_array($data['riverLevels'] ?? null) ? $data['riverLevels'] : [];
        $this->mapCenter = is_array($data['mapCenter'] ?? null) ? $data['mapCenter'] : null;
        $this->hasUserLocation = (bool) ($data['hasUserLocation'] ?? false);
        $this->lastChecked = is_string($data['lastChecked'] ?? null) ? $data['lastChecked'] : null;
    }
}
