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

        return ToolResult::ok($assessment->toArray());
    }

    public function presentForLlm(ToolResult $result, TokenBudget $budget): array|string
    {
        if (! $result->isOk()) {
            return ['getError' => $result->error()];
        }

        // Correlation output is already succinct and LLM-facing; return as-is.
        return $result->data();
    }
}
