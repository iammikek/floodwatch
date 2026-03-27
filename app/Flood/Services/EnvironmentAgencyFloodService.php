<?php

namespace App\Flood\Services;

use App\Flood\DTOs\FloodWarning;
use App\Services\DataLakeClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnvironmentAgencyFloodService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFloods(
        ?float $lat = null,
        ?float $lng = null,
        ?int $radiusKm = null
    ): array {
        try {
            return $this->fetchFloodsFromDataLake($lat, $lng, $radiusKm);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
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
}
