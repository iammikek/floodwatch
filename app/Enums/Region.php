<?php

namespace App\Enums;

/**
 * South West regions supported by Flood Watch.
 */
enum Region: string
{
    case Somerset = 'somerset';
    case Bristol = 'bristol';
    case Devon = 'devon';
    case Cornwall = 'cornwall';

    /**
     * Default location for warm cache pre-fetch per region.
     */
    public function warmCacheLocation(): string
    {
        return match ($this) {
            self::Somerset => 'Langport',
            self::Bristol => 'Bristol',
            self::Devon => 'Exeter',
            self::Cornwall => 'Truro',
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
