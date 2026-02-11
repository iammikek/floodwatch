<?php

declare(strict_types=1);

use App\Services\RiskCorrelationService;
use App\Services\Tooling\Handlers\GetCorrelationSummaryHandler;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;
use Mockery;

it('executes correlation using context floods/incidents/levels and returns array', function () {
    $svc = Mockery::mock(RiskCorrelationService::class);
    $svc->shouldReceive('correlate')
        ->once()
        ->with(
            [['f' => 1]],
            [['i' => 2]],
            [['r' => 3]],
            'somerset'
        )
        ->andReturn(new class
        {
            public function toArray(): array
            {
                return ['summary' => 'ok', 'key_routes' => ['A303']];
            }
        });

    $handler = new GetCorrelationSummaryHandler($svc);

    $result = $handler->execute(
        new ToolArguments,
        new ToolContext(region: 'somerset', floods: [['f' => 1]], incidents: [['i' => 2]], riverLevels: [['r' => 3]])
    );

    expect($result->isOk())->toBeTrue();
    expect($result->data())->toHaveKey('summary', 'ok');
});

it('presents correlation result as-is and surfaces errors', function () {
    $svc = Mockery::mock(RiskCorrelationService::class);
    $handler = new GetCorrelationSummaryHandler($svc);

    $ok = $handler->presentForLlm(ToolResult::ok(['summary' => 'ok']), new TokenBudget(0));
    expect($ok)->toBeArray()->toHaveKey('summary', 'ok');

    $err = $handler->presentForLlm(ToolResult::error('Service unavailable'), new TokenBudget(0));
    expect($err)->toBeArray()->toHaveKey('getError', 'Service unavailable');
});
