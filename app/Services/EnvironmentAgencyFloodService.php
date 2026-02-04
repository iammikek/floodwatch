<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class EnvironmentAgencyFloodService
{
    public function getFloods(
        ?float $lat = null,
        ?float $long = null,
        ?int $radiusKm = null
    ): array {
        $lat ??= config('flood-watch.default_lat');
        $long ??= config('flood-watch.default_long');
        $radiusKm ??= config('flood-watch.default_radius_km');

        $baseUrl = config('flood-watch.environment_agency.base_url');
        $timeout = config('flood-watch.environment_agency.timeout');
        $url = "{$baseUrl}/id/floods?lat={$lat}&long={$long}&dist={$radiusKm}";

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
        $areaCentroids = $this->fetchFloodAreaCentroids($baseUrl, $timeout, $lat, $long, $radiusKm);

        return array_map(function (array $item) use ($areaCentroids) {
            $areaId = $item['floodAreaID'] ?? '';
            $centroid = $areaCentroids[$areaId] ?? null;

            return [
                'description' => $item['description'] ?? '',
                'severity' => $item['severity'] ?? '',
                'severityLevel' => $item['severityLevel'] ?? 0,
                'message' => $item['message'] ?? '',
                'floodAreaID' => $areaId,
                'timeRaised' => $item['timeRaised'] ?? null,
                'timeMessageChanged' => $item['timeMessageChanged'] ?? null,
                'timeSeverityChanged' => $item['timeSeverityChanged'] ?? null,
                'lat' => $centroid['lat'] ?? null,
                'long' => $centroid['long'] ?? null,
            ];
        }, $items);
    }

    /**
     * Fetch flood area centroids for cross-referencing with location.
     *
     * @return array<string, array{lat: float, long: float}>
     */
    private function fetchFloodAreaCentroids(string $baseUrl, int $timeout, float $lat, float $long, int $radiusKm): array
    {
        $url = "{$baseUrl}/id/floodAreas?lat={$lat}&long={$long}&dist={$radiusKm}&_limit=200";

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        $items = $data['items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $centroids = [];
        foreach ($items as $item) {
            $notation = $item['notation'] ?? $item['fwdCode'] ?? null;
            $itemLat = $item['lat'] ?? null;
            $itemLong = $item['long'] ?? null;
            if ($notation !== null && $notation !== '' && $itemLat !== null && $itemLong !== null) {
                $centroids[(string) $notation] = [
                    'lat' => (float) $itemLat,
                    'long' => (float) $itemLong,
                ];
            }
        }

        return $centroids;
    }
}
