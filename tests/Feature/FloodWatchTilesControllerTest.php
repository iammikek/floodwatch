<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

it('proxies warning tiles with ETag passthrough when lake enabled', function () {
    config(['flood-watch.data_lake.base_url' => 'https://lake.example.test']);

    Http::fake(function (Request $request) {
        expect($request->url())->toContain('/v1/warnings/tiles/8/129/85');
        expect($request->hasHeader('Accept'))->toBeTrue();
        expect($request->header('Accept')[0] ?? '')->toContain('application/x-protobuf');

        return Http::response('PBF_BYTES', 200, ['ETag' => 'W/"abc"']);
    });

    $response = get('/api/lake/warnings/tiles/8/129/85.pbf');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/x-protobuf');
    $response->assertHeader('ETag', 'W/"abc"');
    expect($response->getContent())->toBe('PBF_BYTES');
});

it('returns 304 when If-None-Match matches upstream ETag', function () {
    config(['flood-watch.data_lake.base_url' => 'https://lake.example.test']);

    Http::fake(function (Request $request) {
        expect($request->hasHeader('If-None-Match'))->toBeTrue();
        expect($request->header('If-None-Match')[0] ?? '')->toBe('W/"abc"');

        return Http::response('', 304, ['ETag' => 'W/"abc"']);
    });

    $response = get('/api/lake/warnings/tiles/9/258/170.pbf', ['If-None-Match' => 'W/"abc"']);
    $response->assertStatus(304);
    $response->assertHeader('ETag', 'W/"abc"');
});

it('propagates non-success status codes from upstream', function () {
    config(['flood-watch.data_lake.base_url' => 'https://lake.example.test']);

    Http::fake(function (Request $request) {
        return Http::response('', 502, ['ETag' => 'W/"zzz"']);
    });

    $response = get('/api/lake/warnings/tiles/8/129/85.pbf');
    $response->assertStatus(502);
});
