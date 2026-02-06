<?php

namespace Tests\Feature\Roads;

use App\Roads\IncidentIcon;
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

    public function test_status_label_returns_translated_label_for_known_status(): void
    {
        $this->assertSame('Planned', IncidentIcon::statusLabel('planned'));
        $this->assertSame('Active', IncidentIcon::statusLabel('active'));
        $this->assertSame('Suspended', IncidentIcon::statusLabel('suspended'));
    }

    public function test_status_label_returns_title_cased_for_unknown_status(): void
    {
        $this->assertSame('Unknown Status', IncidentIcon::statusLabel('unknownStatus'));
    }

    public function test_status_label_returns_empty_for_null_and_empty(): void
    {
        $this->assertSame('', IncidentIcon::statusLabel(null));
        $this->assertSame('', IncidentIcon::statusLabel(''));
    }

    public function test_type_label_returns_translated_label_for_known_type(): void
    {
        $this->assertSame('Authority operation', IncidentIcon::typeLabel('authorityOperation'));
        $this->assertSame('Road works', IncidentIcon::typeLabel('constructionWork'));
        $this->assertSame('Flooding', IncidentIcon::typeLabel('flooding'));
    }

    public function test_type_label_returns_title_cased_for_unknown_type(): void
    {
        $this->assertSame('Some Unknown Type', IncidentIcon::typeLabel('someUnknownType'));
    }

    public function test_type_label_returns_empty_for_null_and_empty(): void
    {
        $this->assertSame('', IncidentIcon::typeLabel(null));
        $this->assertSame('', IncidentIcon::typeLabel(''));
    }

    public function test_enrich_adds_icon_status_label_and_type_label_to_each_incident(): void
    {
        $incidents = [
            [
                'incidentType' => 'flooding',
                'managementType' => 'roadClosed',
                'status' => 'active',
            ],
        ];

        $enriched = IncidentIcon::enrich($incidents);

        $this->assertSame('🌊', $enriched[0]['icon']);
        $this->assertSame('Active', $enriched[0]['statusLabel']);
        $this->assertSame('Flooding', $enriched[0]['typeLabel']);
    }

    public function test_enrich_handles_empty_incidents(): void
    {
        $enriched = IncidentIcon::enrich([]);

        $this->assertSame([], $enriched);
    }

    public function test_enrich_handles_incidents_with_missing_fields(): void
    {
        $incidents = [
            [],
        ];

        $enriched = IncidentIcon::enrich($incidents);

        $this->assertArrayHasKey('icon', $enriched[0]);
        $this->assertArrayHasKey('statusLabel', $enriched[0]);
        $this->assertArrayHasKey('typeLabel', $enriched[0]);
        $this->assertSame('🛣️', $enriched[0]['icon']);
    }
}
