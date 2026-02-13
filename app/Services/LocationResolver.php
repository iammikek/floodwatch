<?php

namespace App\Services;

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

    /**
     * County/area names that indicate South West region.
     */
    private const array SOUTH_WEST_INDICATORS = [
        'somerset', 'devon', 'cornwall', 'bristol', 'dorset',
        'north somerset', 'south gloucestershire', 'bath and north east somerset',
    ];

    public function __construct(
        protected PostcodeValidator $postcodeValidator
    ) {}

    /**
     * Resolve a location string (postcode or place name) to coordinates and region.
     *
     * @return array{valid: bool, in_area: bool, errors?: string, lat?: float, lng?: float, region?: string, outcode?: string, display_name?: string}
     */
    public function resolve(string $input): array
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            return [
                'valid' => false,
                'in_area' => false,
                'errors' => 'Please enter a postcode or location.',
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
     * @return array{valid: bool, in_area: bool, errors?: string, lat?: float, lng?: float, region?: string, display_name?: string}
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
                    'errors' => 'Location lookup rate limit exceeded. Please wait a minute and try again.',
                ];
            }

            if (! $response->successful()) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'errors' => 'Unable to find that location. Try a postcode or a town name in the South West.',
                ];
            }

            $results = $response->json();
            $first = $results[0] ?? null;

            if ($first === null) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'errors' => 'Location not found. Try a postcode or town name (e.g. Langport, Bristol, Exeter).',
                ];
            }

            $lat = isset($first['lat']) ? (float) $first['lat'] : null;
            $lon = isset($first['lon']) ? (float) $first['lon'] : null;

            if ($lat === null || $lon === null) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'errors' => 'Unable to get coordinates for that location.',
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
                    'errors' => 'That location is outside the South West. Flood Watch covers Bristol, Somerset, Devon and Cornwall.',
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
                'errors' => 'Unable to look up that location. Please try again.',
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

        foreach (self::SOUTH_WEST_INDICATORS as $indicator) {
            if (str_contains($searchable, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reverse geocode coordinates to a location string and region.
     *
     * @return array{valid: bool, in_area: bool, location: string, region: ?string, errors: ?string}
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
                    'errors' => 'Location lookup rate limit exceeded. Please wait a minute and try again.',
                ];
            }

            if (! $response->successful()) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'location' => '',
                    'region' => null,
                    'errors' => 'Could not get location. Try entering a postcode.',
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
                    'errors' => 'Could not get location. Try entering a postcode.',
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
                'errors' => null,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'valid' => false,
                'in_area' => false,
                'location' => '',
                'region' => null,
                'errors' => 'Could not get location. Try entering a postcode.',
            ];
        }
    }

    private function getRegionFromAddress(array $address): ?string
    {
        $county = strtolower($address['county'] ?? $address['state_district'] ?? $address['state'] ?? '');
        $city = strtolower($address['city'] ?? $address['town'] ?? $address['village'] ?? '');

        if (str_contains($county, 'somerset') || str_contains($city, 'somerset')) {
            return 'somerset';
        }
        if (str_contains($county, 'bristol') || str_contains($city, 'bristol')) {
            return 'bristol';
        }
        if (str_contains($county, 'devon') || str_contains($city, 'devon')) {
            return 'devon';
        }
        if (str_contains($county, 'cornwall') || str_contains($city, 'cornwall')) {
            return 'cornwall';
        }
        if (str_contains($county, 'dorset') || str_contains($city, 'dorset')) {
            return 'somerset';
        }

        return null;
    }
}
