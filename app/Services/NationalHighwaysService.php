<?php

namespace App\Services;

use App\DTOs\RoadIncident;
use Illuminate\Support\Facades\Http;

class NationalHighwaysService
{
    /**
     * Get road and lane closure incidents for South West routes (M5, A38, A30, A303, A361, A372, etc.).
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

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                ])
                ->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            report($e);

            return [];
        }

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

            $incidents[] = RoadIncident::fromArray($closure)->toArray();
        }

        return $incidents;
    }
}
