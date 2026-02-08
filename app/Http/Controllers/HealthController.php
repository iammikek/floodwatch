<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;
use Throwable;

class HealthController extends Controller
{
    private const HEALTH_TIMEOUT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        [$environmentAgency, $floodForecast, $weather, $nationalHighways] = Concurrency::run([
            fn () => $this->checkEnvironmentAgency(),
            fn () => $this->checkFloodForecast(),
            fn () => $this->checkWeather(),
            fn () => $this->checkNationalHighways(),
        ]);

        $checks = [
            'environment_agency' => $environmentAgency,
            'flood_forecast' => $floodForecast,
            'weather' => $weather,
            'national_highways' => $nationalHighways,
            'cache' => $this->checkCache(),
        ];

        $allOk = collect($checks)->every(fn (array $c) => $c['status'] === 'ok');
        $statusCode = $allOk ? 200 : 503;

        return response()->json([
            'status' => $allOk ? 'healthy' : 'degraded',
            'checks' => $checks,
        ], $statusCode);
    }

    private function checkEnvironmentAgency(): array
    {
        $baseUrl = config('flood-watch.environment_agency.base_url');
        $url = "{$baseUrl}/id/floods?lat=51&long=-2&dist=5";

        try {
            $response = Http::timeout(self::HEALTH_TIMEOUT)->get($url);

            return [
                'status' => $response->successful() ? 'ok' : 'degraded',
                'message' => $response->successful() ? null : "HTTP {$response->status()}",
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkFloodForecast(): array
    {
        $baseUrl = config('flood-watch.flood_forecast.base_url');
        $url = "{$baseUrl}/api/public/v1/statements/latest_public_forecast";

        try {
            $response = Http::timeout(self::HEALTH_TIMEOUT)->get($url);

            return [
                'status' => $response->successful() ? 'ok' : 'degraded',
                'message' => $response->successful() ? null : "HTTP {$response->status()}",
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkWeather(): array
    {
        $baseUrl = config('flood-watch.weather.base_url');
        $url = "{$baseUrl}/forecast?latitude=51&longitude=-2&current=temperature_2m";

        try {
            $response = Http::timeout(self::HEALTH_TIMEOUT)->get($url);

            return [
                'status' => $response->successful() ? 'ok' : 'degraded',
                'message' => $response->successful() ? null : "HTTP {$response->status()}",
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkNationalHighways(): array
    {
        $apiKey = config('flood-watch.national_highways.api_key');
        if (empty($apiKey)) {
            return ['status' => 'skipped', 'message' => 'API key not configured'];
        }

        $baseUrl = rtrim(config('flood-watch.national_highways.base_url'), '/');
        $closuresPath = ltrim(config('flood-watch.national_highways.closures_path', 'closures'), '/');
        $url = "{$baseUrl}/{$closuresPath}?closureType=planned";

        try {
            $response = Http::timeout(self::HEALTH_TIMEOUT)
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'X-Response-MediaType' => 'application/json',
                ])
                ->get($url);

            return [
                'status' => $response->successful() ? 'ok' : 'degraded',
                'message' => $response->successful() ? null : "HTTP {$response->status()}",
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        $store = config('flood-watch.cache_store', 'flood-watch');
        if ($store === 'array' || $store === 'flood-watch-array') {
            return ['status' => 'ok', 'message' => 'Using array store'];
        }

        try {
            $key = 'flood-watch:health:ping';
            Cache::store($store)->put($key, true, 10);
            $ok = Cache::store($store)->get($key) === true;
            Cache::store($store)->forget($key);

            return [
                'status' => $ok ? 'ok' : 'degraded',
                'message' => $ok ? null : 'Read/write failed',
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }
}
