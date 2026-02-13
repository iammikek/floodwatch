<?php

namespace App\Services;

use App\Enums\Region;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves location strings (postcodes or place names) to coordinates and region.
 * Uses postcodes.io for UK postcodes and Nominatim (OpenStreetMap) for place names.
 */
class LocationResolver
{
    /**
     * South West UK bounding box (lon min, lat min, lon max, lat max) for Nominatim viewbox.
     */
    private const string SOUTH_WEST_VIEWBOX = '-5.7,50.0,-2.2,51.6';

    public function __construct(
        protected PostcodeValidator $postcodeValidator
    ) {}

    /**
     * Resolve a location string (postcode or place name) to coordinates and region.
     *
     * @return array{valid: bool, in_area: bool, error?: string, lat?: float, lng?: float, region?: string, outcode?: string, display_name?: string}
     */
    public function resolve(string $input): array
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            return [
                'valid' => false,
                'in_area' => false,
                'error' => __('flood-watch.errors.invalid_location'),
            ];
        }

        $normalized = $this->postcodeValidator->normalize($trimmed);
        if ($this->postcodeValidator->matchesUkFormat($normalized) || $this->postcodeValidator->matchesOutcodeOnly($normalized)) {
            return $this->postcodeValidator->validate($trimmed, geocode: true);
        }

        return $this->geocodePlaceName($trimmed);
    }

    /**
     * Geocode a place name via Nominatim (OpenStreetMap).
     *
     * @return array{valid: bool, in_area: bool, error?: string, lat?: float, lng?: float, region?: string, display_name?: string}
     */
    private function geocodePlaceName(string $placeName): array
    {
        $url = 'https://nominatim.openstreetmap.org/search';
        $params = [
            'q' => $placeName.', UK',
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
            'countrycodes' => 'gb',
            'viewbox' => self::SOUTH_WEST_VIEWBOX,
            'bounded' => 0,
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => config('app.name').'/1.0'])
                ->get($url, $params);

            if ($response->tooManyRequests()) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'error' => __('flood-watch.errors.rate_limit'),
                ];
            }

            if (! $response->successful()) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'error' => __('flood-watch.bookmarks.unable_to_resolve'),
                ];
            }

            $results = $response->json();
            $first = $results[0] ?? null;

            if ($first === null) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'error' => __('flood-watch.bookmarks.unable_to_resolve'),
                ];
            }

            $lat = isset($first['lat']) ? (float) $first['lat'] : null;
            $lon = isset($first['lon']) ? (float) $first['lon'] : null;

            if ($lat === null || $lon === null) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'error' => __('flood-watch.bookmarks.unable_to_resolve'),
                ];
            }

            $address = $first['address'] ?? [];
            $displayName = $first['display_name'] ?? $placeName;
            $inArea = $this->isInSouthWest($lat, $lon, $address);
            $region = $this->getRegionFromAddress($address);

            if (! $inArea) {
                return [
                    'valid' => true,
                    'in_area' => false,
                    'error' => __('flood-watch.errors.outside_area'),
                    'lat' => $lat,
                    'lng' => $lon,
                    'display_name' => $displayName,
                ];
            }

            return [
                'valid' => true,
                'in_area' => true,
                'lat' => $lat,
                'lng' => $lon,
                'region' => $region,
                'display_name' => $displayName,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'valid' => false,
                'in_area' => false,
                'error' => __('flood-watch.bookmarks.unable_to_resolve'),
            ];
        }
    }

    private function isInSouthWest(float $lat, float $lon, array $address): bool
    {
        if ($this->isInSouthWestBoundingBox($lat, $lon)) {
            return true;
        }

        return $this->addressIndicatesSouthWest($address);
    }

    private function isInSouthWestBoundingBox(float $lat, float $lon): bool
    {
        return $lat >= 50.0 && $lat <= 51.6 && $lon >= -5.7 && $lon <= -2.2;
    }

    private function addressIndicatesSouthWest(array $address): bool
    {
        $searchable = strtolower(implode(' ', array_values($address)));

        foreach (Region::indicators() as $indicator) {
            if (str_contains($searchable, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reverse geocode coordinates to a location string and region.
     *
     * @return array{valid: bool, in_area: bool, location: string, region: ?string, error: ?string}
     */
    public function reverseFromCoords(float $lat, float $lng): array
    {
        $url = 'https://nominatim.openstreetmap.org/reverse';
        $params = [
            'lat' => $lat,
            'lon' => $lng,
            'format' => 'json',
            'addressdetails' => 1,
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => config('app.name').'/1.0'])
                ->get($url, $params);

            if ($response->tooManyRequests()) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'location' => '',
                    'region' => null,
                    'error' => __('flood-watch.errors.rate_limit'),
                ];
            }

            if (! $response->successful()) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'location' => '',
                    'region' => null,
                    'error' => __('flood-watch.dashboard.gps_error'),
                ];
            }

            $data = $response->json();
            $address = $data['address'] ?? [];
            $displayName = $data['display_name'] ?? '';

            if ($displayName === '') {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'location' => '',
                    'region' => null,
                    'error' => __('flood-watch.dashboard.gps_error'),
                ];
            }

            $inArea = $this->isInSouthWest($lat, $lng, $address);
            $region = $this->getRegionFromAddress($address);

            /** @var string $location */
            $location = $address['town'] ?? $address['city'] ?? $address['village'] ?? $address['county'] ?? $displayName;

            return [
                'valid' => true,
                'in_area' => $inArea,
                'location' => $location,
                'region' => $region,
                'error' => null,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'valid' => false,
                'in_area' => false,
                'location' => '',
                'region' => null,
                'error' => __('flood-watch.dashboard.gps_error'),
            ];
        }
    }

    private function getRegionFromAddress(array $address): ?string
    {
        $county = strtolower($address['county'] ?? $address['state_district'] ?? $address['state'] ?? '');
        $city = strtolower($address['city'] ?? $address['town'] ?? $address['village'] ?? '');

        foreach (Region::cases() as $region) {
            $needle = $region->value;
            if (str_contains($county, $needle) || str_contains($city, $needle)) {
                return $region->value;
            }
        }

        return null;
    }
}
