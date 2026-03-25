<?php

namespace Tests\Unit\Services;

use App\Services\DataLakeClient;
use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataLakeClientTest extends TestCase
{
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
        Http::fake([
            'http://lake.test/v1/polygons/tiles/flood_zones/10/511/340*' => Http::response(['type' => 'FeatureCollection', 'features' => []], 200),
        ]);

        $client = new DataLakeClient;
        $res = $client->getPolygonTile('flood_zones', 10, 511, 340, region: 'SOM', format: 'simplified');

        $this->assertSame(200, $res->status);
        $this->assertIsArray($res->body);
        $this->assertSame('FeatureCollection', $res->body['type']);
    }
}
