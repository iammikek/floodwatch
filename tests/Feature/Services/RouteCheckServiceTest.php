<?php

namespace Tests\Feature\Services;

use App\DTOs\RouteCheckResult;
use App\Services\RouteCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteCheckServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_error_when_from_empty(): void
    {
        $service = app(RouteCheckService::class);
        $result = $service->check('', 'Bristol');

        $this->assertInstanceOf(RouteCheckResult::class, $result);
        $this->assertSame('error', $result->verdict);
        $this->assertStringContainsString('locations', $result->summary);
    }

    public function test_returns_error_when_to_empty(): void
    {
        $service = app(RouteCheckService::class);
        $result = $service->check('Langport', '');

        $this->assertSame('error', $result->verdict);
    }

    public function test_returns_error_when_from_invalid(): void
    {
        Http::fake([
            'api.postcodes.io/*' => Http::response(['status' => 404, 'getError' => 'Postcode not found'], 404),
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $service = app(RouteCheckService::class);
        $result = $service->check('InvalidPlace99', 'TA10 0DP');

        $this->assertSame('error', $result->verdict);
    }

    public function test_returns_clear_verdict_when_valid_route(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.postcodes.io')) {
                return Http::response(['result' => ['latitude' => 51.0358, 'longitude' => -2.8318]], 200);
            }
            if (str_contains($request->url(), 'router.project-osrm.org')) {
                return Http::response([
                    'code' => 'Ok',
                    'routes' => [
                        [
                            'distance' => 50000,
                            'duration' => 3600,
                            'geometry' => [
                                'coordinates' => [[-2.8318, 51.0358], [-2.5778, 51.4545]],
                                'type' => 'LineString',
                            ],
                            'legs' => [],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RouteCheckService::class);
        $result = $service->check('TA10 0DP', 'BS1 1AA');

        $this->assertSame('clear', $result->verdict);
        $this->assertNotEmpty($result->summary);
        $this->assertNotNull($result->routeKey);
        $this->assertSame(32, strlen($result->routeKey));
        $this->assertIsArray($result->routeGeometry);
        $this->assertCount(2, $result->routeGeometry);
    }

    public function test_returns_error_when_osrm_returns_no_route(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.postcodes.io')) {
                return Http::response(['result' => ['latitude' => 51.0358, 'longitude' => -2.8318]], 200);
            }
            if (str_contains($request->url(), 'router.project-osrm.org')) {
                return Http::response(['code' => 'NoRoute', 'routes' => []], 400);
            }

            return Http::response(null, 404);
        });

        $service = app(RouteCheckService::class);
        $result = $service->check('TA10 0DP', 'BS1 1AA');

        $this->assertSame('error', $result->verdict);
        $this->assertNull($result->routeGeometry);
        $this->assertNull($result->routeKey);
    }

    public function test_returns_cached_result_when_available(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.route_check.cache_ttl_minutes', 15);

        $cached = new RouteCheckResult(
            verdict: 'at_risk',
            summary: 'Cached summary',
            floodsOnRoute: [['description' => 'Test flood']],
            incidentsOnRoute: [],
            alternatives: [],
            routeGeometry: [[-2.83, 51.03], [-2.57, 51.45]],
            routeKey: 'abc123',
        );

        $store = config('flood-watch.cache_store', 'flood-watch');
        $cache = Cache::store($store);
        $cache->put('flood-watch:route_check:51.0358,-2.8318,51.0358,-2.8318', $cached, now()->addMinutes(15));

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.postcodes.io')) {
                return Http::response(['result' => ['latitude' => 51.0358, 'longitude' => -2.8318]], 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RouteCheckService::class);
        $result = $service->check('TA10 0DP', 'TA10 0DP');

        $this->assertSame('at_risk', $result->verdict);
        $this->assertSame('Cached summary', $result->summary);
        $this->assertSame('abc123', $result->routeKey);
        Http::assertSentCount(2);
    }

    public function test_returns_cached_result_when_stored_as_array(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.route_check.cache_ttl_minutes', 15);

        $cachedArray = [
            'verdict' => 'delays',
            'summary' => 'Cached array summary',
            'floods_on_route' => [],
            'incidents_on_route' => [['road' => 'A361', 'incidentType' => 'laneClosures']],
            'alternatives' => [],
            'route_geometry' => [[-2.83, 51.03], [-2.57, 51.45]],
            'route_key' => 'xyz789',
        ];

        $store = config('flood-watch.cache_store', 'flood-watch');
        $cache = Cache::store($store);
        $cache->put('flood-watch:route_check:51.0358,-2.8318,51.0358,-2.8318', $cachedArray, now()->addMinutes(15));

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.postcodes.io')) {
                return Http::response(['result' => ['latitude' => 51.0358, 'longitude' => -2.8318]], 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RouteCheckService::class);
        $result = $service->check('TA10 0DP', 'TA10 0DP');

        $this->assertSame('delays', $result->verdict);
        $this->assertSame('Cached array summary', $result->summary);
        $this->assertSame('xyz789', $result->routeKey);
        Http::assertSentCount(2);
    }

    public function test_returns_blocked_verdict_when_road_closed_on_route(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        $fixture = json_decode(file_get_contents(__DIR__.'/../../fixtures/national_highways_closures.json'), true);

        Http::fake(function ($request) use ($fixture) {
            if (str_contains($request->url(), 'api.postcodes.io')) {
                $url = $request->url();
                $coords = str_contains($url, 'TA10')
                    ? ['latitude' => 51.0358, 'longitude' => -2.8318]
                    : ['latitude' => 51.4545, 'longitude' => -2.5778];

                return Http::response(['result' => $coords], 200);
            }
            if (str_contains($request->url(), 'nominatim.openstreetmap.org')) {
                return Http::response([], 200);
            }
            if (str_contains($request->url(), 'router.project-osrm.org')) {
                $primaryRoute = [
                    'distance' => 50000,
                    'duration' => 3600,
                    'geometry' => [
                        'coordinates' => [[-2.8318, 51.0358], [-2.5778, 51.4545]],
                        'type' => 'LineString',
                    ],
                    'legs' => [],
                ];
                if (str_contains($request->url(), 'alternatives=2')) {
                    return Http::response([
                        'code' => 'Ok',
                        'routes' => [
                            $primaryRoute,
                            [
                                'distance' => 55000,
                                'duration' => 3900,
                                'legs' => [
                                    [
                                        'steps' => [
                                            ['name' => 'A358', 'ref' => ''],
                                            ['name' => 'M5', 'ref' => 'M5'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ], 200);
                }

                return Http::response(['code' => 'Ok', 'routes' => [$primaryRoute]], 200);
            }
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response($fixture, 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RouteCheckService::class);
        $result = $service->check('TA10 0DP', 'BS1 1AA');

        $this->assertNotEmpty($result->incidentsOnRoute, 'Expected incidents on route from fixture');
        $this->assertSame('blocked', $result->verdict);
        $this->assertStringContainsString('road closure', strtolower($result->summary));
        $this->assertNotEmpty($result->alternatives, 'Blocked verdict should fetch alternatives');
    }

    public function test_makes_single_osrm_call_when_verdict_not_blocked(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        $osrmCallCount = 0;
        Http::fake(function ($request) use (&$osrmCallCount) {
            if (str_contains($request->url(), 'api.postcodes.io')) {
                return Http::response(['result' => ['latitude' => 51.0358, 'longitude' => -2.8318]], 200);
            }
            if (str_contains($request->url(), 'router.project-osrm.org')) {
                $osrmCallCount++;

                return Http::response([
                    'code' => 'Ok',
                    'routes' => [
                        [
                            'distance' => 50000,
                            'duration' => 3600,
                            'geometry' => [
                                'coordinates' => [[-2.8318, 51.0358], [-2.5778, 51.4545]],
                                'type' => 'LineString',
                            ],
                            'legs' => [],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RouteCheckService::class);
        $service->check('TA10 0DP', 'BS1 1AA');

        $this->assertSame(1, $osrmCallCount, 'Clear verdict should make only 1 OSRM call (no alternatives fetch)');
    }

    public function test_skips_alternatives_fetch_when_config_disabled(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.route_check.fetch_alternatives_when_blocked', false);

        $fixture = json_decode(file_get_contents(__DIR__.'/../../fixtures/national_highways_closures.json'), true);
        $osrmCallCount = 0;

        Http::fake(function ($request) use ($fixture, &$osrmCallCount) {
            if (str_contains($request->url(), 'api.postcodes.io')) {
                $url = $request->url();
                $coords = str_contains($url, 'TA10')
                    ? ['latitude' => 51.0358, 'longitude' => -2.8318]
                    : ['latitude' => 51.4545, 'longitude' => -2.5778];

                return Http::response(['result' => $coords], 200);
            }
            if (str_contains($request->url(), 'nominatim.openstreetmap.org')) {
                return Http::response([], 200);
            }
            if (str_contains($request->url(), 'router.project-osrm.org')) {
                $osrmCallCount++;

                return Http::response([
                    'code' => 'Ok',
                    'routes' => [
                        [
                            'distance' => 50000,
                            'duration' => 3600,
                            'geometry' => [
                                'coordinates' => [[-2.8318, 51.0358], [-2.5778, 51.4545]],
                                'type' => 'LineString',
                            ],
                            'legs' => [],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response($fixture, 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RouteCheckService::class);
        $result = $service->check('TA10 0DP', 'BS1 1AA');

        $this->assertSame('blocked', $result->verdict);
        $this->assertSame(1, $osrmCallCount, 'fetch_alternatives_when_blocked=false should skip second OSRM call');
        $this->assertEmpty($result->alternatives);

        Config::set('flood-watch.route_check.fetch_alternatives_when_blocked', true);
    }

    public function test_returns_delays_verdict_when_lane_closures_on_route(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        $laneClosuresOnly = [
            'D2Payload' => [
                'situation' => [
                    [
                        'idG' => 'lane-test',
                        'situationRecord' => [
                            [
                                'sitRoadOrCarriagewayOrLaneManagement' => [
                                    'validity' => ['validityStatus' => 'active'],
                                    'cause' => ['causeType' => 'roadOrCarriagewayOrLaneManagement', 'detailedCauseType' => ['roadOrCarriagewayOrLaneManagementType' => ['value' => 'laneClosures']]],
                                    'roadOrCarriagewayOrLaneManagementType' => ['value' => 'laneClosures'],
                                    'locationReference' => [
                                        'locLocationGroupByList' => [
                                            'locationContainedInGroup' => [
                                                [
                                                    'locLinearLocation' => [
                                                        'gmlLineString' => [
                                                            'locGmlLineString' => [
                                                                'posList' => '51.04 -2.83 51.05 -2.82',
                                                            ],
                                                        ],
                                                    ],
                                                    'locSingleRoadLinearLocation' => [
                                                        'linearWithinLinearElement' => [
                                                            [
                                                                'linearElement' => [
                                                                    'locLinearElementByCode' => [
                                                                        'roadName' => 'A361',
                                                                        'linearElementReferenceModel' => 'The Network Model',
                                                                        'linearElementIdentifier' => '{test}',
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake(function ($request) use ($laneClosuresOnly) {
            if (str_contains($request->url(), 'api.postcodes.io')) {
                $url = $request->url();
                $coords = str_contains($url, 'TA10')
                    ? ['latitude' => 51.0358, 'longitude' => -2.8318]
                    : ['latitude' => 51.4545, 'longitude' => -2.5778];

                return Http::response(['result' => $coords], 200);
            }
            if (str_contains($request->url(), 'nominatim.openstreetmap.org')) {
                return Http::response([], 200);
            }
            if (str_contains($request->url(), 'router.project-osrm.org')) {
                return Http::response([
                    'code' => 'Ok',
                    'routes' => [
                        [
                            'distance' => 50000,
                            'duration' => 3600,
                            'geometry' => [
                                'coordinates' => [[-2.8318, 51.0358], [-2.5778, 51.4545]],
                                'type' => 'LineString',
                            ],
                            'legs' => [],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response(['items' => []], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response($laneClosuresOnly, 200);
            }

            return Http::response(null, 404);
        });

        $service = app(RouteCheckService::class);
        $result = $service->check('TA10 0DP', 'BS1 1AA');

        $this->assertSame('delays', $result->verdict);
        $this->assertStringContainsString('delay', strtolower($result->summary));
    }
}
