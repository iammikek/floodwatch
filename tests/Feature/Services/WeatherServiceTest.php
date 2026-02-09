<?php

namespace Tests\Feature\Services;

use App\Services\WeatherService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherServiceTest extends TestCase
{
    public function test_get_forecast_returns_daily_forecast(): void
    {
        Http::fake([
            '*/forecast*' => Http::response([
                'daily' => [
                    'time' => ['2025-02-08', '2025-02-09', '2025-02-10'],
                    'weathercode' => [0, 1, 61],
                    'temperature_2m_max' => [12.5, 11.0, 10.0],
                    'temperature_2m_min' => [5.0, 4.0, 3.0],
                    'precipitation_sum' => [0, 0, 2.5],
                ],
            ], 200),
        ]);

        $service = app(WeatherService::class);
        $result = $service->getForecast(51.0358, -2.8318);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame('2025-02-08', $result[0]['date']);
        $this->assertSame(12.5, $result[0]['temp_max']);
        $this->assertSame(5.0, $result[0]['temp_min']);
        $this->assertArrayHasKey('icon', $result[0]);
        $this->assertArrayHasKey('description', $result[0]);
    }

    public function test_get_forecast_returns_empty_on_api_failure(): void
    {
        Http::fake([
            '*/forecast*' => Http::response(null, 500),
        ]);

        $service = app(WeatherService::class);
        $result = $service->getForecast(51.0358, -2.8318);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_forecast_returns_empty_when_daily_missing(): void
    {
        Http::fake([
            '*/forecast*' => Http::response(['daily' => null], 200),
        ]);

        $service = app(WeatherService::class);
        $result = $service->getForecast(51.0358, -2.8318);

        $this->assertEmpty($result);
    }
}
