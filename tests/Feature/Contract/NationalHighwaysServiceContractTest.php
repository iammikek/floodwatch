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
        $this->assertSame('30 minutes', $result[0]['delayTime']);

        $this->assertSame('M5', $result[1]['road']);
        $this->assertSame('partial', $result[1]['status']);
        $this->assertSame('lane closure', $result[1]['incidentType']);
        $this->assertSame('15 mins', $result[1]['delayTime']);
    }
}
