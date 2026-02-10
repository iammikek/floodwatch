<?php

namespace App\Services;

use App\DTOs\RouteCheckResult;
use App\Enums\IncidentType;
use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Roads\Services\NationalHighwaysService;
use App\Support\GeoJsonBboxExtractor;
use App\Support\IncidentIcon;
use App\Support\IncidentsOnRouteFilter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class RouteCheckService
{
    public function __construct(
        protected LocationResolver $locationResolver,
        protected EnvironmentAgencyFloodService $floodService,
        protected NationalHighwaysService $highwaysService,
        protected GeoJsonBboxExtractor $bboxExtractor,
        protected IncidentsOnRouteFilter $incidentsFilter,
    ) {}

    public function check(string $from, string $to): RouteCheckResult
    {
        $fromTrimmed = trim($from);
        $toTrimmed = trim($to);

        if ($fromTrimmed === '' || $toTrimmed === '') {
            return RouteCheckResult::error(__('flood-watch.route_check.error_missing_locations'));
        }

        $fromValidation = $this->locationResolver->resolve($fromTrimmed);
        if (! $fromValidation['valid']) {
            return RouteCheckResult::error($fromValidation['error'] ?? __('flood-watch.route_check.error_invalid_from'));
        }
        if (! ($fromValidation['in_area'] ?? false)) {
            return RouteCheckResult::error(__('flood-watch.route_check.error_outside_area'));
        }

        $toValidation = $this->locationResolver->resolve($toTrimmed);
        if (! $toValidation['valid']) {
            return RouteCheckResult::error($toValidation['error'] ?? __('flood-watch.route_check.error_invalid_to'));
        }
        if (! ($toValidation['in_area'] ?? false)) {
            return RouteCheckResult::error(__('flood-watch.route_check.error_outside_area'));
        }

        $fromLat = (float) $fromValidation['lat'];
        $fromLng = (float) $fromValidation['lng'];
        $toLat = (float) $toValidation['lat'];
        $toLng = (float) $toValidation['lng'];

        $cacheKey = $this->cacheKey($fromLat, $fromLng, $toLat, $toLng);
        $ttl = config('flood-watch.route_check.cache_ttl_minutes', 15);
        $cache = Cache::store(config('flood-watch.cache_store', 'flood-watch'));

        if ($ttl > 0) {
            $cached = $this->cacheGet($cache, $cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $result = $this->fetchAndAnalyzeRoute($fromLat, $fromLng, $toLat, $toLng);
            if ($ttl > 0) {
                $this->cachePut($cache, $cacheKey, $result->toArray(), now()->addMinutes($ttl));
            }

            return $result;
        } catch (Throwable $e) {
            report($e);

            return RouteCheckResult::error(__('flood-watch.route_check.error_route_failed'));
        }
    }

    /**
     * @return array{code: string, routes?: array}
     */
    private function fetchOsrmRoute(float $fromLat, float $fromLng, float $toLat, float $toLng, bool $withAlternatives = false): array
    {
        $baseUrl = rtrim(config('flood-watch.route_check.osrm_url'), '/');
        $timeout = config('flood-watch.route_check.osrm_timeout', 15);
        $url = "{$baseUrl}/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}";
        $params = [
            'overview' => 'full',
            'geometries' => 'geojson',
        ];
        if ($withAlternatives) {
            $params['alternatives'] = 2;
            $params['steps'] = 'true';
        }

        $response = Http::timeout($timeout)->get($url, $params);

        if ($response->status() === 400 && str_contains((string) $response->body(), 'NoRoute')) {
            throw new RuntimeException('No route found');
        }

        if (! $response->successful()) {
            $response->throw();
        }

        $data = $response->json();
        if (($data['code'] ?? '') !== 'Ok') {
            throw new RuntimeException('OSRM returned: '.($data['code'] ?? 'unknown'));
        }

        return $data;
    }

    private function fetchAndAnalyzeRoute(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteCheckResult
    {
        $osrmData = $this->fetchOsrmRoute($fromLat, $fromLng, $toLat, $toLng, false);
        $routes = $osrmData['routes'] ?? [];
        $primaryRoute = $routes[0] ?? null;

        if ($primaryRoute === null) {
            throw new RuntimeException('No route returned');
        }

        $routeCoords = $this->extractRouteCoordinates($primaryRoute);
        if (count($routeCoords) < 2) {
            throw new RuntimeException('Route has no usable geometry');
        }
        $routeBbox = $this->computeBbox($routeCoords);
        $centerLat = ($routeBbox['minLat'] + $routeBbox['maxLat']) / 2;
        $centerLng = ($routeBbox['minLng'] + $routeBbox['maxLng']) / 2;
        $radiusKm = $this->computeFloodRadiusKm($routeCoords, $routeBbox);
        $proximityKm = config('flood-watch.route_check.incident_proximity_km', 0.5);

        $floods = $this->floodService->getFloods($centerLat, $centerLng, (int) ceil($radiusKm));
        $incidents = $this->highwaysService->getIncidents();

        $floodsOnRoute = $this->filterFloodsOnRoute($floods, $routeBbox, $proximityKm);
        $incidentsOnRoute = $this->incidentsFilter->filter(
            $incidents,
            $routeCoords,
            $routeBbox,
            $proximityKm,
        );

        $verdict = $this->computeVerdict($floodsOnRoute, $incidentsOnRoute);
        $summary = $this->buildSummary($verdict, $floodsOnRoute, $incidentsOnRoute);

        $alternatives = [];
        if ($verdict === 'blocked' && config('flood-watch.route_check.fetch_alternatives_when_blocked', true)) {
            $altData = $this->fetchOsrmRoute($fromLat, $fromLng, $toLat, $toLng, true);
            $altRoutes = $altData['routes'] ?? [];
            $alternatives = $this->extractAlternatives(array_slice($altRoutes, 1, 2));
        }

        $geometry = $routeCoords;
        $routeKey = md5(sprintf('%.4f,%.4f,%.4f,%.4f', $fromLat, $fromLng, $toLat, $toLng));

        return new RouteCheckResult(
            verdict: $verdict,
            summary: $summary,
            floodsOnRoute: $this->stripPolygonsFromFloods($this->enrichFloodsWithIcons($floodsOnRoute)),
            incidentsOnRoute: IncidentIcon::enrichIncidents($incidentsOnRoute),
            alternatives: $alternatives,
            routeGeometry: $geometry,
            routeKey: $routeKey,
        );
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array<int, array{0: float, 1: float}>
     */
    private function extractRouteCoordinates(array $route): array
    {
        $geometry = $route['geometry'] ?? null;
        if ($geometry === null || ! isset($geometry['coordinates'])) {
            return [];
        }
        $coords = $geometry['coordinates'];
        if (! is_array($coords)) {
            return [];
        }
        $result = [];
        foreach ($coords as $c) {
            if (is_array($c) && count($c) >= 2) {
                $result[] = [(float) $c[0], (float) $c[1]];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $coords
     * @return array{minLng: float, minLat: float, maxLng: float, maxLat: float}
     */
    private function computeBbox(array $coords): array
    {
        if (empty($coords)) {
            return ['minLng' => 0.0, 'minLat' => 0.0, 'maxLng' => 0.0, 'maxLat' => 0.0];
        }
        $lngs = array_column($coords, 0);
        $lats = array_column($coords, 1);

        return [
            'minLng' => min($lngs),
            'minLat' => min($lats),
            'maxLng' => max($lngs),
            'maxLat' => max($lats),
        ];
    }

    /**
     * Derive flood query radius from route geometry so long routes get adequate coverage.
     * Uses max distance from bbox center to any route point + buffer, clamped to config limits.
     *
     * @param  array<int, array{0: float, 1: float}>  $routeCoords  [lng, lat] pairs
     * @param  array{minLng: float, minLat: float, maxLng: float, maxLat: float}  $routeBbox
     */
    private function computeFloodRadiusKm(array $routeCoords, array $routeBbox): float
    {
        $centerLat = ($routeBbox['minLat'] + $routeBbox['maxLat']) / 2;
        $centerLng = ($routeBbox['minLng'] + $routeBbox['maxLng']) / 2;

        $maxDistKm = 0.0;
        foreach ($routeCoords as $c) {
            $lat = $c[1];
            $lng = $c[0];
            $d = $this->haversineKm($centerLat, $centerLng, $lat, $lng);
            if ($d > $maxDistKm) {
                $maxDistKm = $d;
            }
        }

        $bufferKm = config('flood-watch.route_check.flood_radius_buffer_km', 5);
        $minKm = config('flood-watch.route_check.flood_radius_km', 25);
        $maxKm = config('flood-watch.route_check.flood_radius_max_km', 80);

        $radius = $maxDistKm + $bufferKm;

        return (float) max($minKm, min($radius, $maxKm));
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Remove polygon from each flood so they are not stored in Livewire/route result; map loads polygons from cache.
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
     * @param  array<int, array<string, mixed>>  $floods
     * @param  array{minLng: float, minLat: float, maxLng: float, maxLat: float}  $routeBbox
     * @return array<int, array<string, mixed>>
     */
    private function filterFloodsOnRoute(array $floods, array $routeBbox, float $centroidBufferKm): array
    {
        $expandedBbox = $this->expandBboxKm($routeBbox, $centroidBufferKm);

        $onRoute = [];
        foreach ($floods as $flood) {
            $polygon = $flood['polygon'] ?? null;
            if (is_array($polygon)) {
                $floodBbox = $this->bboxExtractor->extractBboxFromFeatureCollection($polygon);
                if ($this->bboxOverlaps($routeBbox, $floodBbox)) {
                    $onRoute[] = $flood;
                }
            } else {
                $lat = $flood['lat'] ?? $flood['latitude'] ?? null;
                $lng = $flood['lng'] ?? $flood['long'] ?? $flood['longitude'] ?? null;
                if ($lat !== null && $lng !== null) {
                    if ($lng >= $expandedBbox['minLng'] && $lng <= $expandedBbox['maxLng']
                        && $lat >= $expandedBbox['minLat'] && $lat <= $expandedBbox['maxLat']) {
                        $onRoute[] = $flood;
                    }
                }
            }
        }

        return $onRoute;
    }

    /**
     * Expand bbox by buffer km. Handles degenerate bboxes (horizontal/vertical routes).
     *
     * @param  array{minLng: float, minLat: float, maxLng: float, maxLat: float}  $bbox
     * @return array{minLng: float, minLat: float, maxLng: float, maxLat: float}
     */
    private function expandBboxKm(array $bbox, float $bufferKm): array
    {
        $midLat = ($bbox['minLat'] + $bbox['maxLat']) / 2;
        $degPerKmLat = 1 / 111.0;
        $degPerKmLng = 1 / (111.0 * max(0.01, cos(deg2rad($midLat))));
        $dLat = $bufferKm * $degPerKmLat;
        $dLng = $bufferKm * $degPerKmLng;

        return [
            'minLng' => $bbox['minLng'] - $dLng,
            'minLat' => $bbox['minLat'] - $dLat,
            'maxLng' => $bbox['maxLng'] + $dLng,
            'maxLat' => $bbox['maxLat'] + $dLat,
        ];
    }

    /**
     * @param  array{minLng: float, minLat: float, maxLng: float, maxLat: float}  $a
     * @param  array{minLng: float, minLat: float, maxLng: float, maxLat: float}  $b
     */
    private function bboxOverlaps(array $a, array $b): bool
    {
        return ! ($a['maxLng'] < $b['minLng'] || $a['minLng'] > $b['maxLng']
            || $a['maxLat'] < $b['minLat'] || $a['minLat'] > $b['maxLat']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $floodsOnRoute
     * @param  array<int, array<string, mixed>>  $incidentsOnRoute
     */
    private function computeVerdict(array $floodsOnRoute, array $incidentsOnRoute): string
    {
        $hasBlocked = false;
        $hasDelays = false;
        foreach ($incidentsOnRoute as $inc) {
            $type = strtolower(implode(' ', array_filter([
                $inc['incidentType'] ?? '',
                $inc['managementType'] ?? '',
            ])));
            if (IncidentType::isBlockingClosure($type)) {
                $hasBlocked = true;
                break;
            }
            if (str_contains($type, 'lane') || str_contains($type, 'delay') || str_contains($type, 'construction') || str_contains($type, 'maintenance')) {
                $hasDelays = true;
            }
        }
        if ($hasBlocked) {
            return 'blocked';
        }
        if (! empty($floodsOnRoute)) {
            return 'at_risk';
        }
        if ($hasDelays || ! empty($incidentsOnRoute)) {
            return 'delays';
        }

        return 'clear';
    }

    /**
     * @param  array<int, array<string, mixed>>  $floodsOnRoute
     * @param  array<int, array<string, mixed>>  $incidentsOnRoute
     */
    private function buildSummary(string $verdict, array $floodsOnRoute, array $incidentsOnRoute): string
    {
        $params = [
            'flood_count' => count($floodsOnRoute),
            'incident_count' => count($incidentsOnRoute),
        ];

        return match ($verdict) {
            'blocked' => __('flood-watch.route_check.summary_blocked'),
            'at_risk' => __('flood-watch.route_check.summary_at_risk', $params),
            'delays' => __('flood-watch.route_check.summary_delays', $params),
            default => __('flood-watch.route_check.summary_clear'),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $alternativeRoutes
     * @return array<int, array{names: array<string>, distance: float, duration: float}>
     */
    private function extractAlternatives(array $alternativeRoutes): array
    {
        $result = [];
        foreach ($alternativeRoutes as $route) {
            $names = [];
            $legs = $route['legs'] ?? [];
            foreach ($legs as $leg) {
                $steps = $leg['steps'] ?? [];
                foreach ($steps as $step) {
                    $name = $step['name'] ?? $step['ref'] ?? null;
                    if (is_string($name) && $name !== '' && ! in_array($name, $names, true)) {
                        $names[] = $name;
                    }
                    $ref = $step['ref'] ?? null;
                    if (is_string($ref) && $ref !== '' && ! in_array($ref, $names, true)) {
                        $names[] = $ref;
                    }
                }
            }
            $distance = $route['distance'] ?? 0;
            $duration = $route['duration'] ?? 0;

            $result[] = [
                'names' => array_slice(array_filter($names), 0, 8),
                'distance' => round($distance / 1000, 1),
                'duration' => round($duration / 60),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $floods
     * @return array<int, array<string, mixed>>
     */
    private function enrichFloodsWithIcons(array $floods): array
    {
        return array_map(function (array $f): array {
            $f['icon'] = '⚠️';

            return $f;
        }, $floods);
    }

    private function cacheKey(float $fromLat, float $fromLng, float $toLat, float $toLng): string
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
        $key = sprintf('%.4f,%.4f,%.4f,%.4f', $fromLat, $fromLng, $toLat, $toLng);

        return "{$prefix}:route_check:{$key}";
    }

    /**
     * Safe cache read: returns RouteCheckResult or null on failure/corrupt/incompatible data.
     */
    private function cacheGet(\Illuminate\Contracts\Cache\Repository $cache, string $key): ?RouteCheckResult
    {
        try {
            $data = $cache->get($key);
            if ($data instanceof RouteCheckResult) {
                return $data;
            }
            if (is_array($data)) {
                $reconstructed = RouteCheckResult::fromArray($data);

                return $reconstructed ?? null;
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Safe cache write: silently skips on store failure.
     */
    private function cachePut(\Illuminate\Contracts\Cache\Repository $cache, string $key, array $value, \DateTimeInterface|int $ttl): void
    {
        try {
            $cache->put($key, $value, $ttl);
        } catch (Throwable) {
            // Silently skip cache write on Redis/serialization failure
        }
    }
}
