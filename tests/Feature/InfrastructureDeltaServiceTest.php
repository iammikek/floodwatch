<?php

use App\Models\SystemActivity;
use App\Services\InfrastructureDeltaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('detects new flood warning and creates activity', function () {
    $service = app(InfrastructureDeltaService::class);
    $previous = ['floods' => [], 'incidents' => [], 'riverLevels' => []];
    $current = [
        'floods' => [
            ['floodAreaID' => '123', 'description' => 'North Moor', 'severityLevel' => 2],
        ],
        'incidents' => [],
        'riverLevels' => [],
    ];

    $activities = $service->compareAndCreateActivities($previous, $current);

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->type)->toBe('flood_warning')
        ->and($activities[0]->description)->toContain('North Moor')
        ->and(SystemActivity::count())->toBe(1);
});

test('detects road closure and creates activity', function () {
    $service = app(InfrastructureDeltaService::class);
    $previous = ['floods' => [], 'incidents' => [], 'riverLevels' => []];
    $current = [
        'floods' => [],
        'incidents' => [
            ['road' => 'A361', 'status' => 'closed'],
        ],
        'riverLevels' => [],
    ];

    $activities = $service->compareAndCreateActivities($previous, $current);

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->type)->toBe('road_closure')
        ->and($activities[0]->description)->toContain('A361');
});
