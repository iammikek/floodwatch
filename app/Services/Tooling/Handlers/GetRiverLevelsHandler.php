<?php

declare(strict_types=1);

namespace App\Services\Tooling\Handlers;

use App\Contracts\Tooling\ToolHandler;
use App\Enums\ToolName;
use App\Flood\Services\RiverLevelService;
use App\Support\ConfigKey;
use App\Support\LlmTrim;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;

final class GetRiverLevelsHandler implements ToolHandler
{
    public function __construct(private RiverLevelService $riverLevelService) {}

    public function name(): ToolName
    {
        return ToolName::GetRiverLevels;
    }

    /**
     * Returns the OpenAI function tool definition.
     *
     * @return array{type:string,function:array{name:string,description:string,parameters:array}}
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => ToolName::GetRiverLevels->value,
                'description' => 'Fetch real-time river and sea levels from Environment Agency monitoring stations. Use coordinates from the user message when a postcode is given; otherwise use default (Langport 51.0358, -2.8318). Returns station name, river, town, current level and unit, and reading time.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'lat' => ['type' => 'number', 'description' => 'Latitude (default: 51.0358 for Langport)'],
                        'lng' => ['type' => 'number', 'description' => 'Longitude (default: -2.8318 for Langport)'],
                        'radius_km' => ['type' => 'integer', 'description' => 'Search radius in km (default: 15)'],
                    ],
                ],
            ],
        ];
    }

    public function execute(ToolArguments $args, ToolContext $ctx): ToolResult
    {
        $data = $this->riverLevelService->getLevels($args->lat ?? null, $args->lng ?? null, $args->radiusKm ?? null);

        return ToolResult::ok($data);
    }

    public function presentForLlm(ToolResult $result, TokenBudget $budget): array|string
    {
        if (! $result->isOk()) {
            return ['getError' => $result->error()];
        }

        $max = (int) config(ConfigKey::LLM_MAX_RIVER_LEVELS, 15);

        return LlmTrim::limitItems($result->data(), $max);
    }
}
