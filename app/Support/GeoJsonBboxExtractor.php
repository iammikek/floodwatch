<?php

namespace App\Support;

/**
 * Extract bounding boxes from GeoJSON FeatureCollection geometries.
 * Handles Polygon and MultiPolygon (and nested rings) by walking all coordinate pairs.
 */
class GeoJsonBboxExtractor
{
    /**
     * Extract bbox from GeoJSON FeatureCollection with Polygon or MultiPolygon geometries.
     * Walks all coordinate pairs in nested rings to handle both geometry types.
     *
     * @param  array<string, mixed>  $polygon  GeoJSON FeatureCollection
     * @return array{minLng: float, minLat: float, maxLng: float, maxLat: float}
     */
    public function extractBboxFromFeatureCollection(array $polygon): array
    {
        $coords = [];
        $features = $polygon['features'] ?? [];
        foreach ($features as $feature) {
            $geom = $feature['geometry'] ?? [];
            $geometryCoords = $geom['coordinates'] ?? [];
            foreach ($this->extractCoordinatePairs($geometryCoords) as $pair) {
                $coords[] = $pair;
            }
        }
        if (empty($coords)) {
            return ['minLng' => 0.0, 'minLat' => 0.0, 'maxLng' => 0.0, 'maxLat' => 0.0];
        }
        $lngs = array_column($coords, 0);
        $lats = array_column($coords, 1);

        return [
            'minLng' => min($lngs),
            'minLat' => min($lats),
            'maxLng' => max($lngs),
            'maxLat' => max($lats),
        ];
    }

    /**
     * Recursively extract [lng, lat] coordinate pairs from GeoJSON coordinates.
     * Handles Polygon (coordinates[ring][point]) and MultiPolygon (coordinates[poly][ring][point]).
     *
     * @param  array<int, mixed>  $coords
     * @return array<int, array{0: float, 1: float}>
     */
    private function extractCoordinatePairs(array $coords): array
    {
        $pairs = [];
        foreach ($coords as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (isset($item[0], $item[1]) && is_numeric($item[0]) && is_numeric($item[1])) {
                $pairs[] = [(float) $item[0], (float) $item[1]];
            } else {
                $pairs = array_merge($pairs, $this->extractCoordinatePairs($item));
            }
        }

        return $pairs;
    }
}
