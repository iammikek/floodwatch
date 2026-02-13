<?php

namespace Tests\Unit\Support;

use App\Support\ConfigKey;
use App\Support\Tooling\BudgetAllocator;
use App\Support\Tooling\TokenBudget;
use Tests\TestCase;

class BudgetAllocatorTest extends TestCase
{
    public function test_get_per_tool_limits_returns_zeros_when_budget_exhausted(): void
    {
        config([ConfigKey::LLM_ESTIMATED_CHARS_PER_ITEM => 200]);
        $configKeys = [
            'GetFloodData' => ConfigKey::LLM_MAX_FLOODS,
            'GetHighwaysIncidents' => ConfigKey::LLM_MAX_INCIDENTS,
            'GetRiverLevels' => ConfigKey::LLM_MAX_RIVER_LEVELS,
        ];

        $limits = BudgetAllocator::getPerToolLimits($configKeys, new TokenBudget(maxTokens: 0, usedTokens: 0));

        $this->assertSame(['GetFloodData' => 0, 'GetHighwaysIncidents' => 0, 'GetRiverLevels' => 0], $limits);
    }

    public function test_apply_returns_empty_arrays_when_budget_exhausted(): void
    {
        $payloads = [
            'GetFloodData' => [1, 2, 3],
            'GetHighwaysIncidents' => [1, 2],
            'GetRiverLevels' => [1],
        ];

        $trimmed = BudgetAllocator::apply($payloads, new TokenBudget(maxTokens: 0, usedTokens: 0));

        $this->assertSame(['GetFloodData' => [], 'GetHighwaysIncidents' => [], 'GetRiverLevels' => []], $trimmed);
    }
}
