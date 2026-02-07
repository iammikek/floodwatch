<?php

namespace App\Support;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
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
        if (Lang::has($key)) {
            return __($key);
        }

        $enum = IncidentStatus::tryFromString($status);

        return $enum !== null ? $enum->label() : Str::title(self::splitCamelCase($status));
    }

    public static function typeLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }

        $key = 'flood-watch.incident_type.'.$type;
        if (Lang::has($key)) {
            return __($key);
        }

        $enum = IncidentType::tryFromString($type);

        return $enum !== null ? $enum->label() : Str::title(self::splitCamelCase($type));
    }

    /**
     * Resolve an emoji icon for a road incident based on incidentType and managementType.
     * Uses IncidentType enum for known types; falls back to config key-matching for API-specific values.
     */
    public static function forIncident(?string $incidentType, ?string $managementType = null): string
    {
        $search = array_filter([$incidentType, $managementType]);
        $icons = config('flood-watch.incident_icons', []);
        $default = $icons['default'] ?? '🛣️';

        foreach ($search as $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $enum = IncidentType::tryFromString($value);
            if ($enum !== null) {
                return $enum->icon();
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

    private static function splitCamelCase(string $value): string
    {
        $withSpaces = preg_replace('/([a-z])([A-Z])/', '$1 $2', str_replace('_', ' ', $value));

        return $withSpaces ?? $value;
    }
}
