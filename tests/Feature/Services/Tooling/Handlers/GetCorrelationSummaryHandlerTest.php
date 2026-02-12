<?php

declare(strict_types=1);

use App\Services\RiskCorrelationService;
use App\Services\Tooling\Handlers\GetCorrelationSummaryHandler;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;

it('executes correlation using context floods/incidents/levels and returns array', function () {
    $svc = new RiskCorrelationService;

    $handler = new GetCorrelationSummaryHandler($svc);

    $result = $handler->execute(
        new ToolArguments,
        new ToolContext(region: 'somerset', floods: [['f' => 1]], incidents: [['i' => 2]], riverLevels: [['r' => 3]])
    );

    expect($result->isOk())->toBeTrue();
    expect($result->data())->toBeArray();
});

it('presents correlation result as-is and surfaces errors', function () {
    $svc = new RiskCorrelationService;
    $handler = new GetCorrelationSummaryHandler($svc);

    $ok = $handler->presentForLlm(ToolResult::ok(['summary' => 'ok']), new TokenBudget(0));
    expect($ok)->toBeArray()->toHaveKey('summary', 'ok');

    $err = $handler->presentForLlm(ToolResult::error('Service unavailable'), new TokenBudget(0));
    expect($err)->toBeArray()->toHaveKey('getError', 'Service unavailable');
});
