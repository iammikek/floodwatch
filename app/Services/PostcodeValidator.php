<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PostcodeValidator
{
    /**
     * UK postcode format (outcode + incode). Supports optional space.
     */
    private const UK_POSTCODE_REGEX = '/^([A-Z]{1,2}[0-9][0-9A-Z]?)\s*([0-9][A-Z]{2})$/i';

    /**
     * Outcode-only pattern (e.g. TA10, TA10 0) for partial postcode lookup.
     */
    private const OUTCODE_ONLY_REGEX = '/^([A-Z]{1,2}[0-9][0-9A-Z]?)(?:\s+[0-9][A-Z]{0,2})?$/i';

    /**
     * Outcode prefixes that fall within the Somerset Levels (Sedgemoor, South Somerset).
     */
    private const SOMERSET_LEVELS_PREFIXES = [
        'TA3', 'TA4', 'TA5', 'TA6', 'TA7', 'TA8', 'TA9', 'TA10', 'TA11',
        'BA3', 'BA4', 'BA5', 'BA6', 'BA7', 'BA8', 'BA9',
        'BS26', 'BS27', 'BS28',
    ];

    /**
     * Validate and optionally geocode a UK postcode for the Somerset Levels.
     *
     * @return array{valid: bool, in_area: bool, error?: string, lat?: float, long?: float, outcode?: string}
     */
    public function validate(string $postcode, bool $geocode = true): array
    {
        $normalized = $this->normalize($postcode);

        if ($normalized === '') {
            return [
                'valid' => false,
                'in_area' => false,
                'error' => 'Please enter a postcode.',
            ];
        }

        if (! $this->matchesUkFormat($normalized) && ! $this->matchesOutcodeOnly($normalized)) {
            return [
                'valid' => false,
                'in_area' => false,
                'error' => 'Invalid postcode format. Use a valid UK postcode (e.g. TA10 0DP).',
            ];
        }

        $outcode = $this->extractOutcode($normalized);
        $inArea = $this->isInSomersetLevels($outcode);

        if (! $inArea) {
            return [
                'valid' => true,
                'in_area' => false,
                'error' => 'This postcode is outside the Somerset Levels. Flood Watch currently covers Sedgemoor and South Somerset only.',
                'outcode' => $outcode,
            ];
        }

        $result = [
            'valid' => true,
            'in_area' => true,
            'outcode' => $outcode,
        ];

        if ($geocode) {
            $coords = $this->geocode($normalized);
            if ($coords !== null) {
                $result['lat'] = $coords['lat'];
                $result['long'] = $coords['long'];
            }
        }

        return $result;
    }

    public function normalize(string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($postcode)));
    }

    public function matchesUkFormat(string $postcode): bool
    {
        return (bool) preg_match(self::UK_POSTCODE_REGEX, $postcode);
    }

    public function matchesOutcodeOnly(string $postcode): bool
    {
        return (bool) preg_match(self::OUTCODE_ONLY_REGEX, $postcode);
    }

    public function isInSomersetLevels(string $outcode): bool
    {
        $prefix = $this->outcodePrefix($outcode);

        return in_array($prefix, self::SOMERSET_LEVELS_PREFIXES, true);
    }

    /**
     * Geocode postcode via postcodes.io (free, no API key).
     *
     * @return array{lat: float, long: float}|null
     */
    public function geocode(string $postcode): ?array
    {
        $normalized = $this->normalize($postcode);
        $url = 'https://api.postcodes.io/postcodes/'.rawurlencode($normalized);

        try {
            $response = Http::timeout(5)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $result = $data['result'] ?? null;

            if ($result === null || ! isset($result['latitude'], $result['longitude'])) {
                return null;
            }

            return [
                'lat' => (float) $result['latitude'],
                'long' => (float) $result['longitude'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractOutcode(string $postcode): string
    {
        if (preg_match(self::UK_POSTCODE_REGEX, $postcode, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match(self::OUTCODE_ONLY_REGEX, $postcode, $m)) {
            return strtoupper($m[1]);
        }

        return '';
    }

    private function outcodePrefix(string $outcode): string
    {
        $outcode = strtoupper($outcode);

        if (preg_match('/^([A-Z]{1,2}[0-9][0-9A-Z]?)/', $outcode, $m)) {
            return $m[1];
        }

        return $outcode;
    }
}
