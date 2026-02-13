<?php

namespace Tests\Feature\Roads\Services;

use App\Roads\Services\NationalHighwaysService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NationalHighwaysServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::store(config('flood-watch.cache_store'))->forget(NationalHighwaysService::cacheKey());
        parent::tearDown();
    }

    public function test_get_incidents_returns_from_cache_when_populated(): void
    {
        $cached = [['road' => 'A361', 'status' => 'active', 'incidentType' => 'roadClosed', 'delayTime' => '']];
        Cache::store(config('flood-watch.cache_store'))->put(NationalHighwaysService::cacheKey(), $cached, now()->addMinutes(15));

        Config::set('flood-watch.national_highways.api_key', 'test-key');
        $service = new NationalHighwaysService;
        $result = $service->getIncidents();

        $this->assertSame($cached, $result);
        Http::assertNothingSent();
    }

    public function test_fetch_and_store_in_cache_fetches_and_stores_in_cache(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);
        Config::set('flood-watch.national_highways.cache_minutes', 15);

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
        $result = $service->fetchAndStoreInCache();

        $this->assertNotEmpty($result);
        $this->assertSame('A361', $result[0]['road']);
        $this->assertSame($result, Cache::store(config('flood-watch.cache_store'))->get(NationalHighwaysService::cacheKey()));
    }

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

    public function test_returns_empty_array_on_connection_exception(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake([
            '*api.example.com*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('network');
            },
        ]);

        $service = new NationalHighwaysService;
        $result = $service->fetchAndStoreInCache();

        $this->assertSame([], $result);
    }

    public function test_returns_empty_array_when_circuit_is_open(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        $cb = \Mockery::mock(\App\Support\CircuitBreaker::class);
        $cb->shouldReceive('execute')
            ->andThrow(new \App\Support\CircuitOpenException);

        $service = new NationalHighwaysService($cb);
        $result = $service->getIncidents();

        $this->assertSame([], $result);
    }
}
