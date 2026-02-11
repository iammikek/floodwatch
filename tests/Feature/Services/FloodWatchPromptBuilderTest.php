<?php

declare(strict_types=1);

use App\Enums\ToolName;
use App\Services\FloodWatchPromptBuilder;

it('aggregates tool definitions from ToolRegistry and includes all tool names', function () {
    /** @var FloodWatchPromptBuilder $builder */
    $builder = app(FloodWatchPromptBuilder::class);

    $defs = $builder->getToolDefinitions();

    $names = array_map(fn ($d) => $d['function']['name'] ?? null, $defs);

    expect($names)->toContain(
        ToolName::GetFloodData->value,
        ToolName::GetHighwaysIncidents->value,
        ToolName::GetRiverLevels->value,
        ToolName::GetFloodForecast->value,
        ToolName::GetCorrelationSummary->value,
    );
});
