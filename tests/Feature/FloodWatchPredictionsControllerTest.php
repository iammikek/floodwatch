<?php

namespace Tests\Feature;

use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FloodWatchPredictionsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set(ConfigKey::DATA_LAKE.'.timeout', 5);
        Config::set(ConfigKey::DATA_LAKE.'.retry_times', 0);
        Config::set('flood-watch.predictions.enabled', true);
        Config::set('flood-watch.predictions.default_corridor', 'a361-muchelney');
    }

    public function test_predictions_requires_flood_watch_session(): void
    {
        $response = $this->getJson('/flood-watch/predictions?corridor=a361-muchelney');

        $response->assertForbidden();
    }

    public function test_predictions_returns_lake_document(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/fixtures/data_lake_predictions.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        Http::fake([
            'http://lake.test/v1/predictions*' => Http::response($fixture, 200),
        ]);

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/predictions?corridor=a361-muchelney');

        $response->assertOk()
            ->assertJsonPath('schema', 'floodwatch.prediction.v1')
            ->assertJsonPath('corridor.id', 'a361-muchelney')
            ->assertJsonPath('prediction.verdict', 'at_risk')
            ->assertJsonPath('method.name', 'historic_analogue_v1')
            ->assertJsonPath('drivers.1.type', 'historic_analogue');
    }

    public function test_predictions_returns_503_when_lake_unavailable(): void
    {
        Http::fake([
            'http://lake.test/v1/predictions*' => Http::response(['detail' => 'down'], 503),
        ]);

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/predictions?corridor=a361-muchelney');

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Prediction unavailable.');
    }

    public function test_corridors_lists_default_when_lake_unreachable(): void
    {
        Http::fake([
            'http://lake.test/v1/predictions/corridors*' => Http::response([], 500),
        ]);

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/predictions/corridors');

        $response->assertOk()
            ->assertJsonPath('corridors.0.id', 'a361-muchelney');
    }

    public function test_cockpit_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee(__('flood-watch.cockpit.title'), false)
            ->assertSee(__('flood-watch.cockpit.nav_cockpit'), false)
            ->assertSee(__('flood-watch.cockpit.nav_classic'), false);

        $this->get('/cockpit')->assertRedirect('/');
    }

    public function test_legacy_dashboard_still_available(): void
    {
        $this->get('/legacy')
            ->assertOk()
            ->assertSeeLivewire('flood-watch-dashboard');
    }
}
