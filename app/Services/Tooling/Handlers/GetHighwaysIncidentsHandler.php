<?php

declare(strict_types=1);

namespace App\Services\Tooling\Handlers;

use App\Contracts\Tooling\ToolHandler;
use App\Enums\ToolName;
use App\Roads\Services\RoadIncidentOrchestrator;
use App\Support\ConfigKey;
use App\Support\LlmTrim;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;
use Illuminate\Support\Facades\Log;

final class GetHighwaysIncidentsHandler implements ToolHandler
{
    public function __construct(private RoadIncidentOrchestrator $orchestrator) {}

    public function name(): ToolName
    {
        return ToolName::GetHighwaysIncidents;
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => ToolName::GetHighwaysIncidents->value,
                'description' => 'Fetch road and lane closure incidents from National Highways for South West key routes. Flood-related incidents are prioritised.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }

    public function execute(ToolArguments $args, ToolContext $ctx): ToolResult
    {
        $data = $this->orchestrator->getFilteredIncidents($ctx->region, $ctx->centerLat, $ctx->centerLng);

        Log::info('Tool execute', [
            'tool' => ToolName::GetHighwaysIncidents->value,
            'provider' => 'national_highways',
            'region' => $ctx->region,
            'lat' => $ctx->centerLat,
            'lng' => $ctx->centerLng,
            'count' => is_array($data) ? count($data) : 0,
        ]);

        return ToolResult::ok($data);
    }

    public function presentForLlm(ToolResult $result, TokenBudget $budget): array|string
    {
        if (! $result->isOk()) {
            return ['getError' => $result->getError()];
        }

        return LlmTrim::limitItems($result->data(), (int) config(ConfigKey::LLM_MAX_INCIDENTS, 25));
    }
}
