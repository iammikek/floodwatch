<?php

declare(strict_types=1);

namespace App\Services\Tooling\Handlers;

use App\Contracts\Tooling\ToolHandler;
use App\Enums\ToolName;
use App\Flood\Services\FloodForecastService;
use App\Support\ConfigKey;
use App\Support\LlmTrim;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;
use Illuminate\Support\Facades\Log;

final class GetFloodForecastHandler implements ToolHandler
{
    public function __construct(private FloodForecastService $forecastService) {}

    public function name(): ToolName
    {
        return ToolName::GetFloodForecast;
    }

    /**
     * @return array{type:string,function:array{name:string,description:string,parameters:array}}
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => ToolName::GetFloodForecast->value,
                'description' => 'Fetch the latest 5-day flood risk forecast from the Flood Forecasting Centre. Returns England-wide narrative (risk trend day1–day5, sources). When summarising, focus on South West–relevant parts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }

    public function execute(ToolArguments $args, ToolContext $ctx): ToolResult
    {
        $data = $this->forecastService->getForecast();

        Log::info('Tool execute', [
            'tool' => ToolName::GetFloodForecast->value,
            'provider' => 'flood_forecasting_centre',
            'region' => $ctx->region,
            'lat' => $ctx->centerLat,
            'lng' => $ctx->centerLng,
        ]);

        return ToolResult::ok($data);
    }

    public function presentForLlm(ToolResult $result, TokenBudget $budget): array|string
    {
        if (! $result->isOk()) {
            return ['getError' => $result->getError()];
        }

        $data = $result->data();
        if (! is_array($data)) {
            return $data;
        }

        if (isset($data['england_forecast'])) {
            $data['england_forecast'] = LlmTrim::truncate(
                (string) $data['england_forecast'],
                (int) config(ConfigKey::LLM_MAX_FORECAST_CHARS, 1200)
            );
        }

        $maxExtraChars = 800;
        foreach (['flood_risk_trend', 'sources'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                if (strlen(json_encode($data[$key])) > $maxExtraChars) {
                    $data[$key] = LlmTrim::limitItems($data[$key], 3);
                }
            }
        }

        return $data;
    }
}
