<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FloodForecastService
{
    /**
     * Fetch the latest 5-day flood risk forecast from the Flood Guidance Statement API.
     *
     * @return array{issued_at: string, england_forecast: string, flood_risk_trend: array<string, string>, sources: array<string, string>}|array{}
     */
    public function getForecast(): array
    {
        $baseUrl = config('flood-watch.flood_forecast.base_url');
        $timeout = config('flood-watch.flood_forecast.timeout', 25);
        $url = "{$baseUrl}/api/public/v1/statements/latest_public_forecast";

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            report($e);

            return [];
        }

        if (! $response->successful()) {
            return [];
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
