<?php

declare(strict_types=1);

use App\Roads\Services\RoadIncidentOrchestrator;
use App\Services\FloodWatchService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

it('handles very low token budget by trimming while completing the flow', function () {
    Config::set('openai.api_key', 'test-key');
    // Force an aggressively low context token limit so trimming paths are exercised
    Config::set('flood-watch.llm_max_context_tokens', 1000);
    // Allow many incidents so tool content can be large before budget trimming applies
    Config::set('flood-watch.llm_max_incidents', 500);
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

    // Mock orchestrator to return a large incident list to inflate tool message size
    $large = [];
    for ($i = 0; $i < 300; $i++) {
        $large[] = ['road' => 'A'.(100 + $i), 'isFloodRelated' => ($i % 2) === 0];
    }

    $orch = Mockery::mock(RoadIncidentOrchestrator::class);
    $orch->shouldReceive('getFilteredIncidents')
        ->once()
        ->andReturn($large);
    app()->instance(RoadIncidentOrchestrator::class, $orch);

    // First response: tool call for highways incidents
    $toolCallResponse = CreateResponse::fake([
        'choices' => [[
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'GetHighwaysIncidents',
                        'arguments' => json_encode([]),
                    ],
                ]],
            ],
            'logprobs' => null,
            'finish_reason' => 'tool_calls',
        ]],
    ]);

    // Final response: completes
    $finalResponse = CreateResponse::fake([
        'choices' => [[
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => 'Summary prepared under tight budget.',
                'tool_calls' => [],
            ],
            'logprobs' => null,
            'finish_reason' => 'stop',
        ]],
    ]);

    OpenAI::fake([$toolCallResponse, $finalResponse]);

    $service = app(FloodWatchService::class);
    $result = $service->chat('Check status', [], null, 51.0358, -2.8318, 'somerset');

    expect($result['response'] ?? '')->toBe('Summary prepared under tight budget.');
    // Should keep full incidents in final return, even though LLM-facing content was trimmed
    expect($result['incidents'] ?? [])->toBeArray()->toHaveCount(300);
});
