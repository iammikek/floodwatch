<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NationalHighwaysService
{
    /**
     * Get road and lane closure incidents for Somerset Levels routes (A361, A372, M5 J23–J25).
     *
     * @return array<int, array{road?: string, status?: string, incidentType?: string, delayTime?: string}>
     */
    public function getIncidents(): array
    {
        $apiKey = config('flood-watch.national_highways.api_key');

        if (empty($apiKey)) {
            return [];
        }

        $baseUrl = rtrim(config('flood-watch.national_highways.base_url'), '/');
        $timeout = config('flood-watch.national_highways.timeout');

        $url = "{$baseUrl}/road-lane-closures/v2/planned";

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $apiKey,
            ])
            ->get($url);

        if (! $response->successful()) {
            return [];
        }

        return $this->parseIncidents($response->json());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{road?: string, status?: string, incidentType?: string, delayTime?: string}>
     */
    private function parseIncidents(array $data): array
    {
        $incidents = [];

        $closures = $data['closure']['closure'] ?? $data['closures'] ?? $data['items'] ?? [];

        foreach ((array) $closures as $closure) {
            if (! is_array($closure)) {
                continue;
            }

            $incidents[] = [
                'road' => $closure['road'] ?? $closure['roadName'] ?? $closure['location'] ?? '',
                'status' => $closure['status'] ?? $closure['closureStatus'] ?? '',
                'incidentType' => $closure['incidentType'] ?? $closure['type'] ?? '',
                'delayTime' => $closure['delayTime'] ?? $closure['delay'] ?? '',
            ];
        }

        return $incidents;
    }
}
