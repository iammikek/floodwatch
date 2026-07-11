<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FloodWatchRiverLevelsControllerTimeSeriesTest extends TestCase
{
    public function test_forwards_from_to_aggregate_to_data_lake(): void
    {
        Config::set('flood-watch.data_lake.base_url', 'http://lake.test');
        Config::set('flood-watch.cache_ttl_minutes', 0);

        Http::fake(function ($request) {
            $url = (string) $request->url();
            if (str_starts_with($url, 'http://lake.test/v1/measurements')) {
                $this->assertStringContainsString('aggregate=hour', $url);
                $this->assertStringContainsString('from=2026-02-01T00%3A00%3A00Z', $url);
                $this->assertStringContainsString('to=2026-02-02T00%3A00%3A00Z', $url);

                return Http::response([
                    'items' => [[
                        'station_label' => 'Gauge Z',
                        'river' => 'River Z',
                        'town' => 'Town Z',
                        'value' => 1.23,
                        'unitName' => 'm',
                        'dateTime' => '2026-02-01T12:00:00Z',
                        'lat' => 51.10,
                        'lng' => -2.80,
                        'stationType' => 'river_gauge',
                    ]],
                ], 200, ['ETag' => 'W/"abc"']);
            }

            return Http::response(null, 404);
        });

        $resp = $this->withSession(['flood_watch_loaded' => true])->getJson(route('flood-watch.river-levels', [
            'lat' => 51.0,
            'lng' => -2.8,
            'radius' => 10,
            'from' => '2026-02-01T00:00:00Z',
            'to' => '2026-02-02T00:00:00Z',
            'aggregate' => 'hour',
        ]));

        $resp->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.station', 'Gauge Z')
            ->assertJsonPath('0.river', 'River Z')
            ->assertJsonPath('0.unit', 'm')
            ->assertJsonPath('0.value', 1.23);

        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'http://lake.test/v1/measurements') && str_contains($req->url(), 'aggregate=hour'));
    }
}
