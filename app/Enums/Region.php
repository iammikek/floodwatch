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

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower($value));
    }
}
