<?php

declare(strict_types=1);

namespace App\Services\Tooling\Handlers;

use App\Contracts\Tooling\ToolHandler;
use App\Enums\ToolName;
use App\Services\RiskCorrelationService;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;
use Illuminate\Support\Facades\Log;

final class GetCorrelationSummaryHandler implements ToolHandler
{
    public function __construct(private RiskCorrelationService $correlationService) {}

    public function name(): ToolName
    {
        return ToolName::GetCorrelationSummary;
    }

    /**
     * @return array{type:string,function:array{name:string,description:string,parameters:array}}
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => ToolName::GetCorrelationSummary->value,
                'description' => 'Get a deterministic correlation of flood warnings with road incidents and river levels. Call this after fetching flood and road data to receive cross-references and key routes to monitor. Use this to inform your summary.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }

    public function execute(ToolArguments $args, ToolContext $ctx): ToolResult
    {
        $assessment = $this->correlationService->correlate(
            $ctx->floods ?? [],
            $ctx->incidents ?? [],
            $ctx->riverLevels ?? [],
            $ctx->region ?? null,
        );

        $arr = $assessment->toArray();

        Log::info('Tool execute', [
            'tool' => ToolName::GetCorrelationSummary->value,
            'provider' => 'flood_watch_correlation',
            'region' => $ctx->region,
            'lat' => $ctx->centerLat,
            'lng' => $ctx->centerLng,
            'count' => is_array($ctx->floods) ? count($ctx->floods) : 0,
            'incidents' => is_array($ctx->incidents) ? count($ctx->incidents) : 0,
            'river_levels' => is_array($ctx->riverLevels) ? count($ctx->riverLevels) : 0,
        ]);

        return ToolResult::ok($arr);
    }

    public function presentForLlm(ToolResult $result, TokenBudget $budget): array|string
    {
        if (! $result->isOk()) {
            return ['getError' => $result->getError()];
        }

        // Correlation output is already succinct and LLM-facing; return as-is.
        return $result->data();
    }
}
