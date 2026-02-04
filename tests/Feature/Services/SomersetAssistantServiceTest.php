<?php

namespace Tests\Feature\Services;

use App\Services\SomersetAssistantService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class SomersetAssistantServiceTest extends TestCase
{
    public function test_returns_message_when_no_api_key(): void
    {
        Config::set('openai.api_key', '');

        $service = app(SomersetAssistantService::class);

        $result = $service->chat('Check status');

        $this->assertStringContainsString('OPENAI_API_KEY', $result['response']);
        $this->assertSame([], $result['floods']);
        $this->assertSame([], $result['incidents']);
    }

    public function test_chat_returns_final_response_after_tool_calls(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['closure' => ['closure' => []]], 200);
            }

            return Http::response(null, 404);
        });

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
                                'function' => ['name' => 'GetFloodData', 'arguments' => '{}'],
                            ],
                            [
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => ['name' => 'GetHighwaysIncidents', 'arguments' => '{}'],
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
                        'content' => "## Current Status\n\nNo active flood warnings. Roads are clear.",
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $service = app(SomersetAssistantService::class);
        $result = $service->chat('Check flood and road status');

        $this->assertSame("## Current Status\n\nNo active flood warnings. Roads are clear.", $result['response']);
        $this->assertIsArray($result['floods']);
        $this->assertIsArray($result['incidents']);
    }

    public function test_chat_returns_response_when_llm_responds_without_tool_calls(): void
    {
        Config::set('openai.api_key', 'test-key');

        $directResponse = CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'I can help. Please use the Check status button to fetch data.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$directResponse]);

        $service = app(SomersetAssistantService::class);
        $result = $service->chat('How does this work?');

        $this->assertSame('I can help. Please use the Check status button to fetch data.', $result['response']);
        $this->assertSame([], $result['floods']);
        $this->assertSame([], $result['incidents']);
    }

    public function test_execute_tool_passes_custom_coordinates_to_get_flood_data(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                $this->assertStringContainsString('lat=52.1', $request->url());
                $this->assertStringContainsString('long=-1.2', $request->url());
                $this->assertStringContainsString('dist=20', $request->url());

                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['closure' => ['closure' => []]], 200);
            }

            return Http::response(null, 404);
        });

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
                                    'name' => 'GetFloodData',
                                    'arguments' => json_encode([
                                        'lat' => 52.1,
                                        'long' => -1.2,
                                        'radius_km' => 20,
                                    ]),
                                ],
                            ],
                            [
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => ['name' => 'GetHighwaysIncidents', 'arguments' => '{}'],
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
                        'content' => 'Data retrieved for custom location.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $service = app(SomersetAssistantService::class);
        $result = $service->chat('Check floods near TA10');

        $this->assertSame('Data retrieved for custom location.', $result['response']);
    }

    public function test_chat_synthesizes_response_from_flood_and_incident_data(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response([
                    'items' => [
                        [
                            'description' => 'River Parrett at Langport',
                            'severity' => 'Flood Warning',
                            'severityLevel' => 2,
                            'message' => 'Flooding expected.',
                        ],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response([
                    'closure' => [
                        'closure' => [
                            [
                                'road' => 'A361',
                                'status' => 'closed',
                                'incidentType' => 'flooding',
                                'delayTime' => '30 minutes',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(null, 404);
        });

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
                                'function' => ['name' => 'GetFloodData', 'arguments' => '{}'],
                            ],
                            [
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => ['name' => 'GetHighwaysIncidents', 'arguments' => '{}'],
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
                        'content' => "## Current Status\n\nFlood Warning for River Parrett. A361 closed due to flooding.",
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $service = app(SomersetAssistantService::class);
        $result = $service->chat('Check status');

        $this->assertStringContainsString('River Parrett', $result['response']);
        $this->assertStringContainsString('A361', $result['response']);
        $this->assertCount(1, $result['floods']);
        $this->assertSame('River Parrett at Langport', $result['floods'][0]['description']);
        $this->assertCount(1, $result['incidents']);
        $this->assertSame('A361', $result['incidents'][0]['road']);
    }
}
