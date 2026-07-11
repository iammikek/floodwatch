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

    public function test_river_levels_endpoint_returns_cached_response_on_second_request(): void
    {
        $this->app['config']->set('flood-watch.river_levels_cache_minutes', 15);

        $requestCount = 0;
        Http::fake(function ($request) use (&$requestCount) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                $requestCount++;

                return Http::response(['items' => []], 200);
            }

            return Http::response(null, 404);
        });

        $session = ['flood_watch_loaded' => true];
        $url = '/flood-watch/river-levels?lat=51.02&lng=-2.84&radius=20';

        $response1 = $this->withSession($session)->getJson($url);
        $response1->assertOk();

        $response2 = $this->withSession($session)->getJson($url);
        $response2->assertOk();

        $this->assertSame($response1->json(), $response2->json());
        $this->assertSame(1, $requestCount, 'Second request should be served from cache without calling EA API');
    }
}
