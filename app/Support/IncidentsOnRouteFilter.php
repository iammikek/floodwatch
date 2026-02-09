<?php

namespace App\Support;

/**
 * Filters incidents to those within proximity of a route.
 * Uses bbox prefilter, route downsampling, and point-to-segment distance.
 */
class IncidentsOnRouteFilter
{
    /**
     * Filter incidents to those within proximity of the route.
     *
     * @param  array<int, array<string, mixed>>  $incidents
     * @param  array<int, array{0: float, 1: float}>  $routeCoords  [lng, lat] pairs
     * @param  array{minLng: float, minLat: float, maxLng: float, maxLat: float}  $routeBbox
     * @param  int|null  $maxRoutePoints  Max route points for distance check; null uses config default
     * @return array<int, array<string, mixed>>
     */
    public function filter(
        array $incidents,
        array $routeCoords,
        array $routeBbox,
        float $proximityKm,
        ?int $maxRoutePoints = null
    ): array {
        $maxPoints = $maxRoutePoints ?? config('flood-watch.route_check.incident_check_max_route_points', 150);
        $expandedBbox = $this->expandBboxKm($routeBbox, $proximityKm);
        $simplified = $this->downsample($routeCoords, $maxPoints);

        $onRoute = [];
        foreach ($incidents as $incident) {
            $incidentCoords = CoordinateMapper::normalize($incident);
            $lat = $incidentCoords['lat'] ?? null;
            $lng = $incidentCoords['lng'] ?? null;
            if ($lat === null || $lng === null) {
                continue;
            }
            if (! $this->pointInBbox($lat, $lng, $expandedBbox)) {
                continue;
            }
            $minDist = $this->distanceToRouteKm($lat, $lng, $simplified, $proximityKm);
            if ($minDist <= $proximityKm) {
                $incident['distanceKm'] = round($minDist, 2);
                $onRoute[] = $incident;
            }
        }

        return $onRoute;
    }

    /**
     * @param  array{minLng: float, minLat: float, maxLng: float, maxLat: float}  $bbox
     * @return array{minLng: float, minLat: float, maxLng: float, maxLat: float}
     */
    private function expandBboxKm(array $bbox, float $bufferKm): array
    {
        $midLat = ($bbox['minLat'] + $bbox['maxLat']) / 2;
        $degPerKmLat = 1 / 111.0;
        $degPerKmLng = 1 / (111.0 * cos(deg2rad($midLat)));
        $dLat = $bufferKm * $degPerKmLat;
        $dLng = $bufferKm * $degPerKmLng;

        return [
            'minLng' => $bbox['minLng'] - $dLng,
            'minLat' => $bbox['minLat'] - $dLat,
            'maxLng' => $bbox['maxLng'] + $dLng,
            'maxLat' => $bbox['maxLat'] + $dLat,
        ];
    }

    private function pointInBbox(float $lat, float $lng, array $bbox): bool
    {
        return $lng >= $bbox['minLng'] && $lng <= $bbox['maxLng']
            && $lat >= $bbox['minLat'] && $lat <= $bbox['maxLat'];
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $routeCoords
     * @return array<int, array{0: float, 1: float}>
     */
    private function downsample(array $routeCoords, int $maxPoints): array
    {
        $n = count($routeCoords);
        if ($maxPoints < 2) {
            return $n > 0 ? array_slice($routeCoords, 0, 1) : [];
        }
        if ($n <= $maxPoints) {
            return $routeCoords;
        }
        $step = ($n - 1) / ($maxPoints - 1);
        $result = [];
        for ($i = 0; $i < $maxPoints; $i++) {
            $idx = (int) round($i * $step);
            if ($idx >= $n) {
                $idx = $n - 1;
            }
            $result[] = $routeCoords[$idx];
        }

        return $result;
    }

    /**
     * Distance from point to nearest point on route (point-to-segment).
     * Returns early when minDist <= cutoffKm to avoid unnecessary work.
     *
     * @param  array<int, array{0: float, 1: float}>  $routeCoords
     */
    private function distanceToRouteKm(float $lat, float $lng, array $routeCoords, float $cutoffKm): float
    {
        $minDist = PHP_FLOAT_MAX;
        $n = count($routeCoords);
        for ($i = 0; $i < $n - 1; $i++) {
            $a = $routeCoords[$i];
            $b = $routeCoords[$i + 1];
            $d = $this->distanceToSegmentKm($lat, $lng, $a, $b);
            if ($d < $minDist) {
                $minDist = $d;
                if ($minDist <= $cutoffKm) {
                    return $minDist;
                }
            }
        }
        if ($n === 1) {
            return $this->haversineKm($lat, $lng, $routeCoords[0][1], $routeCoords[0][0]);
        }

        return $minDist;
    }

    /**
     * @param  array{0: float, 1: float}  $a  [lng, lat]
     * @param  array{0: float, 1: float}  $b  [lng, lat]
     */
    private function distanceToSegmentKm(float $lat, float $lng, array $a, array $b): float
    {
        $segLenKm = $this->haversineKm($a[1], $a[0], $b[1], $b[0]);
        $nSamples = (int) max(3, min(15, ceil($segLenKm / 0.3)));
        $minDist = PHP_FLOAT_MAX;
        for ($i = 0; $i <= $nSamples; $i++) {
            $t = $i / $nSamples;
            $sLat = $a[1] + $t * ($b[1] - $a[1]);
            $sLng = $a[0] + $t * ($b[0] - $a[0]);
            $d = $this->haversineKm($lat, $lng, $sLat, $sLng);
            if ($d < $minDist) {
                $minDist = $d;
            }
        }

        return $minDist;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
