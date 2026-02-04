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
     * @return array<int, array{notation: string, label: string, riverName: string, town: string, lat: float, long: float, stationType: string, typicalRangeLow?: float, typicalRangeHigh?: float}>
     */
    private function fetchStations(string $baseUrl, int $timeout, float $lat, float $long, int $radiusKm): array
    {
        $url = "{$baseUrl}/id/stations?lat={$lat}&long={$long}&dist={$radiusKm}&_view=full";

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
            $itemLat = $item['lat'] ?? null;
            $itemLong = $item['long'] ?? null;
            if ($itemLat === null || $itemLong === null) {
                continue;
            }
            $label = $item['label'] ?? '';
            $stationType = $this->detectStationType($label, $item['type'] ?? []);
            $stageScale = $item['stageScale'] ?? [];
            $typicalLow = isset($stageScale['typicalRangeLow']) ? (float) $stageScale['typicalRangeLow'] : null;
            $typicalHigh = isset($stageScale['typicalRangeHigh']) ? (float) $stageScale['typicalRangeHigh'] : null;

            $result[] = [
                'notation' => (string) $notation,
                'label' => $label,
                'riverName' => $item['riverName'] ?? '',
                'town' => $item['town'] ?? '',
                'lat' => (float) $itemLat,
                'long' => (float) $itemLong,
                'stationType' => $stationType,
                'typicalRangeLow' => $typicalLow,
                'typicalRangeHigh' => $typicalHigh,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $apiTypes
     */
    private function detectStationType(string $label, array $apiTypes): string
    {
        $labelLower = strtolower($label);
        if (str_contains($labelLower, 'pumping station')) {
            return 'pumping_station';
        }
        if (str_contains($labelLower, 'barrier') || str_contains($labelLower, 'saltmoor')) {
            return 'barrier';
        }
        if (str_contains($labelLower, 'drain')) {
            return 'drain';
        }
        foreach ($apiTypes as $t) {
            if (str_contains((string) $t, 'Coastal')) {
                return 'coastal';
            }
            if (str_contains((string) $t, 'Groundwater')) {
                return 'groundwater';
            }
        }

        return 'river_gauge';
    }

    /**
     * @param  array<int, array{notation: string, label: string, riverName: string, town: string, lat: float, long: float}>  $stations
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
     * @param  array<int, array{notation: string, label: string, riverName: string, town: string, lat: float, long: float, stationType: string, typicalRangeLow?: float|null, typicalRangeHigh?: float|null}>  $stations
     * @param  array<string, array{value: float, unitName: string, dateTime: string}>  $readings
     * @return array<int, array{station: string, river: string, town: string, value: float, unit: string, unitName: string, dateTime: string, lat: float, long: float, stationType: string, levelStatus: string, typicalRangeLow?: float, typicalRangeHigh?: float}>
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

            $value = $reading['value'];
            $typicalLow = $station['typicalRangeLow'] ?? null;
            $typicalHigh = $station['typicalRangeHigh'] ?? null;
            $levelStatus = $this->computeLevelStatus($value, $typicalLow, $typicalHigh);

            $item = [
                'station' => $station['label'],
                'river' => $station['riverName'],
                'town' => $station['town'],
                'value' => $value,
                'unit' => $reading['unitName'],
                'unitName' => $reading['unitName'],
                'dateTime' => $reading['dateTime'],
                'lat' => $station['lat'],
                'long' => $station['long'],
                'stationType' => $station['stationType'],
                'levelStatus' => $levelStatus,
            ];
            if ($typicalLow !== null) {
                $item['typicalRangeLow'] = $typicalLow;
            }
            if ($typicalHigh !== null) {
                $item['typicalRangeHigh'] = $typicalHigh;
            }
            $result[] = $item;
        }

        return $result;
    }

    private function computeLevelStatus(float $value, ?float $typicalLow, ?float $typicalHigh): string
    {
        if ($typicalLow === null || $typicalHigh === null) {
            return 'unknown';
        }
        if ($value > $typicalHigh) {
            return 'elevated';
        }
        if ($value < $typicalLow) {
            return 'low';
        }

        return 'expected';
    }
}
