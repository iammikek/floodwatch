<?php

declare(strict_types=1);

use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Services\Tooling\Handlers\GetFloodDataHandler;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;
use Illuminate\Support\Facades\Config;

it('executes and returns flood data via EA service', function () {
    $ea = Mockery::mock(EnvironmentAgencyFloodService::class);
    $ea->shouldReceive('getFloods')
        ->once()
        ->with(null, null, null)
        ->andReturn([
            ['description' => 'Flood A', 'message' => 'A very long message', 'polygon' => [[-2.8, 51.0]]],
        ]);

    $handler = new GetFloodDataHandler($ea);

    $result = $handler->execute(new ToolArguments, new ToolContext);

    expect($result->isOk())->toBeTrue();
    expect($result->data())->toBeArray()->toHaveCount(1);
});

it('presents trimmed floods for LLM and removes polygon', function () {
    Config::set('flood-watch.llm_max_floods', 1);
    Config::set('flood-watch.llm_max_flood_message_chars', 10);

    $ea = Mockery::mock(EnvironmentAgencyFloodService::class);
    $handler = new GetFloodDataHandler($ea);

    $toolResult = ToolResult::ok([
        [
            'description' => 'Flood A',
            'message' => '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'polygon' => [[-2.8, 51.0]],
            'floodAreaID' => '123',
        ],
        [
            'description' => 'Flood B',
            'message' => 'Another message',
            'floodAreaID' => '456',
        ],
    ]);

    $presented = $handler->presentForLlm($toolResult, new TokenBudget(0));

    expect($presented)->toBeArray()->toHaveCount(1);
    expect($presented[0])->toHaveKey('description', 'Flood A');
    expect($presented[0])->not->toHaveKey('polygon');
    expect(mb_strlen($presented[0]['message'] ?? ''))->toBeLessThanOrEqual(11); // 10 + ellipsis or equal when exact
});

it('presents error shape when ToolResult is error', function () {
    $ea = Mockery::mock(EnvironmentAgencyFloodService::class);
    $handler = new GetFloodDataHandler($ea);

    $presented = $handler->presentForLlm(ToolResult::error('Service unavailable'), new TokenBudget(0));

    expect($presented)->toBeArray()->toHaveKey(ToolResult::ERROR_KEY, 'Service unavailable');
});
