<?php

namespace App\Services;

use App\DTOs\FloodWarning;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EnvironmentAgencyFloodService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFloods(
        ?float $lat = null,
        ?float $long = null,
        ?int $radiusKm = null
    ): array {
        $lat ??= config('flood-watch.default_lat');
        $long ??= config('flood-watch.default_long');
        $radiusKm ??= config('flood-watch.default_radius_km');

        $baseUrl = config('flood-watch.environment_agency.base_url');
        $timeout = config('flood-watch.environment_agency.timeout');
        $url = "{$baseUrl}/id/floods?lat={$lat}&long={$long}&dist={$radiusKm}";

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (ConnectionException $e) {
            report($e);

            return [];
        }

        if (! $response->successful()) {
            return [];
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

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (ConnectionException $e) {
            return [];
        }

        if (! $response->successful()) {
            return [];
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
        $cacheKeyPrefix = 'flood-area-polygon:';

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

        try {
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
        } catch (\Throwable $e) {
            report($e);
        }

        return $result;
    }
}
