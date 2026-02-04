<?php

namespace Tests\Feature\Flood\Services;

use App\Flood\Services\FloodForecastService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FloodForecastServiceTest extends TestCase
{
    public function test_returns_forecast_from_fgs_api(): void
    {
        Config::set('flood-watch.flood_forecast.base_url', 'https://api.example.com');

        Http::fake([
            'api.example.com/*' => Http::response([
                'statement' => [
                    'issued_at' => '2026-02-04T10:30:00Z',
                    'public_forecast' => [
                        'england_forecast' => 'The forecast of flooding across England for today and the next 4 days is very low.',
                    ],
                    'flood_risk_trend' => [
                        'day1' => 'stable',
                        'day2' => 'stable',
                        'day3' => 'increasing',
                        'day4' => 'stable',
                        'day5' => 'stable',
                    ],
                    'sources' => [
                        [
                            'river' => 'The river flood risk is LOW for the next five days. Somerset Levels mentioned.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(FloodForecastService::class);
        $result = $service->getForecast();

        $this->assertSame('2026-02-04T10:30:00Z', $result['issued_at']);
        $this->assertStringContainsString('very low', $result['england_forecast']);
        $this->assertSame('stable', $result['flood_risk_trend']['day1']);
        $this->assertSame('increasing', $result['flood_risk_trend']['day3']);
        $this->assertArrayHasKey('river', $result['sources']);
        $this->assertStringContainsString('Somerset Levels', $result['sources']['river']);
    }

    public function test_returns_empty_array_on_api_failure(): void
    {
        Config::set('flood-watch.flood_forecast.base_url', 'https://api.example.com');

        Http::fake(['api.example.com/*' => Http::response(null, 500)]);

        $service = app(FloodForecastService::class);
        $result = $service->getForecast();

        $this->assertSame([], $result);
    }

    public function test_returns_empty_array_when_statement_missing(): void
    {
        Config::set('flood-watch.flood_forecast.base_url', 'https://api.example.com');

        Http::fake(['api.example.com/*' => Http::response(['foo' => 'bar'], 200)]);

        $service = app(FloodForecastService::class);
        $result = $service->getForecast();

        $this->assertSame([], $result);
    }
}
