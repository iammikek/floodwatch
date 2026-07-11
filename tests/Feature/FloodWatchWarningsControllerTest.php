<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;

it('forwards bbox and filters to Data Lake with ETag passthrough', function () {
    config(['flood-watch.data_lake.base_url' => 'https://lake.example.test']);
    config(['flood-watch.cache_store' => 'array']);
    config(['flood-watch.cache_ttl_minutes' => 10]);
    config(['flood-watch.cache_key_prefix' => 'fw']);

    $calls = 0;
    Http::fake(function (Request $request) use (&$calls) {
        $calls++;
        expect($request->url())->toContain('/v1/warnings');
        expect($request->url())->toContain('bbox=-3.1%2C50.9%2C-2.6%2C51.2');
        expect($request->url())->toContain('region=SOM');
        expect($request->url())->toContain('county=Somerset');
        expect($request->url())->toContain('min_severity=2');

        return Http::response(['items' => [['id' => 'w1']]], 200, ['ETag' => 'W/"e1"']);
    });

    $resp = getJson('/api/lake/warnings?bbox=-3.1,50.9,-2.6,51.2&region=SOM&county=Somerset&min_severity=2');
    $resp->assertOk();
    $resp->assertHeader('ETag', 'W/"e1"');
    $resp->assertJson(['items' => [['id' => 'w1']]]);
    expect($calls)->toBe(1);

    // Second request with If-None-Match should serve cached body on 304
    Http::fake(fn () => Http::response('', 304, ['ETag' => 'W/"e1"']));
    $resp2 = getJson('/api/lake/warnings?bbox=-3.1,50.9,-2.6,51.2&region=SOM&county=Somerset&min_severity=2', ['If-None-Match' => 'W/"e1"']);
    $resp2->assertOk();
    $resp2->assertHeader('ETag', 'W/"e1"');
    $resp2->assertJson(['items' => [['id' => 'w1']]]);
});

it('returns empty items when no bbox/region/county provided', function () {
    $resp = getJson('/api/lake/warnings');
    $resp->assertOk();
    $resp->assertJson(['items' => []]);
});

it('propagates non-success codes', function () {
    config(['flood-watch.data_lake.base_url' => 'https://lake.example.test']);
    Http::fake(fn (Request $request) => Http::response([], 502));
    $resp = getJson('/api/lake/warnings?region=SOM');
    $resp->assertStatus(502);
});
