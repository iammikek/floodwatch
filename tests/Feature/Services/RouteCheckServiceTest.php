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
            'api.postcodes.io/*' => Http::response(['status' => 404, 'error' => 'Postcode not found'], 404),
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
}
