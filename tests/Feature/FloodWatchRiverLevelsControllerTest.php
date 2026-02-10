<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FloodWatchRiverLevelsControllerTest extends TestCase
{
    public function test_river_levels_endpoint_returns_403_when_session_flag_missing(): void
    {
        $response = $this->getJson('/flood-watch/river-levels');

        $response->assertForbidden();
        $response->assertJson(['message' => 'Forbidden.']);
    }

    public function test_river_levels_endpoint_returns_empty_array_when_lat_lng_missing(): void
    {
        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/river-levels');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_river_levels_endpoint_returns_array_for_valid_coordinates(): void
    {
        Http::fake([
            '*environment.data.gov.uk*' => Http::response(['items' => []], 200),
        ]);

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/river-levels?lat=51.03&lng=-2.83&radius=15');

        $response->assertOk();
        $this->assertIsArray($response->json());
    }
}
