<?php

namespace App\Services;

use App\ValueObjects\Postcode;
use Illuminate\Support\Facades\Http;
use Throwable;

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
     * Validate and optionally geocode a UK postcode for the South West.
     *
     * @return array{valid: bool, in_area: bool, error?: string, lat?: float, lng?: float, outcode?: string, region?: string|null}
     */
    public function validate(string $postcode, bool $geocode = true): array
    {
        $postcodeObj = Postcode::tryFrom($postcode);

        if ($postcodeObj === null) {
            $normalized = $this->normalize($postcode);
            if ($normalized === '') {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'error' => __('flood-watch.errors.invalid_location'),
                ];
            }

            return [
                'valid' => false,
                'in_area' => false,
                'error' => __('flood-watch.errors.invalid_location'),
            ];
        }

        if (! $postcodeObj->isInSouthWest()) {
            return [
                'valid' => true,
                'in_area' => false,
                'error' => __('flood-watch.errors.outside_area'),
                'outcode' => $postcodeObj->outcode(),
            ];
        }

        $result = [
            'valid' => true,
            'in_area' => true,
            'outcode' => $postcodeObj->outcode(),
            'region' => $postcodeObj->region()?->value,
        ];

        if ($geocode) {
            $coords = $this->geocode($postcodeObj->normalize());
            if ($coords !== null && isset($coords['error'])) {
                return [
                    'valid' => false,
                    'in_area' => false,
                    'error' => $coords['error'],
                ];
            }
            if ($coords !== null && isset($coords['lat'], $coords['lng'])) {
                $result['lat'] = $coords['lat'];
                $result['lng'] = $coords['lng'];
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

    // Removed unused isInSouthWest(string $outcode): bool

    /**
     * Get the sub-region key from a postcode outcode (somerset, bristol, devon, cornwall).
     */
    public function getRegionFromOutcode(string $outcode): ?string
    {
        $area = $this->extractAreaCode($outcode);
        $regions = config('flood-watch.regions', []);

        foreach ($regions as $regionKey => $config) {
            if (in_array($area, $config['areas'] ?? [], true)) {
                return $regionKey;
            }
        }

        return null;
    }

    /**
     * Geocode postcode via postcodes.io (free, no API key).
     *
     * @return array{lat: float, lng: float}|array{error: string}|null
     */
    public function geocode(string $postcode): ?array
    {
        $normalized = $this->normalize($postcode);
        $url = 'https://api.postcodes.io/postcodes/'.rawurlencode($normalized);

        try {
            $response = Http::timeout(5)->get($url);

            if ($response->tooManyRequests()) {
                return ['error' => __('flood-watch.errors.rate_limit')];
            }

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
                'lng' => (float) $result['longitude'],
            ];
        } catch (Throwable) {
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

    private function extractAreaCode(string $outcode): string
    {
        $outcode = strtoupper($outcode);

        if (preg_match('/^([A-Z]{1,2})/', $outcode, $m)) {
            return $m[1];
        }

        return '';
    }
}
