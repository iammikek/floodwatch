<?php

namespace Tests\Feature\Contract;

use App\Flood\Services\EnvironmentAgencyFloodService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnvironmentAgencyFloodServiceContractTest extends TestCase
{
    public function test_parses_recorded_floods_fixture_correctly(): void
    {
        config()->set('flood-watch.data_lake.base_url', 'http://lake.test');

        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response([
                'items' => [
                    [
                        'description' => 'North Moor and Curry Moor',
                        'severity' => 'Flood Warning',
                        'severityLevel' => 2,
                        'message' => 'Flooding is expected.',
                        'floodAreaID' => '123WAC',
                        'lat' => 51.04,
                        'lng' => -2.82,
                    ],
                    [
                        'description' => 'River Parrett at Langport',
                        'severity' => 'Severe Flood Warning',
                        'severityLevel' => 1,
                        'message' => 'Severe flooding is expected.',
                        'floodAreaID' => 'XYZ',
                        'lat' => 51.035,
                        'lng' => -2.831,
                    ],
                ],
            ], 200),
        ]);

        $service = new EnvironmentAgencyFloodService;
        $result = $service->getFloods(51.0358, -2.8318, 15);

        $this->assertCount(2, $result);

        $this->assertSame('North Moor and Curry Moor', $result[0]['description']);
        $this->assertSame('Flood Warning', $result[0]['severity']);
        $this->assertSame(2, $result[0]['severityLevel']);
        $this->assertSame('123WAC', $result[0]['floodAreaID']);
        $this->assertSame(51.04, $result[0]['lat']);
        $this->assertSame(-2.82, $result[0]['lng']);

        $this->assertSame('River Parrett at Langport', $result[1]['description']);
        $this->assertSame('Severe Flood Warning', $result[1]['severity']);
        $this->assertSame(1, $result[1]['severityLevel']);
        $this->assertSame(51.035, $result[1]['lat']);
        $this->assertSame(-2.831, $result[1]['lng']);
    }
}
