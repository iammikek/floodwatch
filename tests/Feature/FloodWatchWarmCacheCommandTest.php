<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

test('warm cache command runs successfully with mocked APIs', function () {
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
                    'content' => 'All clear.',
                    'tool_calls' => [],
                ],
                'logprobs' => null,
                'finish_reason' => 'stop',
            ],
        ],
    ]);

    OpenAI::fake([$toolCallResponse, $finalResponse]);

    $this->artisan('flood-watch:warm-cache', ['--locations' => 'Langport'])
        ->assertSuccessful();
});

test('warm cache command skips when api key not set', function () {
    Config::set('openai.api_key', '');

    $this->artisan('flood-watch:warm-cache', ['--locations' => 'Langport'])
        ->assertFailed();
});

test('schedule list includes warm cache command', function () {
    $this->artisan('schedule:list')
        ->assertSuccessful()
        ->expectsOutputToContain('flood-watch:warm-cache');
});
