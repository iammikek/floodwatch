<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

it('proxies polygon tiles with query passthrough and ETag', function () {
    config(['flood-watch.data_lake.base_url' => 'https://lake.example.test']);

    Http::fake(function (Request $request) {
        expect($request->url())->toContain('/v1/polygons/tiles/flood_zones/8/129/85');
        expect($request->url())->toContain('region=SOM');
        expect($request->url())->toContain('format=simplified');
        expect($request->hasHeader('Accept'))->toBeTrue();
        expect($request->header('Accept')[0] ?? '')->toContain('application/x-protobuf');

        return Http::response('PBF_POLY', 200, ['ETag' => 'W/"poly1"']);
    });

    $response = get('/api/lake/polygons/tiles/flood_zones/8/129/85.pbf?region=SOM&format=simplified');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/x-protobuf');
    $response->assertHeader('ETag', 'W/"poly1"');
    expect($response->getContent())->toBe('PBF_POLY');
});

it('returns 304 for polygon tiles when If-None-Match matches', function () {
    config(['flood-watch.data_lake.base_url' => 'https://lake.example.test']);

    Http::fake(fn (Request $request) => Http::response('', 304, ['ETag' => 'W/"poly1"']));

    $response = get('/api/lake/polygons/tiles/flood_zones/9/258/170.pbf', ['If-None-Match' => 'W/"poly1"']);
    $response->assertStatus(304);
    $response->assertHeader('ETag', 'W/"poly1"');
});

it('propagates non-success status codes for polygon tiles', function () {
    config(['flood-watch.data_lake.base_url' => 'https://lake.example.test']);

    Http::fake(fn (Request $request) => Http::response('', 502, ['ETag' => 'W/"zzz"']));

    $response = get('/api/lake/polygons/tiles/flood_zones/8/129/85.pbf?region=SOM');
    $response->assertStatus(502);
});
