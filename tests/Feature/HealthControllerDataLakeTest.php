<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthControllerDataLakeTest extends TestCase
{
    public function test_health_includes_data_lake_ok_with_latency(): void
    {
        Config::set('flood-watch.data_lake.base_url', 'http://lake.test');
        Config::set('flood-watch.national_highways.api_key', '');

        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response(['items' => []], 200),
            'http://lake.test/v1/measurements*' => Http::response(['items' => []], 200),
            '*api.ffc-environment-agency.fgs.metoffice.gov.uk*' => Http::response(['statement' => []], 200),
            '*api.open-meteo.com*' => Http::response(['daily' => []], 200),
        ]);

        $response = $this->getJson('/health');

        // Overall may be 503 when unrelated checks are skipped (e.g. EA deprecated, NH key missing).
        $response->assertJsonPath('checks.data_lake.status', 'ok');
        $this->assertArrayHasKey('latency_ms', $response->json('checks.data_lake'));
        $this->assertIsInt($response->json('checks.data_lake.latency_ms'));
        $this->assertGreaterThanOrEqual(0, $response->json('checks.data_lake.latency_ms'));
    }

    public function test_health_reports_data_lake_degraded_when_probe_fails(): void
    {
        Config::set('flood-watch.data_lake.base_url', 'http://lake.test');
        Config::set('flood-watch.national_highways.api_key', '');

        Http::fake([
            'http://lake.test/v1/warnings*' => Http::response(['detail' => 'down'], 503),
            'http://lake.test/v1/measurements*' => Http::response(['items' => []], 200),
            '*api.ffc-environment-agency.fgs.metoffice.gov.uk*' => Http::response(['statement' => []], 200),
            '*api.open-meteo.com*' => Http::response(['daily' => []], 200),
        ]);

        $response = $this->getJson('/health');

        $response->assertStatus(503);
        $response->assertJsonPath('checks.data_lake.status', 'degraded');
        $this->assertArrayHasKey('latency_ms', $response->json('checks.data_lake'));
    }
}
