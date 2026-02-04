<?php

namespace App\ValueObjects;

use App\Enums\Region;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

final readonly class Postcode
{
    private const UK_POSTCODE_REGEX = '/^([A-Z]{1,2}[0-9][0-9A-Z]?)\s*([0-9][A-Z]{2})$/i';

    private const OUTCODE_ONLY_REGEX = '/^([A-Z]{1,2}[0-9][0-9A-Z]?)(?:\s+[0-9][A-Z]{0,2})?$/i';

    private const SOUTH_WEST_AREAS = ['BS', 'BA', 'TA', 'EX', 'TQ', 'PL', 'TR'];

    public function __construct(
        public string $value
    ) {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($value)));
        if ($normalized === '') {
            throw new InvalidArgumentException('Postcode cannot be empty.');
        }
        if (! $this->matchesFormat($normalized)) {
            throw new InvalidArgumentException("Invalid postcode format: {$value}");
        }
    }

    public static function tryFrom(string $value): ?self
    {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($value)));
        if ($normalized === '') {
            return null;
        }
        if (! preg_match(self::UK_POSTCODE_REGEX, $normalized) && ! preg_match(self::OUTCODE_ONLY_REGEX, $normalized)) {
            return null;
        }

        return new self($normalized);
    }

    public function normalize(?string $value = null): string
    {
        $input = $value ?? $this->value;

        return strtoupper(preg_replace('/\s+/', ' ', trim($input)));
    }

    public function outcode(): string
    {
        $normalized = $this->normalize();
        if (preg_match(self::UK_POSTCODE_REGEX, $normalized, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match(self::OUTCODE_ONLY_REGEX, $normalized, $m)) {
            return strtoupper($m[1]);
        }

        return '';
    }

    public function areaCode(): string
    {
        $outcode = $this->outcode();
        if (preg_match('/^([A-Z]{1,2})/', strtoupper($outcode), $m)) {
            return $m[1];
        }

        return $outcode;
    }

    public function isInSouthWest(): bool
    {
        return in_array($this->areaCode(), self::SOUTH_WEST_AREAS, true);
    }

    public function region(): ?Region
    {
        $area = $this->areaCode();
        $regions = Config::get('flood-watch.regions', []);

        foreach ($regions as $regionKey => $config) {
            if (in_array($area, $config['areas'] ?? [], true)) {
                return Region::tryFrom($regionKey);
            }
        }

        return null;
    }

    private function matchesFormat(string $normalized): bool
    {
        return (bool) preg_match(self::UK_POSTCODE_REGEX, $normalized)
            || (bool) preg_match(self::OUTCODE_ONLY_REGEX, $normalized);
    }

    public function __toString(): string
    {
        return $this->normalize();
    }
}
