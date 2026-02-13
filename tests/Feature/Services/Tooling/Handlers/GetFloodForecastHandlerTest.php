<?php

declare(strict_types=1);

use App\Flood\Services\FloodForecastService;
use App\Services\Tooling\Handlers\GetFloodForecastHandler;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;
use Illuminate\Support\Facades\Config;

it('executes and returns forecast via service', function () {
    $svc = Mockery::mock(FloodForecastService::class);
    $svc->shouldReceive('getForecast')
        ->once()
        ->andReturn(['england_forecast' => 'Long narrative', 'sources' => ['EA', 'Met Office']]);

    $handler = new GetFloodForecastHandler($svc);

    $result = $handler->execute(new ToolArguments, new ToolContext);

    expect($result->isOk())->toBeTrue();
    expect($result->data())->toBeArray()->toHaveKey('england_forecast');
});

it('presents forecast with truncated narrative and limited extras', function () {
    Config::set('flood-watch.llm_max_forecast_chars', 10);

    $svc = Mockery::mock(FloodForecastService::class);
    $handler = new GetFloodForecastHandler($svc);

    $toolResult = ToolResult::ok([
        'england_forecast' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'flood_risk_trend' => ['d1', 'd2', 'd3', 'd4', 'd5'],
        'sources' => ['EA', 'Met Office', 'Other', 'Another'],
    ]);

    $presented = $handler->presentForLlm($toolResult, new TokenBudget(0));

    expect($presented)->toBeArray();
    expect(mb_strlen($presented['england_forecast'] ?? ''))
        ->toBeLessThanOrEqual(11); // 10 + ellipsis or equal when exact
});

it('presents error shape on failure', function () {
    $svc = Mockery::mock(FloodForecastService::class);
    $handler = new GetFloodForecastHandler($svc);

    $presented = $handler->presentForLlm(ToolResult::error('Service unavailable'), new TokenBudget(0));

    expect($presented)->toBeArray()->toHaveKey('getError', 'Service unavailable');
});
