<?php

namespace Tests\Feature\Flood\Services;

use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnvironmentAgencyFloodServiceDataLakeTest extends TestCase
{
    public function test_fetches_floods_from_data_lake_when_flag_enabled(): void
    {
        Config::set(ConfigKey::USE_DATA_LAKE, true);
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set('flood-watch.default_lat', 51.0358);
        Config::set('flood-watch.default_lng', -2.8318);
        Config::set('flood-watch.default_radius_km', 15);

        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response([
                'items' => [
                    [
                        'description' => 'River Parrett',
                        'severity' => 'Flood alert',
                        'severityLevel' => 3,
                        'message' => 'Flooding is possible.',
                        'lat' => 51.04,
                        'lng' => -2.83,
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
        $this->assertSame(51.04, $result[0]['lat']);
        $this->assertSame(-2.83, $result[0]['lng']);
    }

    public function test_built_bbox_is_sent_to_lake_endpoint(): void
    {
        Config::set(ConfigKey::USE_DATA_LAKE, true);
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');

        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response(['items' => []], 200),
        ]);

        $service = new EnvironmentAgencyFloodService;
        $service->getFloods(51.0, -2.8, 10);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/warnings')
                && $request->method() === 'GET'
                && str_contains($request->url(), 'bbox=');
        });
    }
}
