<?php

namespace Tests\Feature\Flood\Services;

use App\Flood\Services\RiverLevelService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RiverLevelServiceTest extends TestCase
{
    public function test_fetches_river_levels_for_default_coordinates(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/id/stations?') || str_contains($url, '/id/stations&')) {
                return Http::response([
                    'items' => [
                        [
                            'notation' => '52119',
                            'stationReference' => '52119',
                            'label' => 'Gaw Bridge',
                            'riverName' => 'River Parrett',
                            'town' => 'Kingsbury Episcopi',
                            'lat' => 50.976043,
                            'long' => -2.793549,
                            'stageScale' => [
                                'typicalRangeLow' => 1.5,
                                'typicalRangeHigh' => 3.5,
                            ],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($url, '/stations/52119/readings')) {
                return Http::response([
                    'items' => [
                        [
                            'value' => 2.45,
                            'dateTime' => '2026-02-04T12:00:00Z',
                            'measure' => 'http://environment.data.gov.uk/flood-monitoring/id/measures/52119-level-stage-i-15_min-mASD',
                        ],
                    ],
                ], 200);
            }

            return Http::response(null, 404);
        });

        $service = new RiverLevelService;
        $result = $service->getLevels();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Gaw Bridge', $result[0]['station']);
        $this->assertSame('River Parrett', $result[0]['river']);
        $this->assertSame('Kingsbury Episcopi', $result[0]['town']);
        $this->assertSame(2.45, $result[0]['value']);
        $this->assertSame('2026-02-04T12:00:00Z', $result[0]['dateTime']);
        $this->assertSame(50.976043, $result[0]['lat']);
        $this->assertSame(-2.793549, $result[0]['long']);
        $this->assertSame('river_gauge', $result[0]['stationType']);
        $this->assertSame('expected', $result[0]['levelStatus']);
        $this->assertSame(1.5, $result[0]['typicalRangeLow']);
        $this->assertSame(3.5, $result[0]['typicalRangeHigh']);
    }

    public function test_detects_pumping_station_and_elevated_level(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/id/stations?') || str_contains($url, '/id/stations&')) {
                return Http::response([
                    'items' => [
                        [
                            'notation' => '52345',
                            'label' => 'Huish Episcopi Pumping Station',
                            'riverName' => 'River Yeo',
                            'town' => 'Langport',
                            'lat' => 51.031,
                            'long' => -2.799,
                            'stageScale' => [
                                'typicalRangeLow' => 2.0,
                                'typicalRangeHigh' => 3.5,
                            ],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($url, '/stations/52345/readings')) {
                return Http::response([
                    'items' => [
                        [
                            'value' => 4.2,
                            'dateTime' => '2026-02-04T12:00:00Z',
                            'measure' => 'http://environment.data.gov.uk/flood-monitoring/id/measures/52345-level-stage-i-15_min-mASD',
                        ],
                    ],
                ], 200);
            }

            return Http::response(null, 404);
        });

        $service = new RiverLevelService;
        $result = $service->getLevels();

        $this->assertCount(1, $result);
        $this->assertSame('pumping_station', $result[0]['stationType']);
        $this->assertSame('elevated', $result[0]['levelStatus']);
    }

    public function test_fetches_river_levels_for_custom_coordinates(): void
    {
        Http::fake([
            '*/flood-monitoring/id/stations*' => Http::response(['items' => []], 200),
        ]);

        $service = new RiverLevelService;
        $result = $service->getLevels(51.5, -2.5, 10);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'lat=51.5')
                && str_contains($request->url(), 'long=-2.5')
                && str_contains($request->url(), 'dist=10');
        });

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_returns_empty_array_when_no_stations(): void
    {
        Http::fake([
            '*/flood-monitoring/id/stations*' => Http::response(['items' => []], 200),
        ]);

        $service = new RiverLevelService;
        $result = $service->getLevels();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_returns_empty_array_on_stations_api_failure(): void
    {
        Http::fake([
            '*/flood-monitoring/id/stations*' => Http::response(null, 500),
        ]);

        $service = new RiverLevelService;
        $result = $service->getLevels();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
