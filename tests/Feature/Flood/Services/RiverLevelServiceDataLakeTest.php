<?php

namespace Tests\Feature\Flood\Services;

use App\Flood\Services\RiverLevelService;
use App\Support\ConfigKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RiverLevelServiceDataLakeTest extends TestCase
{
    public function test_fetches_river_levels_from_data_lake_when_flag_enabled(): void
    {
        Config::set(ConfigKey::USE_DATA_LAKE, true);
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set('flood-watch.default_lat', 51.0358);
        Config::set('flood-watch.default_lng', -2.8318);
        Config::set('flood-watch.default_radius_km', 15);

        Http::fake([
            'http://lake.test/v1/measurements*' => Http::response([
                'items' => [
                    [
                        'station_label' => 'Langport Gauge',
                        'river' => 'River Parrett',
                        'town' => 'Langport',
                        'value' => 2.1,
                        'unitName' => 'm',
                        'dateTime' => '2026-02-04T12:00:00Z',
                        'lat' => 51.04,
                        'lng' => -2.83,
                        'stationType' => 'river_gauge',
                        'typicalRangeLow' => 1.5,
                        'typicalRangeHigh' => 3.5,
                    ],
                ],
            ], 200),
        ]);

        $service = new RiverLevelService;
        $result = $service->getLevels();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Langport Gauge', $result[0]['station']);
        $this->assertSame('River Parrett', $result[0]['river']);
        $this->assertSame('Langport', $result[0]['town']);
        $this->assertSame(2.1, $result[0]['value']);
        $this->assertSame('m', $result[0]['unit']);
        $this->assertSame('2026-02-04T12:00:00Z', $result[0]['dateTime']);
        $this->assertSame(51.04, $result[0]['lat']);
        $this->assertSame(-2.83, $result[0]['lng']);
        $this->assertSame('river_gauge', $result[0]['stationType']);
        $this->assertSame(1.5, $result[0]['typicalRangeLow']);
        $this->assertSame(3.5, $result[0]['typicalRangeHigh']);
    }

    public function test_built_bbox_is_sent_to_lake_measurements_endpoint(): void
    {
        Config::set(ConfigKey::USE_DATA_LAKE, true);
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');

        Http::fake([
            'http://lake.test/v1/measurements*' => Http::response(['items' => []], 200),
        ]);

        $service = new RiverLevelService;
        $service->getLevels(51.0, -2.8, 10);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/measurements')
                && $request->method() === 'GET'
                && str_contains($request->url(), 'bbox=')
                && str_contains($request->url(), 'aggregate=raw');
        });
    }

    public function test_returns_empty_array_on_lake_connection_exception(): void
    {
        Config::set(ConfigKey::USE_DATA_LAKE, true);
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_starts_with($url, 'http://lake.test/v1/measurements')) {
                throw new ConnectionException('network');
            }
            if (str_contains($url, 'environment.data.gov.uk/flood-monitoring/id/stations')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($url, '/readings')) {
                return Http::response(null, 404);
            }

            return Http::response(null, 404);
        });

        $service = new RiverLevelService;
        $result = $service->getLevels(51.0, -2.8, 10);

        $this->assertSame([], $result);
    }
}
