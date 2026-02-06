<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class FloodWatchDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $lat = config('flood-watch.default_lat', 51.0358);
        $long = config('flood-watch.default_long', -2.8318);
        Cache::put("flood-watch:map-data:{$lat}:{$long}", [
            'floods' => [],
            'incidents' => [],
            'riverLevels' => [],
            'lastChecked' => null,
        ], 300);
        Cache::put('flood-watch-risk-gauge', [
            'index' => 0,
            'label' => 'Low',
            'summary' => 'No active alerts.',
        ], 900);
    }

    public function test_home_page_renders_flood_watch_dashboard_component(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSeeLivewire('flood-watch-dashboard');
    }

    public function test_home_page_shows_support_link_when_donation_url_configured(): void
    {
        Config::set('app.donation_url', 'https://ko-fi.com/automicalabs');

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Support', false);
        $response->assertSee('https://ko-fi.com/automicalabs', false);
    }

    public function test_home_page_hides_support_link_when_donation_url_not_configured(): void
    {
        Config::set('app.donation_url', null);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('https://ko-fi.com/automicalabs', false);
    }

    public function test_flood_watch_dashboard_displays_flood_risk_road_status_and_forecast_badges(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->assertSee('Flood Risk')
            ->assertSee('Road Status')
            ->assertSee('5-Day Forecast');
    }

    public function test_flood_watch_dashboard_has_location_input(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->assertSee('Your location', false)
            ->assertSet('location', '');
    }

    public function test_dashboard_displays_status_grid_with_four_columns(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->assertSee('Hydrological Activity', false)
            ->assertSee('Infrastructural Impact', false)
            ->assertSee('Weather Outlook', false)
            ->assertSee('AI Advisory', false);
    }

    public function test_dashboard_shows_map_by_default_with_default_centre(): void
    {
        $component = Livewire::test('flood-watch-dashboard');

        $component->assertSet('mapCenter.lat', 51.0358);
        $component->assertSet('mapCenter.long', -2.8318);
        $component->assertSee('flood-map', false);
    }

    public function test_dashboard_has_check_my_location_as_primary_cta(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->assertSee('Check my location', false)
            ->assertSee('Check status', false);
    }

    public function test_dashboard_displays_risk_gauge_section(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

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

        Livewire::test('flood-watch-dashboard')
            ->assertSee('South West Risk Index', false)
            ->assertSee('risk-gauge', false);
    }

    public function test_status_grid_infrastructure_shows_active_over_monitored_format(): void
    {
        $component = Livewire::test('flood-watch-dashboard')
            ->set('assistantResponse', 'Summary.')
            ->set('incidents', [
                ['road' => 'A361', 'status' => 'closed'],
            ]);

        $component->assertSee('1 incidents on 7 monitored routes', false);
    }

    public function test_status_grid_weather_shows_precipitation_next_48h(): void
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
                return Http::response(['daily' => ['time' => ['2025-02-04', '2025-02-05', '2025-02-06'], 'weathercode' => [61, 0, 0], 'temperature_2m_max' => [10, 9, 8], 'temperature_2m_min' => [5, 4, 3], 'precipitation_sum' => [3.2, 2.1, 0]]], 200);
            }

            return Http::response(null, 404);
        });

        $directResponse = CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Weather checked.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$directResponse]);

        Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSee('5.3', false)
            ->assertSee('mm next 48h', false);
    }

    public function test_status_grid_hydrological_shows_stations_elevated_when_applicable(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                if (str_contains($request->url(), '/id/stations')) {
                    return Http::response(['items' => [['notation' => 'E123', 'lat' => 51.04, 'long' => -2.83]]], 200);
                }
                if (str_contains($request->url(), '/id/measures')) {
                    return Http::response(['items' => [['@id' => 'm1', 'latestReading' => ['value' => 2.5], 'period' => 900]]], 200);
                }

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
                            [
                                'id' => 'call_3',
                                'type' => 'function',
                                'function' => ['name' => 'GetRiverLevels', 'arguments' => '{}'],
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
                        'content' => 'River levels checked.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $riverLevelsWithElevated = [
            ['station' => 'Gaw Bridge', 'river' => 'Parrett', 'levelStatus' => 'elevated', 'value' => 4.0, 'typicalRangeHigh' => 3.5, 'trend' => 'rising'],
            ['station' => 'Bishops Hull', 'river' => 'Tone', 'levelStatus' => 'elevated', 'value' => 1.8, 'typicalRangeHigh' => 1.5, 'trend' => 'stable'],
            ['station' => 'Other', 'levelStatus' => 'low', 'value' => 0.5],
        ];

        $component = Livewire::test('flood-watch-dashboard');
        $component->set('assistantResponse', 'Checked.');
        $component->set('riverLevels', $riverLevelsWithElevated);

        $component->assertSee('2 stations elevated', false)
            ->assertSee('Gaw Bridge (Parrett)', false)
            ->assertSee('Bishops Hull (Tone)', false)
            ->assertSee('0.5 m above', false);
    }

    public function test_status_grid_ai_advisory_has_italic_styling(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->set('assistantResponse', 'River Parrett levels elevated.')
            ->assertSee('italic', false);
    }

    public function test_dashboard_has_side_by_side_layout_with_activity_feed_on_desktop(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->assertSee('Live Activity', false)
            ->assertSee('activity-feed', false)
            ->assertSee('lg:flex-row', false);
    }

    public function test_dashboard_activity_feed_shows_recent_activities(): void
    {
        $activity = \App\Models\SystemActivity::factory()->create([
            'description' => 'New flood warning: North Moor',
            'occurred_at' => now(),
        ]);

        Livewire::test('flood-watch-dashboard')
            ->assertSee('New flood warning: North Moor', false)
            ->assertSee($activity->occurred_at->format('H:i'), false);
    }

    public function test_dashboard_risk_gauge_shows_index_label_and_summary(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

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

        $component = Livewire::test('flood-watch-dashboard');

        $risk = $component->get('risk');
        $this->assertIsArray($risk);
        $this->assertArrayHasKey('index', $risk);
        $this->assertArrayHasKey('label', $risk);
        $this->assertArrayHasKey('summary', $risk);

        $component->assertSee((string) $risk['index'], false)
            ->assertSee('/ 100', false)
            ->assertSee($risk['label'], false)
            ->assertSee($risk['summary'], false);
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

        Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('assistantResponse', "## Current Status\n\nNo active flood warnings. Roads are clear.")
            ->assertSet('floods', [])
            ->assertSet('incidents', [])
            ->assertSee('No active flood warnings')
            ->assertSee('Roads are clear')
            ->assertSee('No alerts')
            ->assertSee('Clear');
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
            ->assertSee('Road Status')
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
            ->assertSee('Connecting')
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
            ->set('location', 'TA10 0')
            ->call('search')
            ->assertSet('assistantResponse', 'Checked TA10 0.');
    }

    public function test_search_displays_error_when_assistant_throws(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('app.debug', true);

        $this->fakeChatPrefetchHttp();

        OpenAI::fake([new \RuntimeException('OpenAI API rate limit exceeded')]);

        $component = Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('error', 'AI service rate limit exceeded. Please wait a minute and try again.');

        $this->assertNotNull($component->get('retryAfterTimestamp'));
        $this->assertGreaterThan(time(), $component->get('retryAfterTimestamp'));
    }

    public function test_search_displays_generic_error_when_assistant_throws_and_debug_off(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('app.debug', false);

        $this->fakeChatPrefetchHttp();

        OpenAI::fake([new \RuntimeException('OpenAI API rate limit exceeded')]);

        $component = Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('error', 'AI service rate limit exceeded. Please wait a minute and try again.');

        $this->assertNotNull($component->get('retryAfterTimestamp'));
    }

    public function test_search_displays_friendly_message_for_timeout_error(): void
    {
        Config::set('openai.api_key', 'test-key');
        Config::set('app.debug', true);

        $this->fakeChatPrefetchHttp();

        OpenAI::fake([
            new \RuntimeException('cURL error 28: Operation timed out after 30005 milliseconds with 0 bytes received'),
        ]);

        $component = Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('error', 'The request took too long. The AI service may be busy. Please try again in a moment.');

        $this->assertNull($component->get('retryAfterTimestamp'));
    }

    public function test_check_status_button_has_spinner_markup(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('animate-spin', false);
        $response->assertSee('wire:loading', false);
    }

    public function test_search_rejects_unknown_place_name(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'nominatim.openstreetmap.org')) {
                return Http::response([], 200);
            }

            return Http::response(null, 404);
        });

        Livewire::test('flood-watch-dashboard')
            ->set('location', 'XyzzyNowhere123')
            ->call('search')
            ->assertSet('error', 'Location not found. Try a postcode or town name (e.g. Langport, Bristol, Exeter).')
            ->assertSet('assistantResponse', null);
    }

    public function test_search_rejects_out_of_area_postcode(): void
    {
        Livewire::test('flood-watch-dashboard')
            ->set('location', 'SW1A 1AA')
            ->call('search')
            ->assertSet('error', 'This postcode is outside the South West. Flood Watch covers Bristol, Somerset, Devon and Cornwall.')
            ->assertSet('assistantResponse', null);
    }

    public function test_floods_show_distance_from_user_location_when_searching_by_postcode(): void
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
                if (str_contains($request->url(), '/polygon')) {
                    return Http::response([
                        'type' => 'FeatureCollection',
                        'features' => [
                            [
                                'type' => 'Feature',
                                'geometry' => [
                                    'type' => 'Polygon',
                                    'coordinates' => [[[-2.83, 51.04], [-2.82, 51.04], [-2.82, 51.05], [-2.83, 51.05], [-2.83, 51.04]]],
                                ],
                            ],
                        ],
                    ], 200);
                }
                if (str_contains($request->url(), '/id/floodAreas')) {
                    return Http::response([
                        'items' => [
                            ['notation' => '123abc', 'lat' => 51.04, 'long' => -2.82],
                            ['notation' => '456def', 'lat' => 51.06, 'long' => -2.85],
                        ],
                    ], 200);
                }

                return Http::response([
                    'items' => [
                        [
                            'description' => 'North Moor',
                            'severity' => 'Flood Warning',
                            'severityLevel' => 2,
                            'floodAreaID' => '456def',
                            'message' => 'Flooding expected.',
                        ],
                        [
                            'description' => 'Langport area',
                            'severity' => 'Flood Alert',
                            'severityLevel' => 3,
                            'floodAreaID' => '123abc',
                            'message' => 'Flooding possible.',
                        ],
                    ],
                ], 200);
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
                                'function' => ['name' => 'GetFloodData', 'arguments' => json_encode(['lat' => 51.0358, 'long' => -2.8318])],
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
                        'content' => 'Flood warnings active.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$toolCallResponse, $finalResponse]);

        $component = Livewire::test('flood-watch-dashboard')
            ->set('location', 'TA10 0')
            ->call('search');

        $component->assertSet('hasUserLocation', true)
            ->assertSee('km from your location');

        $floods = $component->get('floods');
        $this->assertCount(2, $floods);
        $this->assertArrayHasKey('distanceKm', $floods[0]);
        $this->assertArrayHasKey('distanceKm', $floods[1]);
        $this->assertNotNull($floods[0]['distanceKm']);
        $this->assertNotNull($floods[1]['distanceKm']);
        $this->assertLessThanOrEqual($floods[1]['distanceKm'], $floods[0]['distanceKm']);
        $this->assertArrayHasKey('polygon', $floods[0]);
        $this->assertSame('FeatureCollection', $floods[0]['polygon']['type'] ?? null);
    }

    public function test_guest_sees_rate_limit_on_page_load_when_already_limited(): void
    {
        $key = 'flood-watch-guest:127.0.0.1';
        RateLimiter::hit($key, 900);

        $component = Livewire::test('flood-watch-dashboard')
            ->assertSet('error', 'Guests are limited to one search every 15 minutes. Please try again later or register for unlimited access.')
            ->assertSet('retryAfterTimestamp', fn ($v) => $v !== null && $v > time());
    }

    public function test_guest_user_is_rate_limited_to_one_search_per_fifteen_minutes(): void
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

        $response = CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'First search OK.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$response]);

        $component = Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('assistantResponse', 'First search OK.');

        $component->call('search')
            ->assertSet('error', 'Guests are limited to one search every 15 minutes. Please try again later or register for unlimited access.')
            ->assertSet('retryAfterTimestamp', fn ($v) => $v !== null && $v > time());
    }

    public function test_authenticated_user_is_not_rate_limited(): void
    {
        $user = User::factory()->create();

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

        $firstResponse = CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'First search.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $secondResponse = CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Second search.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$firstResponse, $secondResponse]);

        Livewire::actingAs($user)
            ->test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('assistantResponse', 'First search.')
            ->call('search')
            ->assertSet('assistantResponse', 'Second search.')
            ->assertSet('error', null);
    }

    public function test_guest_can_search_again_after_rate_limit_expires(): void
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

        $key = 'flood-watch-guest:127.0.0.1';
        RateLimiter::hit($key, 1);

        $this->travel(2)->seconds();

        $response = CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'After cooldown.',
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        OpenAI::fake([$response]);

        Livewire::test('flood-watch-dashboard')
            ->call('search')
            ->assertSet('assistantResponse', 'After cooldown.')
            ->assertSet('error', null);
    }

    private function fakeChatPrefetchHttp(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                if (str_contains($request->url(), '/id/stations')) {
                    return Http::response(['items' => []], 200);
                }

                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
            }

            return Http::response(null, 404);
        });
    }
}
