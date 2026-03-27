<?php

namespace App\Http\Controllers;

use App\Services\DataLakeClient;
use Illuminate\Http\Request;

class FloodWatchTilesController extends Controller
{
    public function warningsTile(Request $request, int $z, int $x, int $y)
    {
        $ifNoneMatch = $request->header('If-None-Match');

        $client = new DataLakeClient;
        $resp = $client->getWarningTile($z, $x, $y, region: null, ifNoneMatch: $ifNoneMatch);

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
}
