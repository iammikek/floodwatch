<?php

namespace App\Flood\Services;

use App\Services\DataLakeClient;
use App\Support\CircuitBreaker;
use App\Support\CircuitOpenException;
use App\Support\CoordinateMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches river and sea level data from the Environment Agency Real-Time API.
 * This powers the Check for flooding service: https://check-for-flooding.service.gov.uk/river-and-sea-levels
 */
class RiverLevelService
{
    private const int MAX_STATIONS = 15;

    public function __construct(
        protected ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->circuitBreaker ??= new CircuitBreaker('environment_agency');
    }

    /**
     * Fetch latest river and sea levels for monitoring stations near the given coordinates.
     * Results are cached by rounded lat/lng and radius to reduce EA API load for map pan/zoom.
     *
     * @return array<int, array{station: string, river: string, town: string, value: float, unit: string, unitName: string, dateTime: string}>
     */
    public function getLevels(
        ?float $lat = null,
        ?float $lng = null,
        ?int $radiusKm = null
    ): array {
        if (config('flood-watch.use_data_lake', false) === true) {
            $lat ??= (float) config('flood-watch.default_lat');
            $lng ??= (float) config('flood-watch.default_lng');
            $radiusKm ??= (int) config('flood-watch.default_radius_km');
            $cacheMinutes = (int) config('flood-watch.river_levels_cache_minutes', 0);
            $key = $this->riverLevelsCacheKey($lat, $lng, $radiusKm);
            $store = config('flood-watch.cache_store', 'flood-watch');
            if ($cacheMinutes > 0) {
                $cached = Cache::store($store)->get($key);
                if (is_array($cached)) {
                    return $cached;
                }
            }
            $result = $this->getLevelsFromDataLake($lat, $lng, $radiusKm);
            if ($cacheMinutes > 0) {
                Cache::store($store)->put($key, $result, now()->addMinutes($cacheMinutes));
            }

            return $result;
        }

        $lat ??= config('flood-watch.default_lat');
        $lng ??= config('flood-watch.default_lng');
        $radiusKm ??= config('flood-watch.default_radius_km');

        $cacheMinutes = config('flood-watch.river_levels_cache_minutes', 0);
        if ($cacheMinutes > 0) {
            $key = $this->riverLevelsCacheKey($lat, $lng, $radiusKm);
            $store = config('flood-watch.cache_store', 'flood-watch');
            $cached = Cache::store($store)->get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $result = $this->circuitBreaker->execute(function () use ($lat, $lng, $radiusKm) {
                $baseUrl = config('flood-watch.environment_agency.base_url');
                $timeout = config('flood-watch.environment_agency.timeout');

                $stations = $this->fetchStations($baseUrl, $timeout, $lat, $lng, $radiusKm);
                if (empty($stations)) {
                    return [];
                }

                $stations = array_slice($stations, 0, self::MAX_STATIONS);
                $readings = $this->fetchReadings($baseUrl, $timeout, $stations);

                return $this->mergeStationsWithReadings($stations, $readings);
            });

            if ($cacheMinutes > 0) {
                $key = $this->riverLevelsCacheKey($lat, $lng, $radiusKm);
                $store = config('flood-watch.cache_store', 'flood-watch');
                Cache::store($store)->put($key, $result, now()->addMinutes($cacheMinutes));
            }

            return $result;
        } catch (CircuitOpenException) {
            return [];
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return [];
        }
    }

    private function riverLevelsCacheKey(float $lat, float $lng, int $radiusKm): string
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
        $latRounded = round($lat, 2);
        $lngRounded = round($lng, 2);

