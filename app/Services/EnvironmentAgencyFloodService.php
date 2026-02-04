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

        return array_map(fn (array $item) => [
            'description' => $item['description'] ?? '',
            'severity' => $item['severity'] ?? '',
            'severityLevel' => $item['severityLevel'] ?? 0,
            'message' => $item['message'] ?? '',
            'floodAreaID' => $item['floodAreaID'] ?? '',
        ], $items);
    }
}
