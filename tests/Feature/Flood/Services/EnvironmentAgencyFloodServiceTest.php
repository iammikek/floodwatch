<?php

namespace Tests\Feature\Flood\Services;

use App\Flood\Services\EnvironmentAgencyFloodService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnvironmentAgencyFloodServiceTest extends TestCase
{
    public function test_fetches_floods_for_default_coordinates(): void
    {
        config()->set('flood-watch.data_lake.base_url', 'http://lake.test');
        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response([
                'items' => [
                    [
                        'description' => 'River Parrett',
                        'severity' => 'Flood alert',
                        'severityLevel' => 3,
                        'message' => 'Flooding is possible.',
                    ],
                ],
            ], 200),
        ]);

        $service = new EnvironmentAgencyFloodService;
        $result = $service->getFloods();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('River Parrett', $result[0]['description']);
        $this->assertSame('Flood alert', $result[0]['severity']);
        $this->assertSame(3, $result[0]['severityLevel']);
    }

    public function test_fetches_floods_for_custom_coordinates(): void
    {
        config()->set('flood-watch.data_lake.base_url', 'http://lake.test');
        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response(['items' => []], 200),
        ]);

        $service = new EnvironmentAgencyFloodService;
        $result = $service->getFloods(51.5, -2.5, 10);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/warnings'));

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_returns_empty_array_on_api_failure(): void
    {
        config()->set('flood-watch.data_lake.base_url', 'http://lake.test');
        Http::fake(['http://lake.test/v1/warnings*' => Http::response(null, 500)]);

        $service = new EnvironmentAgencyFloodService;
        $result = $service->getFloods();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_includes_polygon_geojson_when_flood_area_has_polygon_endpoint(): void
    {
        config()->set('flood-watch.data_lake.base_url', 'http://lake.test');
        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response([
                'items' => [
                    [
                        'description' => 'Test Flood Area',
                        'severity' => 'Flood Warning',
                        'severityLevel' => 2,
                        'floodAreaID' => '123WAC',
                        'message' => 'Flooding expected.',
                        'polygon' => [
                            'type' => 'FeatureCollection',
                            'features' => [
                                [
                                    'type' => 'Feature',
                                    'geometry' => [
                                        'type' => 'Polygon',
                                        'coordinates' => [[[-2.83, 51.04], [-2.82, 51.04], [-2.82, 51.05], [-2.83, 51.05], [-2.83, 51.04]]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new EnvironmentAgencyFloodService;
        $result = $service->getFloods();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('polygon', $result[0]);
        $this->assertSame('FeatureCollection', $result[0]['polygon']['type']);
        $this->assertArrayHasKey('features', $result[0]['polygon']);
    }
}
