<?php

namespace App\Enums;

/**
 * Road incident status from National Highways DATEX II.
 */
enum IncidentStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower($value));
    }
}
