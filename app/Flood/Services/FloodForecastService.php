<?php

namespace App\Flood\Services;

use App\Support\CircuitBreaker;
use App\Support\CircuitOpenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class FloodForecastService
{
    public function __construct(
        protected ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->circuitBreaker ??= new CircuitBreaker('flood_forecast');
    }

    /**
     * Fetch the latest 5-day flood risk forecast from the Flood Guidance Statement API.
     *
     * @return array{issued_at: string, england_forecast: string, flood_risk_trend: array<string, string>, sources: array<string, string>}|array{}
     */
    public function getForecast(): array
    {
        try {
            return $this->circuitBreaker->execute(fn () => $this->fetchForecast());
        } catch (CircuitOpenException) {
            return [];
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return array{issued_at: string, england_forecast: string, flood_risk_trend: array<string, string>, sources: array<string, string>}|array{}
     */
    private function fetchForecast(): array
    {
        $baseUrl = config('flood-watch.flood_forecast.base_url');
        $timeout = config('flood-watch.flood_forecast.timeout', 25);
        $url = "{$baseUrl}/api/public/v1/statements/latest_public_forecast";

        $retryTimes = config('flood-watch.flood_forecast.retry_times', 3);
        $retrySleep = config('flood-watch.flood_forecast.retry_sleep_ms', 100);

        $response = Http::timeout($timeout)->retry($retryTimes, $retrySleep, null, false)->get($url);
        if (! $response->successful()) {
            $response->throw();
        }

        $data = $response->json();
        $statement = $data['statement'] ?? null;

        if ($statement === null) {
            return [];
        }

        $sources = [];
        foreach ($statement['sources'] ?? [] as $source) {
            foreach ($source as $key => $value) {
                if (is_string($value)) {
                    $sources[$key] = trim($value);
                }
            }
        }

        $publicForecast = $statement['public_forecast'] ?? [];
        $floodRiskTrend = $statement['flood_risk_trend'] ?? [];

        return [
            'issued_at' => $statement['issued_at'] ?? '',
            'england_forecast' => $publicForecast['england_forecast'] ?? '',
            'flood_risk_trend' => $floodRiskTrend,
            'sources' => $sources,
        ];
    }
}
