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
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        Http::fake([
            '*api.example.com*' => Http::response([
                'D2Payload' => [
                    'situation' => [
                        [
                            'situationRecord' => [
                                [
                                    'sitRoadOrCarriagewayOrLaneManagement' => [
                                        'validity' => ['validityStatus' => 'closed'],
                                        'cause' => ['causeType' => 'environmentalObstruction', 'detailedCauseType' => ['environmentalObstructionType' => 'flooding']],
                                        'generalPublicComment' => [['comment' => 'A361 closed']],
                                        'roadOrCarriagewayOrLaneManagementType' => ['value' => 'roadClosed'],
                                        'locationReference' => [
                                            'locSingleRoadLinearLocation' => [
                                                'linearWithinLinearElement' => [
                                                    ['linearElement' => ['locLinearElementByCode' => ['roadName' => 'A361']]],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
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
