<?php

namespace App\Roads;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class IncidentIcon
{
    public static function statusLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        $key = 'flood-watch.incident_status.'.$status;

        return Lang::has($key) ? __($key) : Str::title(self::splitCamelCase($status));
    }

    public static function typeLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }

        $key = 'flood-watch.incident_type.'.$type;

        return Lang::has($key) ? __($key) : Str::title(self::splitCamelCase($type));
    }

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

    /**
     * Add icon and human-readable labels to each incident.
     *
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<int, array<string, mixed>>
     */
    public static function enrich(array $incidents): array
    {
        return array_map(function (array $incident): array {
            $incident['icon'] = self::forIncident(
                $incident['incidentType'] ?? null,
                $incident['managementType'] ?? null
            );
            $incident['statusLabel'] = self::statusLabel($incident['status'] ?? null);
            $incident['typeLabel'] = self::typeLabel($incident['incidentType'] ?? $incident['managementType'] ?? null);

            return $incident;
        }, $incidents);
    }

    private static function splitCamelCase(string $value): string
    {
        $withSpaces = preg_replace('/([a-z])([A-Z])/', '$1 $2', str_replace('_', ' ', $value));

        return $withSpaces ?? $value;
    }
}
