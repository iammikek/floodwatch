<?php

namespace App\Http\Controllers;

use App\Services\DataLakeClient;
use Illuminate\Http\Request;

class FloodWatchTilesController extends Controller
{
    public function warningsTile(Request $request, int $z, int $x, int $y)
    {
        $ifNoneMatch = $request->header('If-None-Match');
        $region = (string) $request->query('region', '');
        $minSeverity = $request->query('min_severity');
        $region = $region !== '' ? $region : null;
        $minSeverity = is_numeric($minSeverity) ? (int) $minSeverity : null;

        $client = new DataLakeClient;
        $resp = $client->getWarningTile($z, $x, $y, region: $region, minSeverity: $minSeverity, ifNoneMatch: $ifNoneMatch);

        if ($resp->status === 304) {
            return response('', 304)
                ->header('ETag', $resp->etag ?? '')
                ->header('Cache-Control', 'public, max-age=300');
        }

        if ($resp->status === 200 && is_string($resp->body)) {
            return response($resp->body, 200)
                ->header('Content-Type', 'application/x-protobuf')
                ->header('Content-Disposition', 'inline; filename=\"warnings.pbf\"')
                ->header('ETag', $resp->etag ?? '')
                ->header('Cache-Control', 'public, max-age=300');
        }

        return response('', $resp->status);
    }

    public function polygonsTile(Request $request, string $dataset, int $z, int $x, int $y)
    {
        $ifNoneMatch = $request->header('If-None-Match');
        $region = (string) $request->query('region', '');
        $scenario = (string) $request->query('scenario', '');
        $format = (string) $request->query('format', 'simplified');

        $region = $region !== '' ? $region : 'SOM';
        $scenario = $scenario !== '' ? $scenario : null;

        $client = new DataLakeClient;
        $resp = $client->getPolygonTile(
            dataset: $dataset,
            z: $z,
            x: $x,
            y: $y,
            region: $region,
            scenario: $scenario,
            format: $format,
            ifNoneMatch: $ifNoneMatch
        );

        if ($resp->status === 304) {
            return response('', 304)
                ->header('ETag', $resp->etag ?? '')
                ->header('Cache-Control', 'public, max-age=300');
        }

        if ($resp->status === 200 && is_string($resp->body)) {
            return response($resp->body, 200)
                ->header('Content-Type', 'application/x-protobuf')
                ->header('Content-Disposition', 'inline; filename=\"polygons.pbf\"')
                ->header('ETag', $resp->etag ?? '')
                ->header('Cache-Control', 'public, max-age=300');
        }

        return response('', $resp->status);
    }
}
