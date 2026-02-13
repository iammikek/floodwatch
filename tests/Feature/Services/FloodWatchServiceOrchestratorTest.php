<?php

declare(strict_types=1);

use App\Roads\Services\RoadIncidentOrchestrator;
use App\Services\FloodWatchService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

it('dispatches via ToolRegistry and preserves full incidents in returned data while LLM sees trimmed content', function () {
    Config::set('openai.api_key', 'test-key');
    Config::set('flood-watch.llm_max_incidents', 1); // ensure presenter would trim to 1 for LLM
    Config::set('flood-watch.national_highways.api_key', 'test-key');
    Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

    // Prefetch HTTP calls
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.ffc-environment-agency.fgs.metoffice.gov.uk')) {
            return Http::response(['statement' => []], 200);
        }
        if (str_contains($request->url(), 'api.open-meteo.com')) {
            return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
        }
        if (str_contains($request->url(), 'environment.data.gov.uk')) {
            return Http::response(['items' => []], 200);
        }
        if (str_contains($request->url(), 'api.data.nationalhighways.co.uk')) {
            return Http::response([], 200);
        }

        return Http::response([], 200);
    });

    // Mock orchestrator used by GetHighwaysIncidentsHandler (invoked via ToolRegistry)
    $orch = Mockery::mock(RoadIncidentOrchestrator::class);
    $orch->shouldReceive('getFilteredIncidents')
        ->once()
        ->with(Mockery::any(), Mockery::any(), Mockery::any())
        ->andReturn([
            ['road' => 'A303', 'isFloodRelated' => true],
            ['road' => 'A39', 'isFloodRelated' => false],
        ]);
    app()->instance(RoadIncidentOrchestrator::class, $orch);

    // First LLM response: requests GetHighwaysIncidents tool
    $toolCallResponse = CreateResponse::fake([
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'GetHighwaysIncidents',
                                'arguments' => json_encode([]),
                            ],
                        ],
                    ],
                ],
                'logprobs' => null,
                'finish_reason' => 'tool_calls',
            ],
        ],
    ]);

    // Final LLM response: finish
    $finalResponse = CreateResponse::fake([
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Here is the summary.',
                    'tool_calls' => [],
                ],
                'logprobs' => null,
                'finish_reason' => 'stop',
            ],
        ],
    ]);

    OpenAI::fake([$toolCallResponse, $finalResponse]);

    $service = app(FloodWatchService::class);
    $result = $service->chat('Check status near Langport', [], null, 51.0358, -2.8318, 'somerset');

    // Expect full incidents (2) are preserved in returned data even though LLM-facing content was limited to 1
    expect($result['incidents'] ?? [])->toBeArray()->toHaveCount(2);
    expect($result['response'] ?? '')->toBe('Here is the summary.');
});
