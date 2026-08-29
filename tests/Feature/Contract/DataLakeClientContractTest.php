<?php

namespace Tests\Feature\Contract;

use App\Services\DataLakeClient;
use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks the Laravel ↔ data lake response shapes consumed by services/controllers.
 * Fixtures are synthetic; they mirror the payloads Laravel already maps.
 */
class DataLakeClientContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set(ConfigKey::DATA_LAKE.'.timeout', 5);
        Config::set(ConfigKey::DATA_LAKE.'.retry_times', 0);
    }

    public function test_warnings_response_shape_is_locked(): void
    {
        $fixture = $this->loadFixture('data_lake_warnings.json');

        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response($fixture, 200, ['ETag' => 'W/"warn-1"']),
        ]);

        $res = (new DataLakeClient)->getWarnings(bbox: '-3.1,50.9,-2.6,51.2');

        $this->assertSame(200, $res->status);
        $this->assertSame('W/"warn-1"', $res->etag);
        $this->assertIsArray($res->body);
        $this->assertArrayHasKey('items', $res->body);
        $this->assertNotEmpty($res->body['items']);

        $item = $res->body['items'][0];
        foreach (['description', 'severity', 'severityLevel', 'message', 'floodAreaID', 'lat', 'lng'] as $key) {
            $this->assertArrayHasKey($key, $item, "warnings item missing {$key}");
        }
        $this->assertIsString($item['description']);
        $this->assertIsString($item['severity']);
        $this->assertIsNumeric($item['severityLevel']);
        $this->assertIsNumeric($item['lat']);
        $this->assertIsNumeric($item['lng']);
    }

    public function test_measurements_response_shape_is_locked(): void
    {
        $fixture = $this->loadFixture('data_lake_measurements.json');

        Http::fake([
            'http://lake.test/v1/measurements*' => Http::response($fixture, 200, ['ETag' => 'W/"meas-1"']),
        ]);

        $res = (new DataLakeClient)->getMeasurements(
            bbox: '-3.1,50.9,-2.6,51.2',
            aggregate: 'raw',
            limit: 10
        );

        $this->assertSame(200, $res->status);
        $this->assertSame('W/"meas-1"', $res->etag);
        $this->assertIsArray($res->body);
        $this->assertArrayHasKey('items', $res->body);
        $this->assertNotEmpty($res->body['items']);

        $item = $res->body['items'][0];
        foreach (['value', 'dateTime', 'lat', 'lng', 'station_label', 'unitName'] as $key) {
            $this->assertArrayHasKey($key, $item, "measurements item missing {$key}");
        }
        $this->assertIsNumeric($item['value']);
        $this->assertIsString($item['dateTime']);
        $this->assertIsNumeric($item['lat']);
        $this->assertIsNumeric($item['lng']);
    }

    public function test_polygons_inline_response_shape_is_locked(): void
    {
        $fixture = $this->loadFixture('data_lake_polygons.json');

        Http::fake([
            'http://lake.test/v1/polygons*' => Http::response($fixture, 200, ['ETag' => 'W/"poly-1"']),
        ]);

        $res = (new DataLakeClient)->getPolygons(
            dataset: 'flood_zones',
            region: 'SOM',
            format: 'simplified',
            inline: true,
            bbox: '-3.1,50.9,-2.6,51.2'
        );

        $this->assertSame(200, $res->status);
        $this->assertSame('W/"poly-1"', $res->etag);
        $this->assertIsArray($res->body);
        $this->assertSame('FeatureCollection', $res->body['type']);
        $this->assertArrayHasKey('features', $res->body);
        $this->assertNotEmpty($res->body['features']);

        $feature = $res->body['features'][0];
        $this->assertSame('Feature', $feature['type']);
        $this->assertArrayHasKey('geometry', $feature);
        $this->assertArrayHasKey('type', $feature['geometry']);
        $this->assertArrayHasKey('coordinates', $feature['geometry']);
        $this->assertArrayHasKey('properties', $feature);
    }

    public function test_predictions_response_shape_is_locked(): void
    {
        $fixture = $this->loadFixture('data_lake_predictions.json');

        Http::fake([
            'http://lake.test/v1/predictions*' => Http::response($fixture, 200, ['ETag' => 'W/"pred-1"']),
        ]);

        $res = (new DataLakeClient)->getPredictions(corridor: 'a361-muchelney');

        $this->assertSame(200, $res->status);
        $this->assertSame('W/"pred-1"', $res->etag);
        $this->assertIsArray($res->body);
        $this->assertSame('floodwatch.prediction.v0', $res->body['schema']);
        $this->assertArrayHasKey('prediction', $res->body);
        $this->assertArrayHasKey('dispatch', $res->body);
        $this->assertArrayHasKey('method', $res->body);
        foreach (['verdict', 'verdictLabel', 'summary', 'confidence'] as $key) {
            $this->assertArrayHasKey($key, $res->body['prediction'], "prediction missing {$key}");
        }
        $this->assertArrayHasKey('safeToPass', $res->body['dispatch']);
        $this->assertArrayHasKey('name', $res->body['method']);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(string $name): array
    {
        $path = base_path('tests/fixtures/'.$name);
        $json = file_get_contents($path);
        $this->assertNotFalse($json, "Missing fixture {$name}");

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
