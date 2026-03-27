<?php

namespace App\Flood\Services;

use App\Flood\DTOs\FloodWarning;
use App\Services\DataLakeClient;
use App\Support\CircuitBreaker;
use App\Support\CircuitOpenException;
use App\Support\CoordinateMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnvironmentAgencyFloodService
{
    public function __construct(
        protected ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->circuitBreaker ??= new CircuitBreaker('environment_agency');
    }

    private function http(string $url, int $timeout): Response
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
        ?float $lng = null,
        ?int $radiusKm = null
    ): array {
        if (config('flood-watch.use_data_lake', false) === true) {
            return $this->fetchFloodsFromDataLake($lat, $lng, $radiusKm);
        }
        try {
            return $this->circuitBreaker->execute(function () use ($lat, $lng, $radiusKm) {
                return $this->fetchFloods($lat, $lng, $radiusKm);
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
     *
     * @deprecated Replaced by Data Lake-backed warnings when flood-watch.use_data_lake=true
     */
    private function fetchFloods(
        ?float $lat,
        ?float $lng,
        ?int $radiusKm
    ): array {
        $lat ??= config('flood-watch.default_lat');
        $lng ??= config('flood-watch.default_lng');
        $radiusKm ??= config('flood-watch.default_radius_km');

        $baseUrl = config('flood-watch.environment_agency.base_url');
        $timeout = config('flood-watch.environment_agency.timeout');
        $url = "{$baseUrl}/id/floods?lat={$lat}&long={$lng}&dist={$radiusKm}";

        try {
            $response = $this->http($url, $timeout);
            if (! $response->successful()) {
                $response->throw();
            }
        } catch (Throwable $e) {
            Log::error('FloodWatch EA floods fetch failed', [
                'provider' => 'environment_agency',
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $data = $response->json();
        $items = $data['items'] ?? [];
        $areaCentroids = $this->fetchFloodAreaCentroids($baseUrl, $timeout, $lat, $lng, $radiusKm);
        $polygons = $this->fetchFloodAreaPolygons($baseUrl, $timeout, $items);

        $result = [];
        foreach ($items as $item) {
            $areaId = $item['floodAreaID'] ?? '';
            $centroid = $areaCentroids[$areaId] ?? null;

            $coords = CoordinateMapper::normalize($centroid ?? []);
            $raw = [
                'description' => $item['description'] ?? '',
                'severity' => $item['severity'] ?? '',
                'severityLevel' => $item['severityLevel'] ?? 0,
                'message' => $item['message'] ?? '',
                'floodAreaID' => $areaId,
                'timeRaised' => $item['timeRaised'] ?? null,
                'timeMessageChanged' => $item['timeMessageChanged'] ?? null,
                'timeSeverityChanged' => $item['timeSeverityChanged'] ?? null,
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
            ];
            if (isset($polygons[$areaId])) {
                $raw['polygon'] = $polygons[$areaId];
            }

            $result[] = FloodWarning::fromArray($raw)->toArray();
        }

        return $result;
    }

    /**
     * Data Lake-backed warnings endpoint behind feature flag.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFloodsFromDataLake(
        ?float $lat,
        ?float $lng,
        ?int $radiusKm
    ): array {
        $store = config('flood-watch.cache_store');
        $ttlMinutes = (int) config('flood-watch.cache_ttl_minutes', 0);
        $lat ??= (float) config('flood-watch.default_lat');
        $lng ??= (float) config('flood-watch.default_lng');
        $radiusKm ??= (int) config('flood-watch.default_radius_km');

        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.001));
        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;
        $bbox = "{$minLng},{$minLat},{$maxLng},{$maxLat}";

        $cacheKeyPrefix = config('flood-watch.cache_key_prefix', 'flood-watch').':lake:warnings:';
        $cacheKey = "{$cacheKeyPrefix}{$bbox}";
        $cached = null;
        if ($ttlMinutes > 0) {
            $cached = Cache::store($store)->get($cacheKey);
        }
        $ifNoneMatch = is_array($cached) && isset($cached['etag']) ? (string) $cached['etag'] : null;

        $client = new DataLakeClient;
        $t0 = microtime(true);
        $resp = $client->getWarnings(bbox: $bbox, ifNoneMatch: $ifNoneMatch);
        $latencyMs = (int) round((microtime(true) - $t0) * 1000);
        if ($resp->status === 304 && is_array($cached) && isset($cached['body']) && is_array($cached['body'])) {
            $items = $cached['body']['items'] ?? [];
        } else {
            if ($resp->status !== 200 || ! is_array($resp->body)) {
                return [];
            }
            $items = $resp->body['items'] ?? [];
            if ($ttlMinutes > 0 && $resp->etag) {
                Cache::store($store)->put($cacheKey, ['etag' => $resp->etag, 'body' => $resp->body], now()->addMinutes($ttlMinutes));
            }
        }
        Log::info('FloodWatch Data Lake warnings', [
            'provider' => 'data_lake',
            'endpoint' => 'warnings',
            'bbox' => $bbox,
            'status' => $resp->status,
            'items' => is_array($items) ? count($items) : 0,
            'latency_ms' => $latencyMs,
        ]);
        if (! is_array($items) || empty($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $raw = [
                'description' => (string) ($item['description'] ?? ''),
                'severity' => (string) ($item['severity'] ?? ''),
                'severityLevel' => (int) ($item['severityLevel'] ?? ($item['severity_level'] ?? 0)),
                'message' => (string) ($item['message'] ?? ''),
                'floodAreaID' => (string) ($item['floodAreaID'] ?? ($item['area_id'] ?? '')),
                'timeRaised' => $item['timeRaised'] ?? ($item['time_raised'] ?? null),
                'timeMessageChanged' => $item['timeMessageChanged'] ?? ($item['time_message_changed'] ?? null),
                'timeSeverityChanged' => $item['timeSeverityChanged'] ?? ($item['time_severity_changed'] ?? null),
                'lat' => isset($item['lat']) ? (float) $item['lat'] : null,
                'lng' => isset($item['lng']) ? (float) $item['lng'] : null,
            ];
            if (isset($item['polygon']) && is_array($item['polygon'])) {
                $raw['polygon'] = $item['polygon'];
            }
            $result[] = FloodWarning::fromArray($raw)->toArray();
        }

        return $result;
    }

    /**
     * Fetch flood area centroids for cross-referencing with location.
     *
     * @return array<string, array{lat: float, lng: float}>
     *
     * @deprecated Replaced by Data Lake-backed warnings when flood-watch.use_data_lake=true
     */
    private function fetchFloodAreaCentroids(string $baseUrl, int $timeout, float $lat, float $lng, int $radiusKm): array
    {
        $url = "{$baseUrl}/id/floodAreas?lat={$lat}&long={$lng}&dist={$radiusKm}&_limit=200";

        try {
            $response = $this->http($url, $timeout);
            if (! $response->successful()) {
                $response->throw();
            }
        } catch (Throwable $e) {
            Log::error('FloodWatch EA centroids fetch failed', [
                'provider' => 'environment_agency',
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $data = $response->json();
        $items = $data['items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $centroids = [];
        foreach ($items as $item) {
            $notation = $item['notation'] ?? $item['fwdCode'] ?? null;
            $coords = CoordinateMapper::normalize($item);
            if ($notation !== null && $notation !== '' && $coords['lat'] !== null && $coords['lng'] !== null) {
                $centroids[(string) $notation] = $coords;
            }
        }

        return $centroids;
    }

    /**
     * Fetch flood area polygon GeoJSON for map display. Uses cache and parallel requests.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     *
     * @deprecated Replaced by Data Lake-backed warnings when flood-watch.use_data_lake=true
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

        $cache = Cache::store(config('flood-watch.cache_store'));
        $result = [];
        $toFetch = [];

        foreach ($areaIds as $areaId) {
            $cached = $cache->get("{$cacheKeyPrefix}{$areaId}");
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
            if ($response instanceof Throwable || ! $response->successful()) {
                continue;
            }
            $geojson = $response->json();
            if (is_array($geojson) && isset($geojson['type'], $geojson['features'])) {
                $result[$areaId] = $geojson;
                $cache->put("{$cacheKeyPrefix}{$areaId}", $geojson, now()->addHours($cacheHours));
            }
        }

        return $result;
    }
}
