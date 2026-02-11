<?php

declare(strict_types=1);

use App\Support\Tooling\BudgetAllocator;
use App\Support\Tooling\TokenBudget;

it('returns empty array when given empty payloads', function () {
    $budget = new TokenBudget(1000, 0);

    $result = BudgetAllocator::apply([], $budget);

    expect($result)->toBe([]);
});

it('returns empty arrays for all tools when budget is zero', function () {
    $budget = new TokenBudget(100, 100);

    $payloads = [
        'GetFloodData' => [['id' => 1], ['id' => 2]],
        'GetHighwaysIncidents' => [['id' => 3]],
    ];

    $result = BudgetAllocator::apply($payloads, $budget);

    expect($result['GetFloodData'])->toBe([]);
    expect($result['GetHighwaysIncidents'])->toBe([]);
});

it('returns payloads unchanged when within budget', function () {
    $budget = new TokenBudget(10000, 0);

    $payloads = [
        'GetFloodData' => [['id' => 1], ['id' => 2]],
        'GetHighwaysIncidents' => [['id' => 3]],
    ];

    $result = BudgetAllocator::apply($payloads, $budget);

    expect($result)->toBe($payloads);
});

it('trims payloads proportionally when over budget', function () {
    config(['flood-watch.llm_estimated_chars_per_item' => 1000]);

    $budget = new TokenBudget(500, 0);

    $payloads = [
        'GetFloodData' => array_fill(0, 10, ['id' => 1]),
        'GetHighwaysIncidents' => array_fill(0, 10, ['id' => 2]),
    ];

    $result = BudgetAllocator::apply($payloads, $budget);

    expect(count($result['GetFloodData']))->toBeLessThan(10);
    expect(count($result['GetHighwaysIncidents']))->toBeLessThan(10);
    expect(count($result['GetFloodData']))->toBeGreaterThanOrEqual(1);
    expect(count($result['GetHighwaysIncidents']))->toBeGreaterThanOrEqual(1);
});

it('preserves at least one item per tool when trimming', function () {
    config(['flood-watch.llm_estimated_chars_per_item' => 10000]);

    $budget = new TokenBudget(100, 0);

    $payloads = [
        'GetFloodData' => array_fill(0, 50, ['id' => 1]),
        'GetHighwaysIncidents' => array_fill(0, 50, ['id' => 2]),
    ];

    $result = BudgetAllocator::apply($payloads, $budget);

    expect(count($result['GetFloodData']))->toBeGreaterThanOrEqual(1);
    expect(count($result['GetHighwaysIncidents']))->toBeGreaterThanOrEqual(1);
});

it('calculates per-tool limits from config keys', function () {
    config([
        'flood-watch.llm_max_floods' => 25,
        'flood-watch.llm_max_incidents' => 20,
    ]);

    $budget = new TokenBudget(50000, 0);

    $limits = BudgetAllocator::getPerToolLimits([
        'GetFloodData' => 'flood-watch.llm_max_floods',
        'GetHighwaysIncidents' => 'flood-watch.llm_max_incidents',
    ], $budget);

    expect($limits['GetFloodData'])->toBe(25);
    expect($limits['GetHighwaysIncidents'])->toBe(20);
});

it('reduces per-tool limits proportionally when budget is tight', function () {
    config([
        'flood-watch.llm_max_floods' => 100,
        'flood-watch.llm_max_incidents' => 100,
        'flood-watch.llm_estimated_chars_per_item' => 1000,
    ]);

    $budget = new TokenBudget(1000, 0);

    $limits = BudgetAllocator::getPerToolLimits([
        'GetFloodData' => 'flood-watch.llm_max_floods',
        'GetHighwaysIncidents' => 'flood-watch.llm_max_incidents',
    ], $budget);

    expect($limits['GetFloodData'])->toBeLessThan(100);
    expect($limits['GetHighwaysIncidents'])->toBeLessThan(100);
    expect($limits['GetFloodData'])->toBeGreaterThanOrEqual(1);
    expect($limits['GetHighwaysIncidents'])->toBeGreaterThanOrEqual(1);
});
