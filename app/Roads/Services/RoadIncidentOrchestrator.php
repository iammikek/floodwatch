<?php

declare(strict_types=1);

namespace App\Roads\Services;

use App\Enums\Region;
use App\Support\CoordinateMapper;

class RoadIncidentOrchestrator
{
    public function __construct(
        protected NationalHighwaysService $highwaysService,
        protected SomersetCouncilRoadworksService $somersetRoadworksService
    ) {}

    /**
     * Get filtered and sorted incidents for the given region and coordinates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredIncidents(?string $region = null, ?float $lat = null, ?float $lng = null): array
    {
        $incidents = $this->mergedHighwaysIncidents($region);

        $incidents = $this->filterIncidentsByRegion($incidents, $region);

        if ($lat !== null && $lng !== null) {
            $incidents = $this->filterIncidentsByProximity($incidents, $lat, $lng);
        }

        $incidents = $this->filterMotorwaysFromDisplay($incidents);

        return $this->sortIncidentsByPriority($incidents);
    }

    /**
     * Merged incidents from cache: National Highways + Somerset Council (when region is Somerset).
     */
    private function mergedHighwaysIncidents(?string $region): array
    {
        $incidents = $this->highwaysService->getIncidents();

        if ($region === Region::Somerset->value) {
            $somerset = $this->somersetRoadworksService->getIncidents();
            $incidents = array_merge($incidents, $somerset);
        }

        return $incidents;
    }

    /**
     * Filter incidents to only those on roads within the region's county limits.
     */
    private function filterIncidentsByRegion(array $incidents, ?string $region): array
    {
        $allowed = $this->getAllowedRoadsForRegion($region);
        if (empty($allowed)) {
            return $incidents;
        }

        return array_values(array_filter($incidents, function (array $incident) use ($allowed): bool {
            $road = trim((string) ($incident['road'] ?? ''));
            if ($road === '') {
                return false;
            }
            $baseRoad = $this->extractBaseRoad($road);

            return in_array($baseRoad, $allowed, true);
        }));
    }

    /**
     * Filter incidents to those within radius of the search location.
     */
    private function filterIncidentsByProximity(array $incidents, float $centerLat, float $centerLng): array
    {
        $radiusKm = config('flood-watch.incident_summary_proximity_km', 80);
        if ($radiusKm <= 0) {
            return $incidents;
        }

        return array_values(array_filter($incidents, function (array $incident) use ($centerLat, $centerLng, $radiusKm): bool {
            $coords = CoordinateMapper::normalize($incident);
            $lat = $coords['lat'] ?? null;
            $lng = $coords['lng'] ?? null;
            if ($lat === null || $lng === null) {
                return false;
            }

            return $this->haversineKm($centerLat, $centerLng, (float) $lat, (float) $lng) <= $radiusKm;
        }));
    }

    /**
     * Filter out motorway incidents when config excludes them (hyperlocal focus).
     */
    private function filterMotorwaysFromDisplay(array $incidents): array
    {
        if (! config('flood-watch.exclude_motorways_from_display', true)) {
            return $incidents;
        }

        return array_values(array_filter($incidents, function (array $incident): bool {
            $road = trim((string) ($incident['road'] ?? ''));
            if ($road === '') {
                return true;
            }
            $baseRoad = $this->extractBaseRoad($road);

            return ! preg_match('/^M\d+/', $baseRoad);
        }));
    }

    /**
     * Sort incidents by priority: flood-related first, then roadClosed before laneClosures.
     */
    private function sortIncidentsByPriority(array $incidents): array
    {
        usort($incidents, function (array $a, array $b): int {
            $aFlood = (bool) ($a['isFloodRelated'] ?? false);
            $bFlood = (bool) ($b['isFloodRelated'] ?? false);
            if ($aFlood !== $bFlood) {
                return $aFlood ? -1 : 1;
            }
            $aClosed = ($a['managementType'] ?? $a['incidentType'] ?? '') === 'roadClosed';
            $bClosed = ($b['managementType'] ?? $b['incidentType'] ?? '') === 'roadClosed';
            if ($aClosed !== $bClosed) {
                return $aClosed ? -1 : 1;
            }

            return 0;
        });

        return array_values($incidents);
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

    private function getAllowedRoadsForRegion(?string $region): array
    {
        if ($region === null || $region === '') {
            return config('flood-watch.incident_allowed_roads', []);
        }

        $routes = config("flood-watch.correlation.{$region}.key_routes", []);

        return array_map(fn (string $route) => $this->extractBaseRoad($route), $routes);
    }

    private function extractBaseRoad(string $road): string
    {
        if (preg_match('/^(M\d+|A\d+)/i', trim($road), $matches)) {
            return strtoupper($matches[1]);
        }

        return strtoupper(trim($road));
    }
}
