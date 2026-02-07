<?php

namespace App\Support;

/**
 * Maps external coordinate keys to our internal schema (lat, lng).
 * Use when ingesting data from APIs that use lon, long, latitude, longitude.
 */
class CoordinateMapper
{
    /**
     * Normalize external coordinate array to our schema (lat, lng).
     * Accepts: lat, lon, long, latitude, longitude.
     *
     * @param  array<string, mixed>  $data  Raw data from external API
     * @return array{lat: ?float, lng: ?float}
     */
    public static function normalize(array $data): array
    {
        $lat = self::extractLat($data);
        $lng = self::extractLng($data);

        return [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * Extract latitude from data with various key names.
     */
    public static function extractLat(array $data): ?float
    {
        $value = $data['lat'] ?? $data['latitude'] ?? null;

        return $value !== null ? (float) $value : null;
    }

    /**
     * Extract longitude from data with various key names.
     */
    public static function extractLng(array $data): ?float
    {
        $value = $data['lng'] ?? $data['long'] ?? $data['lon'] ?? $data['longitude'] ?? null;

        return $value !== null ? (float) $value : null;
    }

    /**
     * Map [lat, lng] or [lat, long] array (e.g. from posList) to our schema.
     * Returns null for missing indices to avoid invalid defaults (0,0).
     *
     * @param  array{0?: float, 1?: float}  $point
     * @return array{lat: ?float, lng: ?float}
     */
    public static function fromPointArray(array $point): array
    {
        return [
            'lat' => isset($point[0]) ? (float) $point[0] : null,
            'lng' => isset($point[1]) ? (float) $point[1] : null,
        ];
    }
}
