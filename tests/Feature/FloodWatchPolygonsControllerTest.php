<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FloodWatchPolygonsControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
        Cache::store(config('flood-watch.cache_store', 'flood-watch'))->forget("{$prefix}:polygon:area-1");
        parent::tearDown();
    }

    public function test_polygons_endpoint_returns_403_when_session_flag_missing(): void
    {
        $response = $this->getJson('/flood-watch/polygons?ids=123');

        $response->assertForbidden();
        $response->assertJson(['message' => 'Forbidden.']);
    }

    public function test_polygons_endpoint_returns_empty_object_when_no_cache(): void
    {
        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/polygons?ids=123,456');

        $response->assertOk();
        $response->assertJson([]);
    }

    public function test_polygons_endpoint_returns_cached_polygons_by_id(): void
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
        $store = config('flood-watch.cache_store', 'flood-watch');
        $geojson = ['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'geometry' => ['type' => 'Polygon', 'coordinates' => []]]]];
        Cache::store($store)->put("{$prefix}:polygon:area-1", $geojson, now()->addHour());

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/polygons?ids=area-1,area-2');

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('area-1', $data);
        $this->assertSame('FeatureCollection', $data['area-1']['type']);
        $this->assertArrayNotHasKey('area-2', $data);
    }

    public function test_polygons_endpoint_limits_number_of_ids(): void
    {
        $ids = range(1, 30);
        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/polygons?ids='.implode(',', $ids));

        $response->assertOk();
    }

    public function test_polygons_endpoint_returns_inline_geojson_when_lake_and_bbox(): void
    {
        config()->set('flood-watch.data_lake.base_url', 'http://lake.test');
        config()->set('flood-watch.cache_ttl_minutes', 5);

        Http::fake([
            'http://lake.test/v1/polygons*' => Http::response([
                'type' => 'FeatureCollection',
                'features' => [
                    ['type' => 'Feature', 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-2.83, 51.04], [-2.82, 51.04], [-2.82, 51.05], [-2.83, 51.05], [-2.83, 51.04]]]]],
                ],
            ], 200, ['ETag' => 'W/"p1"']),
        ]);

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/polygons?bbox=-2.9,51.0,-2.7,51.1&outcode=TA1');

        $response->assertOk();
        $data = $response->json();
        $this->assertSame('FeatureCollection', $data['type'] ?? null);
        $this->assertIsArray($data['features'] ?? null);
    }
}
