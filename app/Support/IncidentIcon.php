<?php

namespace App\Support;

class IncidentIcon
{
    /**
     * Resolve an emoji icon for a road incident based on incidentType and managementType.
     * Matches DATEX II values (constructionWork, sweepingOfRoad, flooding, etc.)
     * and UK road sign equivalents (🚧 road works, 🚫 closed).
     */
    public static function forIncident(?string $incidentType, ?string $managementType = null): string
    {
        $icons = config('flood-watch.incident_icons', []);
        $default = $icons['default'] ?? '🛣️';

        $search = array_filter([
            $incidentType,
            $managementType,
        ]);

        foreach ($search as $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $lower = strtolower($value);
            foreach ($icons as $key => $icon) {
                if ($key === 'default') {
                    continue;
                }
                if (strtolower($key) === $lower || str_contains($lower, strtolower($key))) {
                    return $icon;
                }
            }
        }

        return $default;
    }
}
