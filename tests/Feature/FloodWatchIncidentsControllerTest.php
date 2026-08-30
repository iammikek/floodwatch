<?php

namespace Tests\Feature;

use App\Roads\Services\RoadIncidentOrchestrator;
use Mockery;
use Tests\TestCase;

class FloodWatchIncidentsControllerTest extends TestCase
{
    public function test_incidents_requires_flood_watch_session(): void
    {
        $response = $this->getJson('/flood-watch/incidents?lat=51.12&lng=-2.82&region=SOM');

        $response->assertForbidden();
    }

    public function test_incidents_returns_empty_items_without_coords(): void
    {
        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/incidents');

        $response->assertOk();
        $response->assertExactJson(['items' => []]);
    }

    public function test_incidents_maps_orchestrator_rows_for_cockpit(): void
    {
        $orch = Mockery::mock(RoadIncidentOrchestrator::class);
        $orch->shouldReceive('getFilteredIncidents')
            ->once()
            ->with('SOM', 51.12, -2.82)
            ->andReturn([
                [
                    'road' => 'A361',
                    'status' => 'Closed',
                    'incidentType' => 'Flooding',
                    'locationDescription' => 'Flood water on carriageway',
                    'lat' => 51.11,
                    'lng' => -2.81,
                    'isFloodRelated' => true,
                ],
            ]);
        $this->app->instance(RoadIncidentOrchestrator::class, $orch);

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/incidents?lat=51.12&lng=-2.82&region=SOM');

        $response->assertOk();
        $response->assertJsonPath('items.0.road', 'A361');
        $response->assertJsonPath('items.0.type', 'incident');
        $response->assertJsonPath('items.0.statusLabel', 'Closed');
        $response->assertJsonPath('items.0.lat', 51.11);
    }
}
