<?php

declare(strict_types=1);

use App\Roads\Services\RoadIncidentOrchestrator;
use App\Services\Tooling\Handlers\GetHighwaysIncidentsHandler;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;
use Illuminate\Support\Facades\Config;

it('executes and returns incidents via orchestrator using context', function () {
    $orch = Mockery::mock(RoadIncidentOrchestrator::class);
    $orch->shouldReceive('getFilteredIncidents')
        ->once()
        ->with('somerset', 51.0, -2.8)
        ->andReturn([
            ['road' => 'A303', 'isFloodRelated' => true],
            ['road' => 'A39', 'isFloodRelated' => false],
        ]);

    $handler = new GetHighwaysIncidentsHandler($orch);

    $result = $handler->execute(
        new ToolArguments,
        new ToolContext(region: 'somerset', centerLat: 51.0, centerLng: -2.8)
    );

    expect($result->isOk())->toBeTrue();
    expect($result->data())->toBeArray()->toHaveCount(2);
});

it('presents incidents limited by config for LLM', function () {
    Config::set('flood-watch.llm_max_incidents', 1);

    $orch = Mockery::mock(RoadIncidentOrchestrator::class);
    $handler = new GetHighwaysIncidentsHandler($orch);

    $toolResult = ToolResult::ok([
        ['road' => 'A303', 'isFloodRelated' => true],
        ['road' => 'A39', 'isFloodRelated' => false],
    ]);

    $presented = $handler->presentForLlm($toolResult, new TokenBudget(0));

    expect($presented)->toBeArray()->toHaveCount(1);
    expect($presented[0]['road'] ?? null)->toBe('A303');
});

it('presents error shape when ToolResult is error', function () {
    $orch = Mockery::mock(RoadIncidentOrchestrator::class);
    $handler = new GetHighwaysIncidentsHandler($orch);

    $presented = $handler->presentForLlm(ToolResult::error('Service unavailable'), new TokenBudget(0));

    expect($presented)->toBeArray()->toHaveKey(ToolResult::ERROR_KEY, 'Service unavailable');
});
