<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FloodWatchPolygonsController extends Controller
{
    private const MAX_IDS = 20;

    /**
     * Return polygon GeoJSON for the given flood area IDs from cache.
     * Polygons are cached by EnvironmentAgencyFloodService when flood data is fetched.
     */
    public function __invoke(Request $request): JsonResponse
    {
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
}
