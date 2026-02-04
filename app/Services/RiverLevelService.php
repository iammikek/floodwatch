<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Fetches river and sea level data from the Environment Agency Real-Time API.
 * This powers the Check for flooding service: https://check-for-flooding.service.gov.uk/river-and-sea-levels
 */
class RiverLevelService
{
    private const MAX_STATIONS = 15;

    /**
     * Fetch latest river and sea levels for monitoring stations near the given coordinates.
     *
     * @return array<int, array{station: string, river: string, town: string, value: float, unit: string, unitName: string, dateTime: string}>
     */
    public function getLevels(
        ?float $lat = null,
        ?float $long = null,
        ?int $radiusKm = null
    ): array {
        $lat ??= config('flood-watch.default_lat');
        $long ??= config('flood-watch.default_long');
        $radiusKm ??= config('flood-watch.default_radius_km');

        $baseUrl = config('flood-watch.environment_agency.base_url');
        $timeout = config('flood-watch.environment_agency.timeout');

        $stations = $this->fetchStations($baseUrl, $timeout, $lat, $long, $radiusKm);
        if (empty($stations)) {
            return [];
        }

        $stations = array_slice($stations, 0, self::MAX_STATIONS);
        $readings = $this->fetchReadings($baseUrl, $timeout, $stations);

        return $this->mergeStationsWithReadings($stations, $readings);
    }

    /**
     * @return array<int, array{notation: string, label: string, riverName: string, town: string}>
     */
    private function fetchStations(string $baseUrl, int $timeout, float $lat, float $long, int $radiusKm): array
    {
        $url = "{$baseUrl}/id/stations?lat={$lat}&long={$long}&dist={$radiusKm}";

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
        $items = $data['items'] ?? [];

        $result = [];
        foreach ($items as $item) {
            $notation = $item['notation'] ?? $item['stationReference'] ?? null;
            if (empty($notation)) {
                continue;
            }
            $result[] = [
                'notation' => (string) $notation,
                'label' => $item['label'] ?? '',
                'riverName' => $item['riverName'] ?? '',
                'town' => $item['town'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array{notation: string, label: string, riverName: string, town: string}>  $stations
     * @return array<string, array{value: float, unitName: string, dateTime: string}>
     */
    private function fetchReadings(string $baseUrl, int $timeout, array $stations): array
    {
        $requests = [];
        foreach ($stations as $station) {
            $notation = $station['notation'];
            $requests[$notation] = "{$baseUrl}/id/stations/{$notation}/readings?latest&_sorted";
        }

        try {
            $responses = Http::timeout($timeout)->pool(function ($pool) use ($requests) {
                foreach ($requests as $notation => $url) {
                    $pool->as($notation)->get($url);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $readings = [];
        foreach ($responses as $notation => $response) {
            if ($response instanceof \Throwable || ! $response->successful()) {
                continue;
            }

            $data = $response->json();
            $items = $data['items'] ?? [];
            $latest = $items[0] ?? null;

            if ($latest !== null && isset($latest['value'], $latest['dateTime'])) {
                $measure = $latest['measure'] ?? '';
                $unitName = $this->extractUnitFromMeasure($measure);

                $readings[$notation] = [
                    'value' => (float) $latest['value'],
                    'unitName' => $unitName,
                    'dateTime' => $latest['dateTime'] ?? '',
                ];
            }
        }

        return $readings;
    }

    private function extractUnitFromMeasure(string $measure): string
    {
        if (str_contains($measure, 'mASD') || str_contains($measure, 'mAOD')) {
            return 'm';
        }
        if (str_contains($measure, 'm')) {
            return 'm';
        }

        return 'm';
    }

    /**
     * @param  array<int, array{notation: string, label: string, riverName: string, town: string}>  $stations
     * @param  array<string, array{value: float, unitName: string, dateTime: string}>  $readings
     * @return array<int, array{station: string, river: string, town: string, value: float, unit: string, unitName: string, dateTime: string}>
     */
    private function mergeStationsWithReadings(array $stations, array $readings): array
    {
        $result = [];

        foreach ($stations as $station) {
            $notation = $station['notation'];
            $reading = $readings[$notation] ?? null;

            if ($reading === null) {
                continue;
            }

            $result[] = [
                'station' => $station['label'],
                'river' => $station['riverName'],
                'town' => $station['town'],
                'value' => $reading['value'],
                'unit' => $reading['unitName'],
                'unitName' => $reading['unitName'],
                'dateTime' => $reading['dateTime'],
            ];
        }

        return $result;
    }
}
