<?php

namespace Tests\Feature\Flood\Services;

use App\Flood\Services\RiverLevelService;
use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RiverLevelServiceTimeSeriesDataLakeTest extends TestCase
{
    public function test_timeseries_hourly_with_from_to_is_requested_and_parsed(): void
    {
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set('flood-watch.cache_ttl_minutes', 5);

        Http::fake([
            'http://lake.test/v1/measurements*' => function ($request) {
                $this->assertStringContainsString('aggregate=hour', (string) $request->url());
                $this->assertStringContainsString('from=2026-02-01T00%3A00%3A00Z', (string) $request->url());
                $this->assertStringContainsString('to=2026-02-02T00%3A00%3A00Z', (string) $request->url());

                return Http::response([
                    'items' => [[
                        'station_label' => 'Gauge B',
                        'river' => 'River Y',
                        'town' => 'Town Y',
                        'value' => 0.87,
                        'unitName' => 'm',
                        'dateTime' => '2026-02-01T12:00:00Z',
                        'lat' => 51.12,
                        'lng' => -2.75,
                        'stationType' => 'river_gauge',
                    ]],
                ], 200, ['ETag' => 'W/"ts1"']);
            },
        ]);

        $svc = new RiverLevelService;
        $rows = $svc->getLevels(51.0, -2.8, 10, '2026-02-01T00:00:00Z', '2026-02-02T00:00:00Z', 'hour');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertSame('Gauge B', $rows[0]['station']);
        $this->assertSame('m', $rows[0]['unit']);
    }
}
