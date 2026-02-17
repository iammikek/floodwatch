<?php

namespace App\Services;

use App\ValueObjects\Postcode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

class PostcodeValidator
{
    /**
     * UK postcode format (outcode + incode). Supports optional space.
     */
    private const string UK_POSTCODE_REGEX = '/^([A-Z]{1,2}[0-9][0-9A-Z]?)\s*([0-9][A-Z]{2})$/i';

    /**
     * Outcode-only pattern (e.g. TA10, TA10 0) for partial postcode lookup.
     */
    private const string OUTCODE_ONLY_REGEX = '/^([A-Z]{1,2}[0-9][0-9A-Z]?)(?:\s+[0-9][A-Z]{0,2})?$/i';

    /**
     * Validate and optionally geocode a UK postcode for the South West.
     *
     * @return array{valid: bool, in_area: bool, error?: string, lat?: float, lng?: float, outcode?: string, region?: string|null}
     *
     * @throws InvalidArgumentException
     */
    public function validate(string $postcode, bool $geocode = true): array
    {
        $postcodeObj = Postcode::tryFrom($postcode);

        if ($postcodeObj === null) {
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

    /**
     * Geocode postcode via postcodes.io (free, no API key).
     * Successful results are cached to reduce repeat API calls.
     *
     * @return array{lat: float, lng: float}|array{error: string}|null
     *
     * @throws InvalidArgumentException
     */
    public function geocode(string $postcode): ?array
    {
        $normalized = $this->normalize($postcode);
        $cacheMinutes = config('flood-watch.geocode_postcode_cache_minutes', 0);

        if ($cacheMinutes > 0) {
            $key = $this->geocodePostcodeCacheKey($normalized);
            $store = config('flood-watch.cache_store', 'flood-watch');
            $cached = Cache::store($store)->get($key);
            if ($cached !== null && is_array($cached) && isset($cached['lat'], $cached['lng'])) {
                return $cached;
            }
        }

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

            $coords = [
                'lat' => (float) $result['latitude'],
                'lng' => (float) $result['longitude'],
            ];

            if ($cacheMinutes > 0) {
                $key = $this->geocodePostcodeCacheKey($normalized);
                $store = config('flood-watch.cache_store', 'flood-watch');
                Cache::store($store)->put($key, $coords, now()->addMinutes($cacheMinutes));
            }

            return $coords;
        } catch (Throwable) {
            return null;
        }
    }

    private function geocodePostcodeCacheKey(string $normalizedPostcode): string
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');

        return "{$prefix}:geocode:postcode:".$normalizedPostcode;
    }
}
