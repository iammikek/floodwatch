<?php

declare(strict_types=1);

namespace App\Support\Tooling;

use App\Support\ConfigKey;

/**
 * Allocates token budget across multiple tool payloads proportionally.
 *
 * When the combined size of tool outputs exceeds the available budget,
 * this allocator trims each payload proportionally based on its share
 * of the total content.
 */
final class BudgetAllocator
{
    /**
     * Apply proportional trimming to tool payloads based on token budget.
     *
     * @param  array<string, array<int, mixed>>  $toolPayloads  Map of tool name => array of items
     * @param  TokenBudget  $budget  Available token budget
     * @return array<string, array<int, mixed>> Trimmed payloads
     */
    public static function apply(array $toolPayloads, TokenBudget $budget): array
    {
        if (empty($toolPayloads)) {
            return [];
        }

        $maxTokens = $budget->remaining();
        if ($maxTokens <= 0) {
            return array_map(fn () => [], $toolPayloads);
        }

        $toolSizes = [];
        $totalItems = 0;

        foreach ($toolPayloads as $toolName => $items) {
            $count = count($items);
            $toolSizes[$toolName] = $count;
            $totalItems += $count;
        }

        if ($totalItems === 0) {
            return $toolPayloads;
        }

        $estimatedCharsPerItem = (int) config(ConfigKey::LLM_ESTIMATED_CHARS_PER_ITEM, 200);
        $charsPerToken = 4;
        $estimatedTotalChars = $totalItems * $estimatedCharsPerItem;
        $availableChars = $maxTokens * $charsPerToken;

        if ($estimatedTotalChars <= $availableChars) {
            return $toolPayloads;
        }

        $reductionRatio = $availableChars / $estimatedTotalChars;

        $result = [];
        foreach ($toolPayloads as $toolName => $items) {
            $originalCount = count($items);
            $allowedCount = max(1, (int) floor($originalCount * $reductionRatio));
            $result[$toolName] = array_slice($items, 0, $allowedCount);
        }

        return $result;
    }

    /**
     * Get per-tool max items from config, applying budget constraints.
     *
     * @param  array<string, string>  $configKeys  Map of tool name => config key for max items
     * @param  TokenBudget  $budget  Available token budget
     * @return array<string, int> Map of tool name => allowed max items
     */
    public static function getPerToolLimits(array $configKeys, TokenBudget $budget): array
    {
        $limits = [];
        $totalConfiguredItems = 0;

        foreach ($configKeys as $toolName => $configKey) {
            $limit = (int) config($configKey, 25);
            $limits[$toolName] = $limit;
            $totalConfiguredItems += $limit;
        }

        if ($totalConfiguredItems === 0) {
            return $limits;
        }

        $estimatedCharsPerItem = (int) config(ConfigKey::LLM_ESTIMATED_CHARS_PER_ITEM, 200);
        $charsPerToken = 4;
        $estimatedTotalChars = $totalConfiguredItems * $estimatedCharsPerItem;
        $availableChars = $budget->remaining() * $charsPerToken;

        if ($estimatedTotalChars <= $availableChars) {
            return $limits;
        }

        $reductionRatio = $availableChars / $estimatedTotalChars;

        foreach ($limits as $toolName => $limit) {
            $limits[$toolName] = max(1, (int) floor($limit * $reductionRatio));
        }

        return $limits;
    }
}
