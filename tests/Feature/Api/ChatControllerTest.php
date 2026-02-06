<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    public function test_chat_returns_json_api_document_with_ai_response(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);
        Config::set('flood-watch.cache_ttl_minutes', 0);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'postcodes.io')) {
                return Http::response([
                    'result' => [
                        'latitude' => 51.0358,
                        'longitude' => -2.8318,
                        'postcode' => 'TA10 9PU',
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
                            ['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'GetFloodData', 'arguments' => '{}']],
                            ['id' => 'c2', 'type' => 'function', 'function' => ['name' => 'GetHighwaysIncidents', 'arguments' => '{}']],
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
                        'content' => 'No active flood warnings. Roads are clear.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $response = $this->postJson('/api/v1/chat', [
            'location' => 'TA10 9PU',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'response',
                    'floods',
                    'incidents',
                    'lastChecked',
                ],
            ],
        ]);
        $this->assertSame('chat', $response->json('data.type'));
        $this->assertStringContainsString('No active flood warnings', $response->json('data.attributes.response'));
    }

    public function test_chat_returns_422_for_invalid_location(): void
    {
        $response = $this->postJson('/api/v1/chat', [
            'location' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);
    }
}
