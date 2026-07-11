<?php

declare(strict_types=1);

use App\Roads\Services\RoadIncidentOrchestrator;
use App\Services\FloodWatchService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

it('prepends canonical preamble when enabled and incidents exist', function () {
    Config::set('openai.api_key', 'test-key');
    Config::set('flood-watch.add_canonical_preamble', true);
    Config::set('flood-watch.national_highways.api_key', 'test-key');
    Config::set('flood-watch.national_highways.base_url', 'https://api.data.nationalhighways.co.uk');

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

    $orch = Mockery::mock(RoadIncidentOrchestrator::class);
    $orch->shouldReceive('getFilteredIncidents')
        ->once()
        ->with(Mockery::any(), Mockery::any(), Mockery::any())
        ->andReturn([
            ['road' => 'A303', 'isFloodRelated' => true],
            ['road' => 'A39', 'isFloodRelated' => false],
        ]);
    app()->instance(RoadIncidentOrchestrator::class, $orch);

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

    $finalResponse = CreateResponse::fake([
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'LLM summary here.',
                    'tool_calls' => [],
                ],
                'logprobs' => null,
                'finish_reason' => 'stop',
            ],
        ],
    ]);

    OpenAI::fake([$toolCallResponse, $finalResponse]);

    $service = app(FloodWatchService::class);
    $result = $service->chat('Check status', [], null, 51.0358, -2.8318, 'somerset');

    expect($result['response'] ?? '')->toStartWith("## Summary\n\n### Current Status\n\n- Flood warnings: 0\n- Flood alerts: 0\n- Road status: 2 incidents\n\nLLM summary here.");
});
