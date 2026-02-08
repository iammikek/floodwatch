<?php

namespace Tests\Feature\Services;

use App\Models\LlmRequest;
use App\Models\User;
use App\Services\FloodWatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class FloodWatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_message_when_no_api_key(): void
    {
        Config::set('openai.api_key', '');

        $service = app(FloodWatchService::class);

        $result = $service->chat('Check status');

        $this->assertStringContainsString('OPENAI_API_KEY', $result['response']);
        $this->assertSame([], $result['floods']);
        $this->assertSame([], $result['incidents']);
        $this->assertSame([], $result['forecast']);
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
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
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

        $service = app(FloodWatchService::class);
        $result = $service->chat('Check flood and road status');

        $this->assertSame("## Current Status\n\nNo active flood warnings. Roads are clear.", $result['response']);
        $this->assertIsArray($result['floods']);
        $this->assertIsArray($result['incidents']);
    }

    public function test_chat_returns_response_when_llm_responds_without_tool_calls(): void
    {
        Config::set('openai.api_key', 'test-key');

        Http::fake([
            '*api.ffc-environment-agency.fgs.metoffice.gov.uk*' => Http::response(['statement' => []], 200),
            '*api.open-meteo.com*' => Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200),
            '*environment.data.gov.uk*' => Http::response(['items' => []], 200),
        ]);

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

        $service = app(FloodWatchService::class);
        $result = $service->chat('How does this work?');

        $this->assertSame('I can help. Please use the Check status button to fetch data.', $result['response']);
        $this->assertSame([], $result['floods']);
        $this->assertSame([], $result['incidents']);
    }

    public function test_chat_records_llm_request_with_usage_and_context(): void
    {
        Config::set('openai.api_key', 'test-key');

        Http::fake([
            '*api.ffc-environment-agency.fgs.metoffice.gov.uk*' => Http::response(['statement' => []], 200),
            '*api.open-meteo.com*' => Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200),
            '*environment.data.gov.uk*' => Http::response(['items' => []], 200),
        ]);

        $directResponse = CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Status.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 150,
                'completion_tokens' => 25,
                'total_tokens' => 175,
            ],
            'model' => 'gpt-4o-mini',
            'id' => 'chatcmpl-record123',
        ]);

        OpenAI::fake([$directResponse]);

        $user = User::factory()->create();

        $service = app(FloodWatchService::class);
        $service->chat('Check status', [], null, null, null, 'somerset', $user->id);

        $record = LlmRequest::first();
        $this->assertNotNull($record);
        $this->assertSame((string) $user->id, (string) $record->user_id);
        $this->assertSame(150, $record->input_tokens);
        $this->assertSame(25, $record->output_tokens);
        $this->assertSame('gpt-4o-mini', $record->model);
        $this->assertSame('somerset', $record->region);
        $this->assertSame('chatcmpl-record123', $record->openai_id);
    }

    public function test_execute_tool_passes_custom_coordinates_to_get_flood_data(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                if (str_contains($request->url(), '/id/stations')) {
                    return Http::response(['items' => []], 200);
                }
                if (str_contains($request->url(), '/id/floodAreas')) {
                    return Http::response(['items' => []], 200);
                }
                if (str_contains($request->url(), '/id/floods')) {
                    $this->assertStringContainsString('lat=52.1', $request->url());
                    $this->assertStringContainsString('long=-1.2', $request->url());
                    $this->assertStringContainsString('dist=20', $request->url());

                    return Http::response(['items' => []], 200);
                }
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
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
                                        'lng' => -1.2,
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

        $service = app(FloodWatchService::class);
        $result = $service->chat('Check floods near TA10');

        $this->assertSame('Data retrieved for custom location.', $result['response']);
    }

    public function test_chat_synthesizes_response_from_flood_and_incident_data(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                if (str_contains($request->url(), '/id/stations')) {
                    return Http::response(['items' => []], 200);
                }
                if (str_contains($request->url(), '/id/floodAreas')) {
                    return Http::response(['items' => []], 200);
                }
                if (str_contains($request->url(), '/id/floods')) {
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
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response([
                    'D2Payload' => [
                        'situation' => [
                            [
                                'situationRecord' => [
                                    [
                                        'sitRoadOrCarriagewayOrLaneManagement' => [
                                            'validity' => ['validityStatus' => 'closed'],
                                            'cause' => ['causeType' => 'environmentalObstruction', 'detailedCauseType' => ['environmentalObstructionType' => 'flooding']],
                                            'generalPublicComment' => [['comment' => '30 minutes delay']],
                                            'roadOrCarriagewayOrLaneManagementType' => ['value' => 'roadClosed'],
                                            'locationReference' => [
                                                'locSingleRoadLinearLocation' => [
                                                    'linearWithinLinearElement' => [
                                                        ['linearElement' => ['locLinearElementByCode' => ['roadName' => 'A361']]],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
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

        $service = app(FloodWatchService::class);
        $result = $service->chat('Check status');

        $this->assertStringContainsString('River Parrett', $result['response']);
        $this->assertStringContainsString('A361', $result['response']);
        $this->assertCount(1, $result['floods']);
        $this->assertSame('River Parrett at Langport', $result['floods'][0]['description']);
        $this->assertCount(1, $result['incidents']);
        $this->assertSame('A361', $result['incidents'][0]['road']);
    }

    /**
     * Acceptance criterion: Intelligence correlation.
     * With A361 closed (flooding) + River Parrett elevated, the AI should correlate
     * road closure with river level and warn about Muchelney.
     */
    public function test_intelligence_correlation_a361_parrett_muchelney(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'environment.data.gov.uk')) {
                if (str_contains($url, '/id/stations?') || str_contains($url, '/id/stations&')) {
                    return Http::response([
                        'items' => [
                            [
                                'notation' => '52119',
                                'label' => 'Parrett at Langport',
                                'riverName' => 'River Parrett',
                                'town' => 'Langport',
                                'lat' => 51.036,
                                'long' => -2.832,
                                'stageScale' => ['typicalRangeLow' => 1.5, 'typicalRangeHigh' => 3.5],
                            ],
                        ],
                    ], 200);
                }
                if (str_contains($url, '/stations/52119/readings')) {
                    return Http::response([
                        'items' => [
                            [
                                'value' => 4.2,
                                'dateTime' => '2026-02-04T12:00:00Z',
                                'measure' => 'http://environment.data.gov.uk/flood-monitoring/id/measures/52119-level-stage-i-15_min-mASD',
                            ],
                        ],
                    ], 200);
                }
                if (str_contains($url, '/id/floodAreas')) {
                    return Http::response(['items' => []], 200);
                }
                if (str_contains($url, '/id/floods')) {
                    return Http::response([
                        'items' => [
                            [
                                'description' => 'River Parrett at Langport',
                                'severity' => 'Flood Warning',
                                'severityLevel' => 2,
                                'message' => 'River levels are rising. Flooding expected.',
                            ],
                        ],
                    ], 200);
                }

                return Http::response(['items' => []], 200);
            }
            if (str_contains($url, 'api.example.com')) {
                return Http::response([
                    'D2Payload' => [
                        'situation' => [
                            [
                                'situationRecord' => [
                                    [
                                        'sitRoadOrCarriagewayOrLaneManagement' => [
                                            'validity' => ['validityStatus' => 'closed'],
                                            'cause' => [
                                                'causeType' => 'environmentalObstruction',
                                                'detailedCauseType' => ['environmentalObstructionType' => 'flooding'],
                                            ],
                                            'generalPublicComment' => [['comment' => 'A361 closed due to flooding']],
                                            'roadOrCarriagewayOrLaneManagementType' => ['value' => 'roadClosed'],
                                            'locationReference' => [
                                                'locSingleRoadLinearLocation' => [
                                                    'linearWithinLinearElement' => [
                                                        ['linearElement' => ['locLinearElementByCode' => ['roadName' => 'A361']]],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($url, 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($url, 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
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
                            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'GetFloodData', 'arguments' => '{}']],
                            ['id' => 'call_2', 'type' => 'function', 'function' => ['name' => 'GetHighwaysIncidents', 'arguments' => '{}']],
                            ['id' => 'call_3', 'type' => 'function', 'function' => ['name' => 'GetCorrelationSummary', 'arguments' => '{}']],
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
                        'content' => "## Current Status\n\nThe A361 is closed due to flooding, and the River Parrett is elevated. Expect Muchelney to be isolated. Do not attempt to travel.",
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $service = app(FloodWatchService::class);
        $result = $service->chat('Check flood and road status for Langport', [], null, 51.0358, -2.8318, 'somerset');

        $this->assertStringContainsString('A361', $result['response'], 'Response should mention A361 closure');
        $this->assertStringContainsString('Parrett', $result['response'], 'Response should mention River Parrett');
        $this->assertStringContainsString('Muchelney', $result['response'], 'Response should warn about Muchelney isolation');
        $this->assertCount(1, $result['floods']);
        $this->assertCount(1, $result['incidents']);
        $this->assertSame('A361', $result['incidents'][0]['road']);
    }

    public function test_identical_queries_hit_cache(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.cache_store', 'flood-watch-array');
        Config::set('flood-watch.cache_ttl_minutes', 15);
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
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
                        'content' => 'Cached response.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $service = app(FloodWatchService::class);

        $first = $service->chat('Check status', [], 'TA10 0');
        $this->assertSame('Cached response.', $first['response']);

        OpenAI::fake();

        $second = $service->chat('Check status', [], 'TA10 0');
        $this->assertSame('Cached response.', $second['response']);
    }

    public function test_get_flood_forecast_tool_returns_forecast_data(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.flood_forecast.base_url', 'https://fgs.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'fgs.example.com')) {
                return Http::response([
                    'statement' => [
                        'issued_at' => '2026-02-04T10:30:00Z',
                        'public_forecast' => ['england_forecast' => 'Flood risk is very low for the next 5 days.'],
                        'flood_risk_trend' => ['day1' => 'stable', 'day2' => 'stable'],
                        'sources' => [['river' => 'River flood risk is LOW.']],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
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
                            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'GetFloodData', 'arguments' => '{}']],
                            ['id' => 'call_2', 'type' => 'function', 'function' => ['name' => 'GetHighwaysIncidents', 'arguments' => '{}']],
                            ['id' => 'call_3', 'type' => 'function', 'function' => ['name' => 'GetFloodForecast', 'arguments' => '{}']],
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
                        'content' => 'Including 5-day outlook.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $service = app(FloodWatchService::class);
        $result = $service->chat('What is the flood forecast?');

        $this->assertSame('Including 5-day outlook.', $result['response']);
        $this->assertNotEmpty($result['forecast']);
        $this->assertStringContainsString('very low', $result['forecast']['england_forecast']);
        $this->assertSame('2026-02-04T10:30:00Z', $result['forecast']['issued_at']);
    }

    public function test_filters_incidents_to_south_west_roads_only(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response([
                    'D2Payload' => [
                        'situation' => [
                            [
                                'situationRecord' => [
                                    [
                                        'sitRoadOrCarriagewayOrLaneManagement' => [
                                            'validity' => ['validityStatus' => 'closed'],
                                            'cause' => ['causeType' => 'environmentalObstruction'],
                                            'generalPublicComment' => [['comment' => 'A361 closed']],
                                            'roadOrCarriagewayOrLaneManagementType' => ['value' => 'roadClosed'],
                                            'locationReference' => [
                                                'locSingleRoadLinearLocation' => [
                                                    'linearWithinLinearElement' => [
                                                        ['linearElement' => ['locLinearElementByCode' => ['roadName' => 'A361']]],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'situationRecord' => [
                                    [
                                        'sitRoadOrCarriagewayOrLaneManagement' => [
                                            'validity' => ['validityStatus' => 'active'],
                                            'cause' => ['causeType' => 'roadMaintenance'],
                                            'generalPublicComment' => [['comment' => 'A120 works']],
                                            'roadOrCarriagewayOrLaneManagementType' => ['value' => 'laneClosures'],
                                            'locationReference' => [
                                                'locSingleRoadLinearLocation' => [
                                                    'linearWithinLinearElement' => [
                                                        ['linearElement' => ['locLinearElementByCode' => ['roadName' => 'A120']]],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
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
                            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'GetFloodData', 'arguments' => '{}']],
                            ['id' => 'call_2', 'type' => 'function', 'function' => ['name' => 'GetHighwaysIncidents', 'arguments' => '{}']],
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
                        'content' => 'Road status for South West.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $service = app(FloodWatchService::class);
        $result = $service->chat('Check status');

        $this->assertCount(1, $result['incidents']);
        $this->assertSame('A361', $result['incidents'][0]['road']);
    }
}
