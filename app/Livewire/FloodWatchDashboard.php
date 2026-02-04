<?php

namespace App\Livewire;

use App\Services\FloodWatchService;
use App\Services\LocationResolver;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FloodWatchDashboard extends Component
{
    public string $location = '';

    public bool $loading = false;

    public ?string $assistantResponse = null;

    public array $floods = [];

    public array $incidents = [];

    public array $forecast = [];

    public array $weather = [];

    public array $riverLevels = [];

    public ?array $mapCenter = null;

    public bool $hasUserLocation = false;

    public ?string $lastChecked = null;

    public ?string $error = null;

    public function search(FloodWatchService $assistant, LocationResolver $locationResolver): void
    {
        $this->reset(['assistantResponse', 'floods', 'incidents', 'forecast', 'weather', 'riverLevels', 'mapCenter', 'hasUserLocation', 'lastChecked', 'error']);
        $this->loading = true;

        $locationTrimmed = trim($this->location);

        $validation = null;
        if ($locationTrimmed !== '') {
            $validation = $locationResolver->resolve($locationTrimmed);
            if (! $validation['valid']) {
                $this->error = $validation['error'] ?? 'Invalid location.';
                $this->loading = false;

                return;
            }
            if (! $validation['in_area']) {
                $this->error = $validation['error'] ?? 'This location is outside the South West.';
                $this->loading = false;

                return;
            }
        }

        $message = $this->buildMessage($locationTrimmed, $validation);
        $cacheKey = $locationTrimmed !== '' ? $locationTrimmed : null;
        $userLat = $validation['lat'] ?? null;
        $userLong = $validation['long'] ?? null;
        $region = $validation['region'] ?? null;

        try {
            $result = $assistant->chat($message, [], $cacheKey, $userLat, $userLong, $region);
            $this->assistantResponse = $result['response'];
            $this->floods = $this->enrichFloodsWithDistance(
                $result['floods'],
                $userLat,
                $userLong
            );
            $this->incidents = $result['incidents'];
            $this->forecast = $result['forecast'] ?? [];
            $this->weather = $result['weather'] ?? [];
            $this->riverLevels = $result['riverLevels'] ?? [];
            $lat = $userLat ?? config('flood-watch.default_lat');
            $long = $userLong ?? config('flood-watch.default_long');
            $this->mapCenter = ['lat' => $lat, 'long' => $long];
            $this->hasUserLocation = $userLat !== null && $userLong !== null;
            $this->lastChecked = $result['lastChecked'] ?? null;

            $this->dispatch('search-completed');
        } catch (\Throwable $e) {
            report($e);
            $this->error = $this->formatErrorMessage($e);
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Enrich floods with distance from user location and sort by proximity (closest first).
     *
     * @param  array<int, array<string, mixed>>  $floods
     * @return array<int, array<string, mixed>>
     */
    private function enrichFloodsWithDistance(array $floods, ?float $userLat, ?float $userLong): array
    {
        $hasCenter = $userLat !== null && $userLong !== null;

        $enriched = array_map(function (array $flood) use ($userLat, $userLong, $hasCenter) {
            $floodLat = $flood['lat'] ?? null;
            $floodLong = $flood['long'] ?? null;
            $flood['distanceKm'] = null;
            if ($hasCenter && $floodLat !== null && $floodLong !== null) {
                $flood['distanceKm'] = round($this->haversineDistanceKm($userLat, $userLong, (float) $floodLat, (float) $floodLong), 1);
            }

            return $flood;
        }, $floods);

        if ($hasCenter) {
            return collect($enriched)
                ->sortBy(fn (array $f) => $f['distanceKm'] ?? PHP_FLOAT_MAX)
                ->values()
                ->all();
        }

        return collect($enriched)
            ->sortByDesc(fn (array $f) => $f['timeMessageChanged'] ?? $f['timeRaised'] ?? '')
            ->values()
            ->all();
    }

    private function haversineDistanceKm(float $lat1, float $long1, float $lat2, float $long2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLong = deg2rad($long2 - $long1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLong / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private function formatErrorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, 'timed out') || str_contains($message, 'cURL error 28') || str_contains($message, 'Operation timed out')) {
            return 'The request took too long. The AI service may be busy. Please try again in a moment.';
        }
        if (str_contains($message, 'Connection') && (str_contains($message, 'refused') || str_contains($message, 'reset'))) {
            return 'Unable to reach the service. Please check your connection and try again.';
        }
        if (config('app.debug')) {
            return $message;
        }

        return 'Unable to get a response. Please try again.';
    }

    /**
     * @param  array{lat?: float, long?: float, outcode?: string, display_name?: string}|null  $validation
     */
    private function buildMessage(string $location, ?array $validation): string
    {
        if ($location === '') {
            return 'Check flood and road status for the South West (Bristol, Somerset, Devon, Cornwall).';
        }

        $label = $validation['display_name'] ?? $location;
        $coords = '';
        if ($validation !== null && isset($validation['lat'], $validation['long'])) {
            $coords = sprintf(' (lat: %.4f, long: %.4f)', $validation['lat'], $validation['long']);
        }

        return "Check flood and road status for {$label}{$coords} in the South West.";
    }

    public function render()
    {
        return view('livewire.flood-watch-dashboard');
    }
}
