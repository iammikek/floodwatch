<?php

namespace App\Http\Controllers;

use App\Services\DataLakeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FloodWatchPolygonsController extends Controller
{
    private const MAX_IDS = 20;

    /**
     * Return polygon GeoJSON for the given flood area IDs from cache.
     * Polygons are cached by EnvironmentAgencyFloodService when flood data is fetched.
     * Rate-limited via throttle:flood-watch-api (see routes).
     */
    public function __invoke(Request $request): JsonResponse
    {
        if ((bool) config('flood-watch.use_data_lake', false) && $request->filled('bbox')) {
            $bbox = (string) $request->query('bbox');
            $outcode = (string) $request->query('outcode', '');
            $region = $this->mapOutcodeToRegion($outcode);
            $dataset = 'flood_zones';
            $format = 'simplified';
            $store = config('flood-watch.cache_store', 'flood-watch');
            $ttlMinutes = (int) config('flood-watch.cache_ttl_minutes', 0);
            $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
            $cacheKey = "{$prefix}:lake:polygons:inline:{$dataset}:{$region}:{$format}:{$bbox}";
            $cached = null;
            if ($ttlMinutes > 0) {
                $cached = Cache::store($store)->get($cacheKey);
            }
            $ifNoneMatch = is_array($cached) && isset($cached['etag']) ? (string) $cached['etag'] : null;

            try {
                $client = new DataLakeClient;
                $t0 = microtime(true);
                $resp = $client->getPolygons(dataset: $dataset, region: $region, format: $format, inline: true, bbox: $bbox, ifNoneMatch: $ifNoneMatch);
                $latencyMs = (int) round((microtime(true) - $t0) * 1000);
            } catch (\Throwable $e) {
                Log::error('FloodWatch Data Lake inline polygons fetch failed', [
                    'provider' => 'data_lake',
                    'bbox' => $bbox,
                    'region' => $region,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([]);
            }

            if ($resp->status === 304 && is_array($cached) && isset($cached['body'])) {
                $geo = $cached['body'];
                Log::info('FloodWatch Data Lake polygons (cached)', [
                    'provider' => 'data_lake',
                    'endpoint' => 'polygons',
                    'mode' => 'inline',
                    'dataset' => $dataset,
                    'region' => $region,
                    'bbox' => $bbox,
                    'status' => 304,
                    'latency_ms' => $latencyMs,
                ]);

                return response()->json($geo);
            }

            if ($resp->status !== 200 || ! is_array($resp->body)) {
                return response()->json([]);
            }
            $geo = $resp->body;
            if ($ttlMinutes > 0 && $resp->etag) {
                Cache::store($store)->put($cacheKey, ['etag' => $resp->etag, 'body' => $geo], now()->addMinutes($ttlMinutes));
            }
            Log::info('FloodWatch Data Lake polygons', [
                'provider' => 'data_lake',
                'endpoint' => 'polygons',
                'mode' => 'inline',
                'dataset' => $dataset,
                'region' => $region,
                'bbox' => $bbox,
                'status' => $resp->status,
                'features' => is_array($geo['features'] ?? null) ? count($geo['features']) : 0,
                'latency_ms' => $latencyMs,
            ]);

            return response()->json($geo);
        }

        $idsParam = $request->query('ids', '');
        $ids = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $idsParam)),
            fn (string $id) => $id !== ''
        )));
        $ids = array_slice($ids, 0, self::MAX_IDS);

        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
        $cacheKeyPrefix = "{$prefix}:polygon:";
        $cache = Cache::store(config('flood-watch.cache_store'));

        $polygons = [];
        foreach ($ids as $areaId) {
            $cached = $cache->get("{$cacheKeyPrefix}{$areaId}");
            if (is_array($cached) && isset($cached['type'], $cached['features'])) {
                $polygons[$areaId] = $cached;
            }
        }

        return response()->json($polygons);
    }

    private function mapOutcodeToRegion(string $outcode): string
    {
        $o = strtoupper($outcode);
        if ($o === '') {
            return 'SOM';
        }
        if (str_starts_with($o, 'TA') || str_starts_with($o, 'BA')) {
            return 'SOM';
        }
        if (str_starts_with($o, 'BS')) {
            return 'BRI';
        }
        if (str_starts_with($o, 'EX') || str_starts_with($o, 'TQ') || str_starts_with($o, 'PL')) {
            return 'DEV';
        }
        if (str_starts_with($o, 'TR')) {
            return 'CON';
        }

        return 'SOM';
    }
}
