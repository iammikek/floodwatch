<?php

namespace Tests\Feature\Services;

use App\Services\RiskService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RiskServiceTest extends TestCase
{
    public function test_calculate_returns_low_risk_when_no_floods_or_incidents(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RiskService::class);
        $result = $service->calculate();

        $this->assertArrayHasKey('index', $result);
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('rawScore', $result);
        $this->assertLessThanOrEqual(100, $result['index']);
        $this->assertGreaterThanOrEqual(0, $result['index']);
        $this->assertSame('Low', $result['label']);
        $this->assertStringContainsString('No active alerts', $result['summary']);
    }

    public function test_calculate_returns_higher_risk_with_severe_flood_warning(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        $floodsFixture = file_get_contents(__DIR__.'/../../fixtures/environment_agency_floods.json');
        $areasFixture = file_get_contents(__DIR__.'/../../fixtures/environment_agency_areas.json');

        Http::fake(function ($request) use ($floodsFixture, $areasFixture) {
            if (str_contains($request->url(), '/id/floods')) {
                return Http::response($floodsFixture, 200, ['Content-Type' => 'application/json']);
            }
            if (str_contains($request->url(), '/id/floodAreas') && ! str_contains($request->url(), '/polygon')) {
                return Http::response($areasFixture, 200, ['Content-Type' => 'application/json']);
            }
            if (str_contains($request->url(), '/polygon')) {
                return Http::response(['type' => 'FeatureCollection', 'features' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RiskService::class);
        $result = $service->calculate();

        $this->assertGreaterThan(20, $result['index']);
        $this->assertStringContainsString('severe', $result['summary']);
    }

    public function test_calculate_filters_incidents_to_south_west_roads_only(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        $fixture = file_get_contents(__DIR__.'/../../fixtures/national_highways_mixed_routes.json');

        Http::fake(function ($request) use ($fixture) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response($fixture, 200, ['Content-Type' => 'application/json']);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RiskService::class);
        $result = $service->calculate();

        $this->assertStringContainsString('1 road closure', $result['summary']);
        $this->assertStringNotContainsString('2 road', $result['summary']);
    }

    public function test_calculate_label_maps_index_to_severity(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RiskService::class);
        $result = $service->calculate();

        $this->assertContains($result['label'], ['Low', 'Moderate', 'High', 'Severe']);
    }
}
