<?php

namespace App\Flood\Services;

class FloodEnrichmentService
{
    /**
     * Enrich floods with distance from user location and sort by proximity (closest first).
     *
     * @param  array<int, array<string, mixed>>  $floods
     * @return array<int, array<string, mixed>>
     */
    public function enrichWithDistance(array $floods, ?float $userLat, ?float $userLong): array
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

    public function haversineDistanceKm(float $lat1, float $long1, float $lat2, float $long2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLong = deg2rad($long2 - $long1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLong / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
