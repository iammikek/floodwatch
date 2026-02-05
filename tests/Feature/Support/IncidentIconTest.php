<?php

namespace Tests\Feature\Support;

use App\Support\IncidentIcon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class IncidentIconTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('flood-watch.incident_icons', [
            'flooding' => '🌊',
            'roadClosed' => '🚫',
            'laneClosures' => '⚠️',
            'constructionWork' => '🚧',
            'maintenanceWork' => '🛠️',
            'sweepingOfRoad' => '🧹',
            'roadworks' => '🚧',
            'default' => '🛣️',
        ]);
    }

    public function test_returns_construction_icon_for_construction_work(): void
    {
        $this->assertSame('🚧', IncidentIcon::forIncident('constructionWork'));
    }

    public function test_returns_sweeping_icon_for_sweeping_of_road(): void
    {
        $this->assertSame('🧹', IncidentIcon::forIncident('sweepingOfRoad'));
    }

    public function test_returns_maintenance_icon_for_maintenance_work(): void
    {
        $this->assertSame('🛠️', IncidentIcon::forIncident('maintenanceWork'));
    }

    public function test_returns_flooding_icon_for_flooding(): void
    {
        $this->assertSame('🌊', IncidentIcon::forIncident('flooding'));
    }

    public function test_returns_road_closed_icon_for_road_closed_management_type(): void
    {
        $this->assertSame('🚫', IncidentIcon::forIncident(null, 'roadClosed'));
    }

    public function test_returns_lane_closure_icon_for_lane_closures(): void
    {
        $this->assertSame('⚠️', IncidentIcon::forIncident('laneClosures'));
    }

    public function test_matches_case_insensitively(): void
    {
        $this->assertSame('🚧', IncidentIcon::forIncident('ConstructionWork'));
        $this->assertSame('🌊', IncidentIcon::forIncident('FLOODING'));
    }

    public function test_returns_default_icon_when_no_match(): void
    {
        $this->assertSame('🛣️', IncidentIcon::forIncident('unknownType'));
    }

    public function test_returns_default_icon_for_null_and_empty(): void
    {
        $this->assertSame('🛣️', IncidentIcon::forIncident(null));
        $this->assertSame('🛣️', IncidentIcon::forIncident(''));
    }
}
