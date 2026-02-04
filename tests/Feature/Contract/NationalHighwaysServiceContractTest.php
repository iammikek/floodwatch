<?php

namespace Tests\Feature\Contract;

use App\Roads\Services\NationalHighwaysService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contract tests using recorded API response fixtures.
 * Verifies parsing logic matches the National Highways API contract.
 */
class NationalHighwaysServiceContractTest extends TestCase
{
    public function test_parses_recorded_closures_fixture_correctly(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        $fixture = file_get_contents(__DIR__.'/../../fixtures/national_highways_closures.json');

        Http::fake([
            '*api.example.com*' => Http::response($fixture, 200, ['Content-Type' => 'application/json']),
        ]);

        $service = new NationalHighwaysService;
        $result = $service->getIncidents();

        $this->assertCount(2, $result);

        $this->assertSame('A361', $result[0]['road']);
        $this->assertSame('closed', $result[0]['status']);
        $this->assertSame('flooding', $result[0]['incidentType']);
        $this->assertSame('A361 closed due to flooding - 30 minutes delay', $result[0]['delayTime']);
        $this->assertArrayHasKey('lat', $result[0]);
        $this->assertArrayHasKey('long', $result[0]);
        $this->assertEqualsWithDelta(51.04, $result[0]['lat'], 0.01);
        $this->assertEqualsWithDelta(-2.83, $result[0]['long'], 0.01);
        $this->assertSame('2025-03-14T08:00:00.0000000+00:00', $result[0]['startTime']);
        $this->assertSame('2025-03-15T06:00:00.0000000+00:00', $result[0]['endTime']);
        $this->assertSame('roadClosed', $result[0]['managementType']);
        $this->assertTrue($result[0]['isFloodRelated'] ?? false);

        $this->assertSame('M5', $result[1]['road']);
        $this->assertSame('active', $result[1]['status']);
        $this->assertSame('laneClosures', $result[1]['incidentType']);
        $this->assertSame('15 mins delay', $result[1]['delayTime']);
        $this->assertArrayHasKey('lat', $result[1]);
        $this->assertArrayHasKey('long', $result[1]);
        $this->assertEqualsWithDelta(52.77, $result[1]['lat'], 0.01);
        $this->assertEqualsWithDelta(-2.11, $result[1]['long'], 0.01);
        $this->assertSame('2025-03-21T06:01:57.0000000+00:00', $result[1]['startTime']);
        $this->assertSame('2025-03-21T06:16:57.0000000+00:00', $result[1]['endTime']);
        $this->assertSame('M5 southbound between J14 and J13', $result[1]['locationDescription']);
        $this->assertSame('laneClosures', $result[1]['managementType']);
    }
}
