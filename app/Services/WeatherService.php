<?php

namespace App\Services;

use App\Support\CircuitBreaker;
use App\Support\CircuitOpenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    public function __construct(
        protected ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->circuitBreaker ??= new CircuitBreaker('weather');
    }

    private const WMO_ICONS = [
        0 => '☀️',
        1 => '🌤️',
        2 => '⛅',
        3 => '☁️',
        45 => '🌫️',
        48 => '🌫️',
        51 => '🌧️',
        53 => '🌧️',
        55 => '🌧️',
        56 => '🌧️',
        57 => '🌧️',
        61 => '🌧️',
        63 => '🌧️',
        65 => '🌧️',
        66 => '🌧️',
        67 => '🌧️',
        71 => '❄️',
        73 => '❄️',
        75 => '❄️',
        77 => '❄️',
        80 => '🌦️',
        81 => '🌦️',
        82 => '🌦️',
        85 => '🌨️',
        86 => '🌨️',
        95 => '⛈️',
        96 => '⛈️',
        99 => '⛈️',
    ];

    /**
     * Fetch 5-day weather forecast from Open-Meteo (free, no API key).
     *
     * @return array<int, array{date: string, icon: string, temp_max: float, temp_min: float, precipitation: float, description: string}>
     */
    public function getForecast(float $lat, float $long): array
    {
        try {
            return $this->circuitBreaker->execute(fn () => $this->fetchForecast($lat, $long));
        } catch (CircuitOpenException) {
            return [];
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return array<int, array{date: string, icon: string, temp_max: float, temp_min: float, precipitation: float, description: string}>
     */
    private function fetchForecast(float $lat, float $long): array
    {
        $baseUrl = config('flood-watch.weather.base_url', 'https://api.open-meteo.com/v1');
        $timeout = config('flood-watch.weather.timeout', 10);
        $url = "{$baseUrl}/forecast?latitude={$lat}&longitude={$long}&daily=weathercode,temperature_2m_max,temperature_2m_min,precipitation_sum&timezone=Europe/London&forecast_days=5";

        $retryTimes = config('flood-watch.weather.retry_times', 3);
        $retrySleep = config('flood-watch.weather.retry_sleep_ms', 100);

        $response = Http::timeout($timeout)->retry($retryTimes, $retrySleep, null, false)->get($url);
        if (! $response->successful()) {
            $response->throw();
        }

        $data = $response->json();
        $daily = $data['daily'] ?? null;

        if ($daily === null || empty($daily['time'])) {
            return [];
        }

        $days = [];
        $times = $daily['time'];
        $codes = $daily['weathercode'] ?? [];
        $tempMax = $daily['temperature_2m_max'] ?? [];
        $tempMin = $daily['temperature_2m_min'] ?? [];
        $precip = $daily['precipitation_sum'] ?? [];

        foreach ($times as $i => $date) {
            $code = (int) ($codes[$i] ?? 0);
            $days[] = [
                'date' => $date,
                'icon' => $this->iconForCode($code),
                'temp_max' => (float) ($tempMax[$i] ?? 0),
                'temp_min' => (float) ($tempMin[$i] ?? 0),
                'precipitation' => (float) ($precip[$i] ?? 0),
                'description' => $this->descriptionForCode($code),
            ];
        }

        return $days;
    }

    private function iconForCode(int $code): string
    {
        if (isset(self::WMO_ICONS[$code])) {
            return self::WMO_ICONS[$code];
        }

        if ($code >= 4 && $code <= 49) {
            return '🌫️';
        }
        if ($code >= 50 && $code <= 69) {
            return '🌧️';
        }
        if ($code >= 70 && $code <= 79) {
            return '❄️';
        }
        if ($code >= 80 && $code <= 99) {
            return '⛈️';
        }

        return '🌤️';
    }

    private function descriptionForCode(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear',
            $code >= 1 && $code <= 3 => 'Cloudy',
            $code >= 45 && $code <= 48 => 'Foggy',
            $code >= 51 && $code <= 67 => 'Rain',
            $code >= 71 && $code <= 77 => 'Snow',
            $code >= 80 && $code <= 82 => 'Showers',
            $code >= 85 && $code <= 86 => 'Snow showers',
            $code >= 95 && $code <= 99 => 'Thunderstorm',
            default => 'Variable',
        };
    }
}
