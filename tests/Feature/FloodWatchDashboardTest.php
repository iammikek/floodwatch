<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class FloodWatchDashboardTest extends TestCase
{
    public function test_home_page_renders_flood_watch_dashboard_component(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSeeLivewire('flood-watch-dashboard');
    }

    public function test_flood_watch_dashboard_displays_flood_risk_and_road_status_badges(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->assertSee('Flood Risk')
            ->assertSee('Road Status');
    }

    public function test_flood_watch_dashboard_has_postcode_input(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->assertSee('postcode', false)
            ->assertSet('postcode', '');
    }

    public function test_search_displays_assistant_response(): void
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

        Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('assistantResponse', "## Current Status\n\nNo active flood warnings. Roads are clear.")
            ->assertSet('floods', [])
            ->assertSet('incidents', [])
            ->assertSee('No active flood warnings')
            ->assertSee('Roads are clear');
    }

    public function test_search_displays_flood_and_road_sections_separately(): void
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
                        'content' => 'Summary: Flood and road closure active.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('floods.0.description', 'River Parrett at Langport')
            ->assertSet('incidents.0.road', 'A361')
            ->assertSee('Flood warnings')
            ->assertSee('Road status')
            ->assertSee('River Parrett at Langport')
            ->assertSee('A361')
            ->assertSee('Summary');
    }

    public function test_search_shows_error_when_no_openai_key(): void
    {
        Config::set('openai.api_key', '');

        Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('assistantResponse', 'Flood Watch is not configured with an OpenAI API key. Please add OPENAI_API_KEY to your environment.');
    }

    public function test_loading_spinner_displayed_when_loading(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->set('loading', true)
            ->assertSee('Searching real-time records')
            ->assertSee('animate-spin');
    }

    public function test_search_includes_postcode_in_request_when_provided(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'postcodes.io')) {
                return Http::response([
                    'result' => ['latitude' => 51.0358, 'longitude' => -2.8318],
                ], 200);
            }
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['closure' => ['closure' => []]], 200);
            }

            return Http::response(null, 404);
        });

        $directResponse = CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Checked TA10 0.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$directResponse]);

        Livewire::test('flood-watch-dashboard')
            ->set('postcode', 'TA10 0')
            ->call('search')
            ->assertSet('assistantResponse', 'Checked TA10 0.');
    }

    public function test_search_displays_error_when_assistant_throws(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('app.debug', true);

        OpenAI::fake([new \RuntimeException('OpenAI API rate limit exceeded')]);

        Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('error', 'OpenAI API rate limit exceeded');
    }

    public function test_search_displays_generic_error_when_assistant_throws_and_debug_off(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('app.debug', false);

        OpenAI::fake([new \RuntimeException('OpenAI API rate limit exceeded')]);

        Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('error', 'Unable to get a response. Please try again.');
    }

    public function test_check_status_button_has_spinner_markup(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('animate-spin', false);
        $response->assertSee('wire:loading', false);
    }

    public function test_search_rejects_invalid_postcode(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->set('postcode', 'INVALID')
            ->call('search')
            ->assertSet('error', 'Invalid postcode format. Use a valid UK postcode (e.g. TA10 0DP).')
            ->assertSet('assistantResponse', null);
    }

    public function test_search_rejects_out_of_area_postcode(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->set('postcode', 'SW1A 1AA')
            ->call('search')
            ->assertSet('error', 'This postcode is outside the Somerset Levels. Flood Watch currently covers Sedgemoor and South Somerset only.')
            ->assertSet('assistantResponse', null);
    }
}