        return "{$prefix}:river-levels:{$latRounded}:{$lngRounded}:{$radiusKm}";
    }

    /**
     * @return array<int, array{notation: string, label: string, riverName: string, town: string, lat: float, lng: float, stationType: string, typicalRangeLow?: float, typicalRangeHigh?: float}>
     *
     * @deprecated Replaced by Data Lake measurements when flood-watch.use_data_lake=true
     */
    private function fetchStations(string $baseUrl, int $timeout, float $lat, float $lng, int $radiusKm): array
    {
        $url = "{$baseUrl}/id/stations?lat={$lat}&long={$lng}&dist={$radiusKm}&_view=full";

        $retryTimes = config('flood-watch.environment_agency.retry_times', 3);
        $retrySleep = config('flood-watch.environment_agency.retry_sleep_ms', 100);

        try {
            $response = Http::timeout($timeout)->retry($retryTimes, $retrySleep, null, false)->get($url);
            if (! $response->successful()) {
                $response->throw();
            }
        } catch (Throwable $e) {
            Log::error('FloodWatch EA stations fetch failed', [
                'provider' => 'environment_agency',
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $data = $response->json();
        $items = $data['items'] ?? [];

        $result = [];
        foreach ($items as $item) {
            $notation = $item['notation'] ?? $item['stationReference'] ?? null;
            if (empty($notation)) {
                continue;
            }
            $coords = CoordinateMapper::normalize($item);
            if ($coords['lat'] === null || $coords['lng'] === null) {
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
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
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
     * @param  array<int, array{notation: string, label: string, riverName: string, town: string, lat: float, lng: float}>  $stations
     * @return array<string, array{value: float, unitName: string, dateTime: string}>
     *
     * @deprecated Replaced by Data Lake measurements when flood-watch.use_data_lake=true
     */
    private function fetchReadings(string $baseUrl, int $timeout, array $stations): array
    {
        $requests = [];
        foreach ($stations as $station) {
            $notation = $station['notation'];
            $requests[$notation] = "{$baseUrl}/id/stations/{$notation}/readings?latest&_sorted";
        }

        $responses = Http::timeout($timeout)->pool(function ($pool) use ($requests) {
            foreach ($requests as $notation => $url) {
                $pool->as($notation)->get($url);
            }
        });

        $readings = [];
        foreach ($responses as $notation => $response) {
            if ($response instanceof Throwable || ! $response->successful()) {
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
     * @param  array<int, array{notation: string, label: string, riverName: string, town: string, lat: float, lng: float, stationType: string, typicalRangeLow?: float|null, typicalRangeHigh?: float|null}>  $stations
     * @param  array<string, array{value: float, unitName: string, dateTime: string}>  $readings
     * @return array<int, array{station: string, river: string, town: string, value: float, unit: string, unitName: string, dateTime: string, lat: float, lng: float, stationType: string, levelStatus: string, typicalRangeLow?: float, typicalRangeHigh?: float}>
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
                'lng' => $station['lng'],
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

    /**
     * Data Lake integration behind feature flag. Returns same shape as EA flow.
     *
     * @return array<int, array{station: string, river: string, town: string, value: float, unit: string, unitName: string, dateTime: string, lat: float, lng: float, stationType: string, levelStatus: string, typicalRangeLow?: float|null, typicalRangeHigh?: float|null}>
     */
    private function getLevelsFromDataLake(float $lat, float $lng, int $radiusKm): array
    {
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.001));
        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;
        $bbox = "{$minLng},{$minLat},{$maxLng},{$maxLat}";

        $store = config('flood-watch.cache_store', 'flood-watch');
        $ttlMinutes = (int) config('flood-watch.cache_ttl_minutes', 0);
        $aggregate = 'raw';
        $cacheKeyPrefix = config('flood-watch.cache_key_prefix', 'flood-watch').':lake:measurements:';
        $cacheKey = "{$cacheKeyPrefix}{$bbox}:{$aggregate}";
        $cached = null;
        if ($ttlMinutes > 0) {
            $cached = Cache::store($store)->get($cacheKey);
        }
        $ifNoneMatch = is_array($cached) && isset($cached['etag']) ? (string) $cached['etag'] : null;

        try {
            $client = new DataLakeClient;
            $resp = $client->getMeasurements(bbox: $bbox, aggregate: $aggregate, page: 1, limit: 200, ifNoneMatch: $ifNoneMatch);
        } catch (Throwable $e) {
            Log::error('FloodWatch Data Lake measurements fetch failed', [
                'provider' => 'data_lake',
                'bbox' => $bbox,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
        if ($resp->status === 304 && is_array($cached) && isset($cached['body']) && is_array($cached['body'])) {
            $items = $cached['body']['items'] ?? [];
        } else {
            if ($resp->status !== 200 || ! is_array($resp->body)) {
                return [];
            }
            $items = $resp->body['items'] ?? [];
            if ($ttlMinutes > 0 && $resp->etag) {
                Cache::store($store)->put($cacheKey, ['etag' => $resp->etag, 'body' => $resp->body], now()->addMinutes($ttlMinutes));
            }
        }
        if (! is_array($items) || empty($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $value = isset($it['value']) ? (float) $it['value'] : null;
            $dateTime = isset($it['dateTime']) ? (string) $it['dateTime'] : null;
            $latV = isset($it['lat']) ? (float) $it['lat'] : null;
            $lngV = isset($it['lng']) ? (float) $it['lng'] : null;
            if ($value === null || $dateTime === null || $latV === null || $lngV === null) {
                continue;
            }
            $stationLabel = (string) ($it['station_label'] ?? $it['station'] ?? 'Unknown Station');
            $riverName = (string) ($it['river'] ?? '');
            $townName = (string) ($it['town'] ?? '');
            $unitName = (string) ($it['unitName'] ?? 'm');

            $typicalLow = isset($it['typicalRangeLow']) ? (float) $it['typicalRangeLow'] : null;
            $typicalHigh = isset($it['typicalRangeHigh']) ? (float) $it['typicalRangeHigh'] : null;
            $levelStatus = $this->computeLevelStatus($value, $typicalLow, $typicalHigh);
            $stationType = (string) ($it['stationType'] ?? 'river_gauge');

            $row = [
                'station' => $stationLabel,
                'river' => $riverName,
                'town' => $townName,
                'value' => $value,
                'unit' => $unitName,
                'unitName' => $unitName,
                'dateTime' => $dateTime,
                'lat' => $latV,
                'lng' => $lngV,
                'stationType' => $stationType,
                'levelStatus' => $levelStatus,
            ];
            if ($typicalLow !== null) {
                $row['typicalRangeLow'] = $typicalLow;
            }
            if ($typicalHigh !== null) {
                $row['typicalRangeHigh'] = $typicalHigh;
            }
            $result[] = $row;
        }

        return $result;
    }
}
