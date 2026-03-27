<?php

namespace App\Http\Controllers;

use App\Services\DataLakeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FloodWatchWarningsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $bbox = $request->query('bbox');
        $region = $request->query('region');
        $county = $request->query('county');
        $since = $request->query('since');
        $minSeverity = $request->query('min_severity');
        $minSeverity = is_numeric($minSeverity) ? (int) $minSeverity : null;

        if ($bbox === null && $region === null && $county === null) {
            return response()->json(['items' => []]);
        }

        $store = config('flood-watch.cache_store', 'flood-watch');
        $ttlMinutes = (int) config('flood-watch.cache_ttl_minutes', 0);
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
        $keyParts = [
            'lake',
            'warnings',
            $bbox ? "bbox:{$bbox}" : '',
            $region ? "region:{$region}" : '',
            $county ? "county:{$county}" : '',
            $since ? "since:{$since}" : '',
            $minSeverity !== null ? "min:{$minSeverity}" : '',
        ];
        $cacheKey = $prefix.':'.implode(':', array_values(array_filter($keyParts, fn ($p) => $p !== '')));
        $cached = null;
        if ($ttlMinutes > 0) {
            $cached = Cache::store($store)->get($cacheKey);
        }
        $ifNoneMatch = is_array($cached) && isset($cached['etag']) ? (string) $cached['etag'] : null;

        $client = new DataLakeClient;
        $resp = $client->getWarnings(
            bbox: $bbox ? (string) $bbox : null,
            region: $region ? (string) $region : null,
            since: $since ? (string) $since : null,
            county: $county ? (string) $county : null,
            minSeverity: $minSeverity,
            ifNoneMatch: $ifNoneMatch
        );

        if ($resp->status === 304 && is_array($cached) && isset($cached['body'])) {
            return response()->json($cached['body'], 200)
                ->header('ETag', $resp->etag ?? '')
                ->header('Cache-Control', 'public, max-age=300');
        }

        if ($resp->status === 200 && is_array($resp->body)) {
            if ($ttlMinutes > 0) {
                Cache::store($store)->put($cacheKey, ['etag' => $resp->etag, 'body' => $resp->body], $ttlMinutes * 60);
            }

            return response()->json($resp->body, 200)
                ->header('ETag', $resp->etag ?? '')
                ->header('Cache-Control', 'public, max-age=300');
        }

        return response()->json(['items' => []], $resp->status);
    }
}
