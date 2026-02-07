<?php

namespace Tests\Feature\Services;

use App\Services\PostcodeValidator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostcodeValidatorTest extends TestCase
{
    public function test_empty_postcode_is_invalid(): void
    {
        $validator = app(PostcodeValidator::class);

        $result = $validator->validate('');

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['in_area']);
        $this->assertStringContainsString('enter a postcode', $result['error']);
    }

    public function test_invalid_format_is_rejected(): void
    {
        $validator = app(PostcodeValidator::class);

        $result = $validator->validate('INVALID');

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['in_area']);
        $this->assertStringContainsString('Invalid postcode format', $result['error']);
    }

    public function test_valid_south_west_postcode_passes(): void
    {
        Http::fake([
            'api.postcodes.io/*' => Http::response([
                'result' => [
                    'latitude' => 51.0358,
                    'longitude' => -2.8318,
                ],
            ], 200),
        ]);

        $validator = app(PostcodeValidator::class);

        $result = $validator->validate('TA10 0DP', geocode: true);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['in_area']);
        $this->assertSame(51.0358, $result['lat']);
        $this->assertSame(-2.8318, $result['lng']);
    }

    public function test_out_of_area_postcode_is_rejected(): void
    {
        $validator = app(PostcodeValidator::class);

        $result = $validator->validate('SW1A 1AA');

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['in_area']);
        $this->assertStringContainsString('outside the South West', $result['error']);
    }

    public function test_normalizes_postcode(): void
    {
        $validator = app(PostcodeValidator::class);

        $this->assertSame('TA10 0DP', $validator->normalize('  ta10  0dp  '));
    }

    public function test_matches_uk_format(): void
    {
        $validator = app(PostcodeValidator::class);

        $this->assertTrue($validator->matchesUkFormat('TA10 0DP'));
        $this->assertTrue($validator->matchesUkFormat('BA6 8AB'));
        $this->assertFalse($validator->matchesUkFormat('INVALID'));
    }

    public function test_geocode_returns_null_on_api_failure(): void
    {
        Http::fake(['api.postcodes.io/*' => Http::response(null, 404)]);

        $validator = app(PostcodeValidator::class);

        $this->assertNull($validator->geocode('TA10 0DP'));
    }

    public function test_geocode_returns_error_on_rate_limit(): void
    {
        Http::fake(['api.postcodes.io/*' => Http::response(['error' => 'Too many requests'], 429)]);

        $validator = app(PostcodeValidator::class);

        $result = $validator->geocode('TA10 0DP');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('rate limit', $result['error']);
    }

    public function test_validate_returns_error_when_geocode_rate_limited(): void
    {
        Http::fake(['api.postcodes.io/*' => Http::response(['error' => 'Too many requests'], 429)]);

        $validator = app(PostcodeValidator::class);

        $result = $validator->validate('TA10 0DP', geocode: true);

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['in_area']);
        $this->assertStringContainsString('rate limit', $result['error']);
    }
}
