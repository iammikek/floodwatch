<?php

namespace App\Roads\Services;

use App\Roads\DTOs\RoadIncident;
use App\Support\CircuitBreaker;
use App\Support\CircuitOpenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class NationalHighwaysService
{
    public function __construct(
        protected ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->circuitBreaker ??= new CircuitBreaker('national_highways');
    }

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

        try {
            return $this->circuitBreaker->execute(fn () => $this->fetchIncidents($apiKey));
        } catch (CircuitOpenException) {
            return [];
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return array<int, array{road?: string, status?: string, incidentType?: string, delayTime?: string}>
     */
    private function fetchIncidents(string $apiKey): array
    {
        $baseUrl = rtrim(config('flood-watch.national_highways.base_url'), '/');
        $timeout = config('flood-watch.national_highways.timeout');

        $url = "{$baseUrl}/road-lane-closures/v2/planned";

        $retryTimes = config('flood-watch.national_highways.retry_times', 3);
        $retrySleep = config('flood-watch.national_highways.retry_sleep_ms', 100);

        $response = Http::timeout($timeout)
            ->retry($retryTimes, $retrySleep, null, false)
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $apiKey,
            ])
            ->get($url);

        if (! $response->successful()) {
            $response->throw();
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
