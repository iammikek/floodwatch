<?php

namespace Tests\Feature;

use App\DTOs\RouteCheckResult;
use App\Services\RouteCheckService;
use Mockery;
use Tests\TestCase;

class FloodWatchRouteCheckControllerTest extends TestCase
{
    public function test_route_check_requires_flood_watch_session(): void
    {
        $response = $this->getJson('/flood-watch/route-check?from=Muchelney&to=Bridgwater');

        $response->assertForbidden();
    }

    public function test_route_check_returns_service_payload(): void
    {
        $svc = Mockery::mock(RouteCheckService::class);
        $svc->shouldReceive('check')
            ->once()
            ->with('Muchelney, Somerset', 'Bridgwater, Somerset')
            ->andReturn(new RouteCheckResult(
                verdict: 'at_risk',
                summary: 'Flood warning near corridor.',
                floodsOnRoute: [],
                incidentsOnRoute: [],
                alternatives: [],
                routeGeometry: [[-2.82, 51.12], [-2.80, 51.14]],
                routeKey: 'abc123',
            ));
        $this->app->instance(RouteCheckService::class, $svc);

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/route-check?from=Muchelney,%20Somerset&to=Bridgwater,%20Somerset');

        $response->assertOk();
        $response->assertJsonPath('verdict', 'at_risk');
        $response->assertJsonPath('verdict_label', 'At risk');
        $response->assertJsonPath('route_geometry.0.0', -2.82);
        $response->assertJsonPath('from', 'Muchelney, Somerset');
    }
}
