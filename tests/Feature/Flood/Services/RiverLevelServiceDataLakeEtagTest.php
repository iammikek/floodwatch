<?php

namespace Tests\Feature\Flood\Services;

use App\Flood\Services\RiverLevelService;
use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RiverLevelServiceDataLakeEtagTest extends TestCase
{
    public function test_measurements_use_etag_and_return_cached_body_on_304(): void
    {
        Config::set(ConfigKey::USE_DATA_LAKE, true);
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set('flood-watch.cache_ttl_minutes', 5);
        Config::set('flood-watch.default_lat', 51.0);
        Config::set('flood-watch.default_lng', -2.8);
        Config::set('flood-watch.default_radius_km', 10);

        Http::fake([
            'http://lake.test/v1/measurements*' => Http::sequence()
                ->push([
                    'items' => [[
                        'station_label' => 'Gauge A',
                        'river' => 'River X',
                        'town' => 'Town X',
                        'value' => 1.23,
                        'unitName' => 'm',
                        'dateTime' => '2026-02-04T12:00:00Z',
                        'lat' => 51.01,
                        'lng' => -2.79,
                        'stationType' => 'river_gauge',
                    ]],
                ], 200, ['ETag' => 'W/"m1"'])
                ->push(null, 304, ['ETag' => 'W/"m1"']),
        ]);

        $svc = new RiverLevelService;
        // First call fetches and caches
        $first = $svc->getLevels(51.0, -2.8, 10);
        $this->assertIsArray($first);
        $this->assertCount(1, $first);
        $this->assertSame('Gauge A', $first[0]['station']);

        // Second call should use If-None-Match and reuse cached body on 304
        $second = $svc->getLevels(51.0, -2.8, 10);
        $this->assertIsArray($second);
        $this->assertCount(1, $second);
        $this->assertSame('Gauge A', $second[0]['station']);
    }
}
