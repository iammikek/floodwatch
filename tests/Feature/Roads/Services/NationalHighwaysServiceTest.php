<?php

namespace Tests\Feature\Roads\Services;

use App\Roads\Services\NationalHighwaysService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NationalHighwaysServiceTest extends TestCase
{
    public function test_returns_empty_array_when_no_api_key_configured(): void
    {
        Config::set('flood-watch.national_highways.api_key', null);

        $service = new NationalHighwaysService;
        $result = $service->getIncidents();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
        Http::assertNothingSent();
    }

    public function test_fetches_incidents_when_api_key_configured(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake([
            '*api.example.com*' => Http::response([
                'closure' => [
                    'closure' => [
                        [
                            'road' => 'A361',
                            'status' => 'closed',
                            'incidentType' => 'flooding',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new NationalHighwaysService;
        $result = $service->getIncidents();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('road', $result[0]);
        $this->assertSame('A361', $result[0]['road']);
    }

    public function test_returns_empty_array_on_api_failure(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake([
            '*api.example.com*' => Http::response(null, 500),
        ]);

        $service = new NationalHighwaysService;
        $result = $service->getIncidents();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
