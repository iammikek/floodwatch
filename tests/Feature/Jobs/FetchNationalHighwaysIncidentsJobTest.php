<?php

namespace Tests\Feature\Jobs;

use App\Jobs\FetchNationalHighwaysIncidentsJob;
use App\Roads\Services\NationalHighwaysService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FetchNationalHighwaysIncidentsJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::forget(NationalHighwaysService::CACHE_KEY);
        parent::tearDown();
    }

    public function test_handle_fetches_and_stores_incidents_in_cache(): void
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

        $job = new FetchNationalHighwaysIncidentsJob;
        $job->handle(app(NationalHighwaysService::class));

        $cached = Cache::get(NationalHighwaysService::CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertNotEmpty($cached);
        $this->assertSame('A361', $cached[0]['road']);
    }

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        FetchNationalHighwaysIncidentsJob::dispatch();

        Queue::assertPushed(FetchNationalHighwaysIncidentsJob::class);
    }
}
