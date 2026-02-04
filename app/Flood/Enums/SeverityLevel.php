<?php

namespace App\Flood\Enums;

/**
 * Environment Agency flood severity levels.
 * 1 = most severe (e.g. Danger to Life), 4 = inactive.
 *
 * @see https://environment.data.gov.uk/flood-monitoring/doc/reference
 */
enum SeverityLevel: int
{
    case Severe = 1;
    case Warning = 2;
    case Alert = 3;
    case Inactive = 4;

    public function label(): string
    {
        return match ($this) {
            self::Severe => 'Severe Flood Warning',
            self::Warning => 'Flood Warning',
            self::Alert => 'Flood Alert',
            self::Inactive => 'Inactive',
        };
    }

    public static function fromApiValue(int|string|null $value): self
    {
        if ($value === null || $value === '') {
            return self::Inactive;
        }

        $int = is_numeric($value) ? (int) $value : 4;

        return self::tryFrom($int) ?? self::Inactive;
    }
}
