<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ScrapeSomersetCouncilRoadworksJob;
use App\Roads\Services\SomersetCouncilRoadworksService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScrapeSomersetCouncilRoadworksJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::forget(SomersetCouncilRoadworksService::CACHE_KEY);
        parent::tearDown();
    }

    public function test_handle_scrapes_and_stores_incidents_in_cache(): void
    {
        Config::set('flood-watch.somerset_council.enabled', true);
        Config::set('flood-watch.somerset_council.roadworks_url', 'https://www.somerset.gov.uk/roadworks/');
        Config::set('flood-watch.somerset_council.cache_minutes', 30);

        $html = file_get_contents(__DIR__.'/../../fixtures/somerset_roadworks.html');
        Http::fake([
            '*somerset.gov.uk*' => Http::response($html, 200),
        ]);

        $job = new ScrapeSomersetCouncilRoadworksJob;
        $job->handle(app(SomersetCouncilRoadworksService::class));

        $cached = Cache::get(SomersetCouncilRoadworksService::CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertCount(2, $cached);
        $this->assertSame('A361 Main Road', $cached[0]['road']);
        $this->assertSame('flooding', $cached[0]['incidentType']);
    }

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        ScrapeSomersetCouncilRoadworksJob::dispatch();

        Queue::assertPushed(ScrapeSomersetCouncilRoadworksJob::class);
    }
}
