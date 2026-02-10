<?php

namespace Tests\Feature\Roads\Services;

use App\Roads\Services\SomersetCouncilRoadworksService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SomersetCouncilRoadworksServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::forget(SomersetCouncilRoadworksService::CACHE_KEY);
        parent::tearDown();
    }

    public function test_get_incidents_returns_empty_when_cache_empty(): void
    {
        Config::set('flood-watch.somerset_council.enabled', true);

        $service = new SomersetCouncilRoadworksService;
        $result = $service->getIncidents();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_incidents_returns_from_cache_when_populated(): void
    {
        Config::set('flood-watch.somerset_council.enabled', true);
        $cached = [
            ['road' => 'A361', 'status' => 'active', 'incidentType' => 'flooding', 'delayTime' => 'Closed due to flooding'],
        ];
        Cache::put(SomersetCouncilRoadworksService::CACHE_KEY, $cached, now()->addMinutes(30));

        $service = new SomersetCouncilRoadworksService;
        $result = $service->getIncidents();

        $this->assertSame($cached, $result);
        Http::assertNothingSent();
    }

    public function test_get_incidents_returns_empty_when_disabled(): void
    {
        Config::set('flood-watch.somerset_council.enabled', false);
        Cache::put(SomersetCouncilRoadworksService::CACHE_KEY, [['road' => 'A361']], now()->addMinutes(30));

        $service = new SomersetCouncilRoadworksService;
        $result = $service->getIncidents();

        $this->assertSame([], $result);
    }

    public function test_scrape_and_store_in_cache_fetches_parses_and_stores_incidents(): void
    {
        Config::set('flood-watch.somerset_council.enabled', true);
        Config::set('flood-watch.somerset_council.roadworks_url', 'https://www.somerset.gov.uk/roadworks/');
        Config::set('flood-watch.somerset_council.timeout', 15);
        Config::set('flood-watch.somerset_council.cache_minutes', 30);

        $html = file_get_contents(__DIR__.'/../../../fixtures/somerset_roadworks.html');
        Http::fake([
            '*somerset.gov.uk*' => Http::response($html, 200),
        ]);

        $service = new SomersetCouncilRoadworksService;
        $service->scrapeAndStoreInCache();

        $cached = Cache::get(SomersetCouncilRoadworksService::CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertCount(2, $cached);

        $this->assertSame('A361 Main Road', $cached[0]['road']);
        $this->assertSame('active', $cached[0]['status']);
        $this->assertSame('flooding', $cached[0]['incidentType']);
        $this->assertStringContainsString('closed due to flooding', $cached[0]['delayTime']);

        $this->assertSame('M5', $cached[1]['road']);
        $this->assertSame('roadClosed', $cached[1]['incidentType']);
    }

    public function test_scrape_and_store_in_cache_stores_empty_array_on_fetch_failure(): void
    {
        Config::set('flood-watch.somerset_council.enabled', true);
        Config::set('flood-watch.somerset_council.roadworks_url', 'https://www.somerset.gov.uk/roadworks/');

        Http::fake([
            '*somerset.gov.uk*' => Http::response(null, 500),
        ]);

        $service = new SomersetCouncilRoadworksService;
        $service->scrapeAndStoreInCache();

        $cached = Cache::get(SomersetCouncilRoadworksService::CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertEmpty($cached);
    }
}
