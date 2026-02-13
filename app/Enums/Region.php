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
    case Dorset = 'dorset';

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
            self::Dorset => 'Dorchester',
        };
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower($value));
    }

    public static function indicators(): array
    {
        $base = array_map(fn (self $r) => $r->value, self::cases());

        return array_merge($base, [
            'north somerset',
            'south gloucestershire',
            'bath and north east somerset',
        ]);
    }
}
