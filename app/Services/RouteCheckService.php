<?php

namespace App\Services;

use App\DTOs\RouteCheckResult;
use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Roads\Services\NationalHighwaysService;
use App\Support\GeoJsonBboxExtractor;
use App\Support\IncidentIcon;
use App\Support\IncidentsOnRouteFilter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            return new RouteCheckResult(
                verdict: 'error',
                summary: __('flood-watch.route_check.error_missing_locations'),
                floodsOnRoute: [],
                incidentsOnRoute: [],
                alternatives: [],
                routeGeometry: null,
            );
        }

        $fromValidation = $this->locationResolver->resolve($fromTrimmed);
        if (! $fromValidation['valid']) {
            return new RouteCheckResult(
                verdict: 'error',
                summary: $fromValidation['error'] ?? __('flood-watch.route_check.error_invalid_from'),
                floodsOnRoute: [],
                incidentsOnRoute: [],
                alternatives: [],
                routeGeometry: null,
            );
        }
        if (! ($fromValidation['in_area'] ?? false)) {
            return new RouteCheckResult(
                verdict: 'error',
                summary: __('flood-watch.route_check.error_outside_area'),
                floodsOnRoute: [],
                incidentsOnRoute: [],
                alternatives: [],
                routeGeometry: null,
            );
        }

        $toValidation = $this->locationResolver->resolve($toTrimmed);
        if (! $toValidation['valid']) {
            return new RouteCheckResult(
                verdict: 'error',
                summary: $toValidation['error'] ?? __('flood-watch.route_check.error_invalid_to'),
                floodsOnRoute: [],
                incidentsOnRoute: [],
                alternatives: [],
                routeGeometry: null,
            );
        }
        if (! ($toValidation['in_area'] ?? false)) {
            return new RouteCheckResult(
                verdict: 'error',
                summary: __('flood-watch.route_check.error_outside_area'),
                floodsOnRoute: [],
                incidentsOnRoute: [],
                alternatives: [],
                routeGeometry: null,
            );
        }

        $fromLat = (float) $fromValidation['lat'];
        $fromLng = (float) $fromValidation['lng'];
        $toLat = (float) $toValidation['lat'];
        $toLng = (float) $toValidation['lng'];

        $cacheKey = $this->cacheKey($fromLat, $fromLng, $toLat, $toLng);
        $ttl = config('flood-watch.route_check.cache_ttl_minutes', 15);
        $cache = Cache::store(config('flood-watch.cache_store', 'flood-watch'));

        if ($ttl > 0) {
            $cached = $cache->get($cacheKey);
            if ($cached !== null && $cached instanceof RouteCheckResult) {
                return $cached;
            }
        }

        try {
            $result = $this->fetchAndAnalyzeRoute($fromLat, $fromLng, $toLat, $toLng);
            if ($ttl > 0) {
                $cache->put($cacheKey, $result, now()->addMinutes($ttl));
            }

            return $result;
        } catch (Throwable $e) {
            report($e);

            return new RouteCheckResult(
                verdict: 'error',
                summary: __('flood-watch.route_check.error_route_failed'),
                floodsOnRoute: [],
                incidentsOnRoute: [],
                alternatives: [],
                routeGeometry: null,
            );
        }
    }

    /**
     * @return array{code: string, routes?: array}
     */
    private function fetchOsrmRoute(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $baseUrl = rtrim(config('flood-watch.route_check.osrm_url'), '/');
        $timeout = config('flood-watch.route_check.osrm_timeout', 15);
        $url = "{$baseUrl}/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}";
        $params = [
            'overview' => 'full',
            'geometries' => 'geojson',
            'alternatives' => 2,
            'steps' => 'true',
        ];

        $response = Http::timeout($timeout)->get($url, $params);

        if ($response->status() === 400 && str_contains((string) $response->body(), 'NoRoute')) {
            throw new \RuntimeException('No route found');
        }

        if (! $response->successful()) {
            $response->throw();
        }

        $data = $response->json();
        if (($data['code'] ?? '') !== 'Ok') {
            throw new \RuntimeException('OSRM returned: '.($data['code'] ?? 'unknown'));
        }

        return $data;
    }

    private function fetchAndAnalyzeRoute(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteCheckResult
    {
        $osrmData = $this->fetchOsrmRoute($fromLat, $fromLng, $toLat, $toLng);
        $routes = $osrmData['routes'] ?? [];
        $primaryRoute = $routes[0] ?? null;

        if ($primaryRoute === null) {
            throw new \RuntimeException('No route returned');
        }

        $routeCoords = $this->extractRouteCoordinates($primaryRoute);
        if (count($routeCoords) < 2) {
            throw new \RuntimeException('Route has no usable geometry');
        }
        $routeBbox = $this->computeBbox($routeCoords);
        $midLat = ($fromLat + $toLat) / 2;
        $midLng = ($fromLng + $toLng) / 2;
        $radiusKm = config('flood-watch.route_check.flood_radius_km', 25);
        $proximityKm = config('flood-watch.route_check.incident_proximity_km', 0.5);

        $floods = $this->floodService->getFloods($midLat, $midLng, $radiusKm);
        $incidents = $this->highwaysService->getIncidents();

        $floodsOnRoute = $this->filterFloodsOnRoute($floods, $routeBbox);
        $incidentsOnRoute = $this->incidentsFilter->filter(
            $incidents,
            $routeCoords,
            $routeBbox,
            $proximityKm,
        );

        $verdict = $this->computeVerdict($floodsOnRoute, $incidentsOnRoute);
        $summary = $this->buildSummary($verdict, $floodsOnRoute, $incidentsOnRoute);
        $alternatives = $this->extractAlternatives(array_slice($routes, 1, 2));

        $geometry = $routeCoords;

        return new RouteCheckResult(
            verdict: $verdict,
            summary: $summary,
            floodsOnRoute: $this->enrichFloodsWithIcons($floodsOnRoute),
            incidentsOnRoute: $this->enrichIncidentsWithIcons($incidentsOnRoute),
            alternatives: $alternatives,
            routeGeometry: $geometry,
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
     * @param  array<int, array<string, mixed>>  $floods
     * @param  array{minLng: float, minLat: float, maxLng: float, maxLat: float}  $routeBbox
     * @return array<int, array<string, mixed>>
     */
    private function filterFloodsOnRoute(array $floods, array $routeBbox): array
    {
        $onRoute = [];
        foreach ($floods as $flood) {
            $polygon = $flood['polygon'] ?? null;
            if ($polygon !== null && is_array($polygon)) {
                $floodBbox = $this->bboxExtractor->extractBboxFromFeatureCollection($polygon);
                if ($this->bboxOverlaps($routeBbox, $floodBbox)) {
                    $onRoute[] = $flood;
                }
            } else {
                $lat = $flood['lat'] ?? $flood['latitude'] ?? null;
                $lng = $flood['lng'] ?? $flood['long'] ?? $flood['longitude'] ?? null;
                if ($lat !== null && $lng !== null) {
                    if ($lng >= $routeBbox['minLng'] && $lng <= $routeBbox['maxLng']
                        && $lat >= $routeBbox['minLat'] && $lat <= $routeBbox['maxLat']) {
                        $onRoute[] = $flood;
                    }
                }
            }
        }

        return $onRoute;
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
            $type = strtolower($inc['incidentType'] ?? $inc['managementType'] ?? '');
            if (str_contains($type, 'roadclosed') || str_contains($type, 'closure')) {
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

    /**
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<int, array<string, mixed>>
     */
    private function enrichIncidentsWithIcons(array $incidents): array
    {
        return array_map(function (array $inc): array {
            $inc['icon'] = IncidentIcon::forIncident(
                $inc['incidentType'] ?? null,
                $inc['managementType'] ?? null
            );
            $inc['statusLabel'] = IncidentIcon::statusLabel($inc['status'] ?? null);
            $inc['typeLabel'] = IncidentIcon::typeLabel($inc['incidentType'] ?? $inc['managementType'] ?? null);

            return $inc;
        }, $incidents);
    }

    private function cacheKey(float $fromLat, float $fromLng, float $toLat, float $toLng): string
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
        $key = sprintf('%.4f,%.4f,%.4f,%.4f', $fromLat, $fromLng, $toLat, $toLng);

        return "{$prefix}:route_check:{$key}";
    }
}
