<?php

namespace App\Flood\Services;

use App\Flood\DTOs\FloodWarning;
use App\Support\CircuitBreaker;
use App\Support\CircuitOpenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EnvironmentAgencyFloodService
{
    public function __construct(
        protected ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->circuitBreaker ??= new CircuitBreaker('environment_agency');
    }

    private function http(string $url, int $timeout): \Illuminate\Http\Client\Response
    {
        $retryTimes = config('flood-watch.environment_agency.retry_times', 3);
        $retrySleep = config('flood-watch.environment_agency.retry_sleep_ms', 100);

        return Http::timeout($timeout)
            ->retry($retryTimes, $retrySleep, null, false)
            ->get($url);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFloods(
        ?float $lat = null,
        ?float $long = null,
        ?int $radiusKm = null
    ): array {
        try {
            return $this->circuitBreaker->execute(function () use ($lat, $long, $radiusKm) {
                return $this->fetchFloods($lat, $long, $radiusKm);
            });
        } catch (CircuitOpenException) {
            return [];
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchFloods(
        ?float $lat,
        ?float $long,
        ?int $radiusKm
    ): array {
        $lat ??= config('flood-watch.default_lat');
        $long ??= config('flood-watch.default_long');
        $radiusKm ??= config('flood-watch.default_radius_km');

        $baseUrl = config('flood-watch.environment_agency.base_url');
        $timeout = config('flood-watch.environment_agency.timeout');
        $url = "{$baseUrl}/id/floods?lat={$lat}&long={$long}&dist={$radiusKm}";

        $response = $this->http($url, $timeout);
        if (! $response->successful()) {
            $response->throw();
        }

        $data = $response->json();
        $items = $data['items'] ?? [];
        $areaCentroids = $this->fetchFloodAreaCentroids($baseUrl, $timeout, $lat, $long, $radiusKm);
        $polygons = $this->fetchFloodAreaPolygons($baseUrl, $timeout, $items);

        $result = [];
        foreach ($items as $item) {
            $areaId = $item['floodAreaID'] ?? '';
            $centroid = $areaCentroids[$areaId] ?? null;

            $raw = [
                'description' => $item['description'] ?? '',
                'severity' => $item['severity'] ?? '',
                'severityLevel' => $item['severityLevel'] ?? 0,
                'message' => $item['message'] ?? '',
                'floodAreaID' => $areaId,
                'timeRaised' => $item['timeRaised'] ?? null,
                'timeMessageChanged' => $item['timeMessageChanged'] ?? null,
                'timeSeverityChanged' => $item['timeSeverityChanged'] ?? null,
                'lat' => $centroid['lat'] ?? null,
                'long' => $centroid['long'] ?? null,
            ];
            if (isset($polygons[$areaId])) {
                $raw['polygon'] = $polygons[$areaId];
            }

            $result[] = FloodWarning::fromArray($raw)->toArray();
        }

        return $result;
    }

    /**
     * Fetch flood area centroids for cross-referencing with location.
     *
     * @return array<string, array{lat: float, long: float}>
     */
    private function fetchFloodAreaCentroids(string $baseUrl, int $timeout, float $lat, float $long, int $radiusKm): array
    {
        $url = "{$baseUrl}/id/floodAreas?lat={$lat}&long={$long}&dist={$radiusKm}&_limit=200";

        $response = $this->http($url, $timeout);
        if (! $response->successful()) {
            $response->throw();
        }

        $data = $response->json();
        $items = $data['items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $centroids = [];
        foreach ($items as $item) {
            $notation = $item['notation'] ?? $item['fwdCode'] ?? null;
            $itemLat = $item['lat'] ?? null;
            $itemLong = $item['long'] ?? null;
            if ($notation !== null && $notation !== '' && $itemLat !== null && $itemLong !== null) {
                $centroids[(string) $notation] = [
                    'lat' => (float) $itemLat,
                    'long' => (float) $itemLong,
                ];
            }
        }

        return $centroids;
    }

    /**
     * Fetch flood area polygon GeoJSON for map display. Uses cache and parallel requests.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function fetchFloodAreaPolygons(string $baseUrl, int $timeout, array $items): array
    {
        $maxPolygons = config('flood-watch.environment_agency.max_polygons_per_request', 10);
        $cacheHours = config('flood-watch.environment_agency.polygon_cache_hours', 168);
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
        $cacheKeyPrefix = "{$prefix}:polygon:";

        $areaIds = [];
        foreach ($items as $item) {
            $id = $item['floodAreaID'] ?? '';
            if ($id !== '' && ! in_array($id, $areaIds, true)) {
                $areaIds[] = $id;
                if (count($areaIds) >= $maxPolygons) {
                    break;
                }
            }
        }

        $result = [];
        $toFetch = [];

        foreach ($areaIds as $areaId) {
            $cached = Cache::get("{$cacheKeyPrefix}{$areaId}");
            if ($cached !== null) {
                $result[$areaId] = $cached;
            } else {
                $toFetch[] = $areaId;
            }
        }

        if (empty($toFetch)) {
            return $result;
        }

        $responses = Http::timeout($timeout)->pool(function ($pool) use ($baseUrl, $toFetch) {
            foreach ($toFetch as $areaId) {
                $pool->as($areaId)->get("{$baseUrl}/id/floodAreas/{$areaId}/polygon");
            }
        });

        foreach ($responses as $areaId => $response) {
            if ($response instanceof \Throwable || ! $response->successful()) {
                continue;
            }
            $geojson = $response->json();
            if (is_array($geojson) && isset($geojson['type'], $geojson['features'])) {
                $result[$areaId] = $geojson;
                Cache::put("{$cacheKeyPrefix}{$areaId}", $geojson, $cacheHours * 3600);
            }
        }

        return $result;
    }
}
