<?php

declare(strict_types=1);

namespace App\Services\Tooling\Handlers;

use App\Contracts\Tooling\ToolHandler;
use App\Enums\ToolName;
use App\Flood\DTOs\FloodWarning;
use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Support\ConfigKey;
use App\Support\LlmTrim;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;

final class GetFloodDataHandler implements ToolHandler
{
    public function __construct(private EnvironmentAgencyFloodService $ea) {}

    public function name(): ToolName
    {
        return ToolName::GetFloodData;
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => ToolName::GetFloodData->value,
                'description' => 'Fetch current flood warnings from the Environment Agency for the South West. Use coordinates from user postcode when present; otherwise defaults are used.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'lat' => [
                            'type' => 'number',
                            'description' => 'Latitude (default: 51.0358 for Langport)',
                        ],
                        'lng' => [
                            'type' => 'number',
                            'description' => 'Longitude (default: -2.8318 for Langport)',
                        ],
                        'radius_km' => [
                            'type' => 'integer',
                            'description' => 'Search radius in km (default: 15)',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(ToolArguments $args, ToolContext $ctx): ToolResult
    {
        $data = $this->ea->getFloods($args->lat, $args->lng, $args->radiusKm);

        return ToolResult::ok($data);
    }

    public function presentForLlm(ToolResult $result, TokenBudget $budget): array|string
    {
        if (! $result->isOk()) {
            return ['error' => $result->error()];
        }

        $max = (int) config(ConfigKey::LLM_MAX_FLOODS, 25);
        $maxMsg = (int) config(ConfigKey::LLM_MAX_FLOOD_MESSAGE_CHARS, 300);

        return LlmTrim::trimList($result->data(), $max, function ($flood) use ($maxMsg) {
            $arr = FloodWarning::fromArray($flood)->withoutPolygon()->toArray();
            if (isset($arr['message'])) {
                $arr['message'] = LlmTrim::truncate($arr['message'], $maxMsg);
            }

            return $arr;
        });
    }
}
