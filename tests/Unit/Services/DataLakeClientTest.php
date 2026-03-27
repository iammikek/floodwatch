<?php

namespace Tests\Unit\Services;

use App\Services\DataLakeClient;
use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataLakeClientTest extends TestCase
{
    public function test_fetch_warnings_retries_on_429_then_succeeds(): void
    {
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set(ConfigKey::DATA_LAKE.'.timeout', 5);
        Config::set(ConfigKey::DATA_LAKE.'.retry_times', 2);
        Config::set(ConfigKey::DATA_LAKE.'.retry_sleep_ms', 1);

        Http::fake([
            'http://lake.test/v1/warnings*' => Http::sequence()
                ->push([], 429)
                ->push(['items' => [['id' => 'w2']]], 200, ['ETag' => 'W/"ret"']),
        ]);

        $client = new DataLakeClient;
        $res = $client->getWarnings(region: 'SOM');

        $this->assertSame(200, $res->status);
        $this->assertSame('W/"ret"', $res->etag);
        $this->assertSame('w2', $res->body['items'][0]['id']);
    }

    public function test_fetch_warnings_200_returns_body_and_etag(): void
    {
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set(ConfigKey::DATA_LAKE.'.timeout', 5);

        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response(['items' => [['id' => 'w1']]], 200, ['ETag' => 'W/"abc"']),
        ]);

        $client = new DataLakeClient;
        $res = $client->getWarnings(region: 'SOM');

        $this->assertSame(200, $res->status);
        $this->assertSame('W/"abc"', $res->etag);
        $this->assertIsArray($res->body);
        $this->assertSame('w1', $res->body['items'][0]['id']);
    }

    public function test_fetch_warnings_304_returns_null_body_and_etag(): void
    {
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set(ConfigKey::DATA_LAKE.'.timeout', 5);

        Http::fake([
            'http://lake.test/v1/warnings*' => function ($request) {
                $ifNoneMatch = $request->header('If-None-Match');

                return Http::response(null, 304, ['ETag' => $ifNoneMatch ?? 'W/"xyz"']);
            },
        ]);

        $client = new DataLakeClient;
        $res = $client->getWarnings(region: 'SOM', ifNoneMatch: 'W/"abc"');

        $this->assertSame(304, $res->status);
        $this->assertSame('W/"abc"', $res->etag);
        $this->assertNull($res->body);
    }

    public function test_polygon_tile_200_builds_path_and_returns_body(): void
    {
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Http::fake(function ($request) {
            $this->assertTrue($request->hasHeader('Accept'));
            $this->assertStringContainsString('application/x-protobuf', $request->header('Accept')[0] ?? '');

            return Http::response('PBF_BYTES', 200, ['ETag' => 'W/"tile"']);
        });

        $client = new DataLakeClient;
        $res = $client->getPolygonTile('flood_zones', 10, 511, 340, region: 'SOM', format: 'simplified');

        $this->assertSame(200, $res->status);
        $this->assertSame('W/"tile"', $res->etag);
        $this->assertIsString($res->body);
        $this->assertSame('PBF_BYTES', $res->body);
    }
}
