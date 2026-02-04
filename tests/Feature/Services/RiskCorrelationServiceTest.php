<?php

namespace Tests\Feature\Services;

use App\Services\RiskCorrelationService;
use Tests\TestCase;

class RiskCorrelationServiceTest extends TestCase
{
    public function test_correlate_categorises_floods_by_severity(): void
    {
        $service = app(RiskCorrelationService::class);

        $floods = [
            [
                'description' => 'Severe area',
                'severity' => 'Severe Flood Warning',
                'severityLevel' => 1,
                'message' => '',
                'floodAreaID' => '1',
            ],
            [
                'description' => 'Warning area',
                'severity' => 'Flood Warning',
                'severityLevel' => 2,
                'message' => '',
                'floodAreaID' => '2',
            ],
        ];

        $assessment = $service->correlate($floods, [], [], null);

        $this->assertCount(1, $assessment->severeFloods);
        $this->assertSame('Severe area', $assessment->severeFloods[0]['description']);
        $this->assertCount(1, $assessment->floodWarnings);
        $this->assertSame('Warning area', $assessment->floodWarnings[0]['description']);
    }

    public function test_correlate_cross_references_flood_areas_with_roads_for_somerset(): void
    {
        $service = app(RiskCorrelationService::class);

        $floods = [
            [
                'description' => 'North Moor',
                'severity' => 'Flood Warning',
                'severityLevel' => 2,
                'message' => '',
                'floodAreaID' => '1',
            ],
        ];
        $incidents = [
            ['road' => 'A361 East Lyng', 'status' => 'Closed', 'incidentType' => '', 'delayTime' => ''],
        ];

        $assessment = $service->correlate($floods, $incidents, [], 'somerset');

        $this->assertNotEmpty($assessment->crossReferences);
        $this->assertTrue($assessment->crossReferences[0]['hasIncident']);
        $this->assertStringContainsString('North Moor', $assessment->crossReferences[0]['floodArea']);
    }

    public function test_correlate_adds_predictive_warning_when_parrett_elevated(): void
    {
        $service = app(RiskCorrelationService::class);

        $riverLevels = [
            [
                'station' => 'Parrett at Langport',
                'river' => 'River Parrett',
                'levelStatus' => 'elevated',
            ],
        ];

        $assessment = $service->correlate([], [], $riverLevels, 'somerset');

        $this->assertNotEmpty($assessment->predictiveWarnings);
        $this->assertStringContainsString('Muchelney', $assessment->predictiveWarnings[0]['message']);
    }

    public function test_correlate_returns_key_routes_for_region(): void
    {
        $service = app(RiskCorrelationService::class);

        $assessment = $service->correlate([], [], [], 'somerset');

        $this->assertContains('A361', $assessment->keyRoutes);
        $this->assertContains('A372', $assessment->keyRoutes);
    }

    public function test_correlate_returns_empty_assessment_for_empty_data(): void
    {
        $service = app(RiskCorrelationService::class);

        $assessment = $service->correlate([], [], [], null);

        $this->assertEmpty($assessment->severeFloods);
        $this->assertEmpty($assessment->floodWarnings);
        $this->assertEmpty($assessment->crossReferences);
        $this->assertEmpty($assessment->predictiveWarnings);
        $this->assertEmpty($assessment->keyRoutes);
    }

    public function test_correlate_to_array_returns_correct_structure(): void
    {
        $service = app(RiskCorrelationService::class);
        $assessment = $service->correlate([], [], [], 'somerset');

        $arr = $assessment->toArray();

        $this->assertArrayHasKey('severe_floods', $arr);
        $this->assertArrayHasKey('flood_warnings', $arr);
        $this->assertArrayHasKey('road_incidents', $arr);
        $this->assertArrayHasKey('cross_references', $arr);
        $this->assertArrayHasKey('predictive_warnings', $arr);
        $this->assertArrayHasKey('key_routes', $arr);
    }

    public function test_correlate_to_prompt_context_includes_cross_references(): void
    {
        $service = app(RiskCorrelationService::class);

        $floods = [
            [
                'description' => 'North Moor',
                'severity' => 'Flood Warning',
                'severityLevel' => 2,
                'message' => '',
                'floodAreaID' => '1',
            ],
        ];
        $incidents = [
            ['road' => 'A361', 'status' => 'Closed', 'incidentType' => '', 'delayTime' => ''],
        ];

        $assessment = $service->correlate($floods, $incidents, [], 'somerset');
        $context = $assessment->toPromptContext();

        $this->assertStringContainsString('Correlation summary', $context);
        $this->assertStringContainsString('North Moor', $context);
        $this->assertStringContainsString('A361', $context);
    }
}
