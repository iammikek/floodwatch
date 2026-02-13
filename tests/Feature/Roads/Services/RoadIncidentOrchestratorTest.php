<?php

declare(strict_types=1);

use App\Roads\Services\NationalHighwaysService;
use App\Roads\Services\RoadIncidentOrchestrator;
use App\Roads\Services\SomersetCouncilRoadworksService;

beforeEach(function (): void {
    $this->highwaysService = Mockery::mock(NationalHighwaysService::class);
    $this->somersetService = Mockery::mock(SomersetCouncilRoadworksService::class);
    $this->somersetService->allows('getIncidents')->andReturn([]);
});

it('retains M5 incidents when key_routes includes junction-suffixed entries like M5 J23', function (): void {
    config()->set('flood-watch.correlation.somerset.key_routes', ['M5 J23', 'A361', 'A372']);

    $this->highwaysService->allows('getIncidents')->andReturn([
        ['id' => '1', 'road' => 'M5', 'lat' => 51.0, 'lng' => -2.8],
        ['id' => '2', 'road' => 'M5 J24-J25', 'lat' => 51.1, 'lng' => -2.9],
        ['id' => '3', 'road' => 'A361', 'lat' => 51.05, 'lng' => -2.85],
        ['id' => '4', 'road' => 'A120', 'lat' => 51.9, 'lng' => 0.9], // Should be filtered out
    ]);

    $orchestrator = new RoadIncidentOrchestrator($this->highwaysService, $this->somersetService);

    config()->set('flood-watch.exclude_motorways_from_display', false);

    $incidents = $orchestrator->getFilteredIncidents('somerset', 51.0, -2.8);

    $roads = array_column($incidents, 'road');

    expect($roads)->toContain('M5')
        ->and($roads)->toContain('M5 J24-J25')
        ->and($roads)->toContain('A361')
        ->and($roads)->not->toContain('A120');
});

it('normalizes key_routes with extractBaseRoad before filtering', function (): void {
    config()->set('flood-watch.correlation.bristol.key_routes', ['M4 J19', 'M5 J14-J15', 'A38']);

    $this->highwaysService->allows('getIncidents')->andReturn([
        ['id' => '1', 'road' => 'M4', 'lat' => 51.5, 'lng' => -2.6],
        ['id' => '2', 'road' => 'M5', 'lat' => 51.4, 'lng' => -2.5],
        ['id' => '3', 'road' => 'A38', 'lat' => 51.45, 'lng' => -2.55],
        ['id' => '4', 'road' => 'A4', 'lat' => 51.45, 'lng' => -2.55], // Not in key_routes
    ]);

    $orchestrator = new RoadIncidentOrchestrator($this->highwaysService, $this->somersetService);

    config()->set('flood-watch.exclude_motorways_from_display', false);

    $incidents = $orchestrator->getFilteredIncidents('bristol', 51.5, -2.6);

    $roads = array_column($incidents, 'road');

    expect($roads)->toContain('M4')
        ->and($roads)->toContain('M5')
        ->and($roads)->toContain('A38')
        ->and($roads)->not->toContain('A4');
});

it('filters out incidents with empty road field', function (): void {
    config()->set('flood-watch.correlation.somerset.key_routes', ['A361']);

    $this->highwaysService->allows('getIncidents')->andReturn([
        ['id' => '1', 'road' => 'A361', 'lat' => 51.0, 'lng' => -2.8],
        ['id' => '2', 'road' => '', 'lat' => 51.0, 'lng' => -2.8],
        ['id' => '3', 'lat' => 51.0, 'lng' => -2.8], // Missing road key
    ]);

    $orchestrator = new RoadIncidentOrchestrator($this->highwaysService, $this->somersetService);

    $incidents = $orchestrator->getFilteredIncidents('somerset', 51.0, -2.8);

    expect($incidents)->toHaveCount(1)
        ->and($incidents[0]['road'])->toBe('A361');
});
