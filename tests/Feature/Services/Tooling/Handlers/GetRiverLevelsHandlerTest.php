<?php

declare(strict_types=1);

use App\Flood\Services\RiverLevelService;
use App\Services\Tooling\Handlers\GetRiverLevelsHandler;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;
use Illuminate\Support\Facades\Config;

it('executes and returns river levels via service', function () {
    $svc = Mockery::mock(RiverLevelService::class);
    $svc->shouldReceive('getLevels')
        ->once()
        ->with(null, null, null)
        ->andReturn([
            ['station' => 'Langport', 'river' => 'Parrett', 'level' => 2.1, 'unit' => 'm'],
        ]);

    $handler = new GetRiverLevelsHandler($svc);

    $result = $handler->execute(new ToolArguments, new ToolContext);

    expect($result->isOk())->toBeTrue();
    expect($result->data())->toBeArray()->toHaveCount(1);
});

it('presents river levels limited by config', function () {
    Config::set('flood-watch.llm_max_river_levels', 1);

    $svc = Mockery::mock(RiverLevelService::class);
    $handler = new GetRiverLevelsHandler($svc);

    $toolResult = ToolResult::ok([
        ['station' => 'A', 'river' => 'X', 'level' => 1.0, 'unit' => 'm'],
        ['station' => 'B', 'river' => 'Y', 'level' => 2.0, 'unit' => 'm'],
    ]);

    $presented = $handler->presentForLlm($toolResult, new TokenBudget(0));

    expect($presented)->toBeArray()->toHaveCount(1);
});

it('presents error shape on failure', function () {
    $svc = Mockery::mock(RiverLevelService::class);
    $handler = new GetRiverLevelsHandler($svc);

    $presented = $handler->presentForLlm(ToolResult::error('Service unavailable'), new TokenBudget(0));

    expect($presented)->toBeArray()->toHaveKey('getError', 'Service unavailable');
});
