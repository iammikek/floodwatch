<?php

namespace Tests\Feature\Services;

use App\Services\LocationResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationResolverTest extends TestCase
{
    public function test_resolves_postcode_via_postcodes_io(): void
    {
        Http::fake([
            'postcodes.io/*' => Http::response([
                'result' => ['latitude' => 51.0358, 'longitude' => -2.8318],
            ], 200),
        ]);

        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('TA10 0');

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['in_area']);
        $this->assertSame(51.0358, $result['lat']);
        $this->assertSame(-2.8318, $result['lng']);
    }

    public function test_resolves_place_name_via_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '51.0358',
                    'lon' => '-2.8318',
                    'display_name' => 'Langport, South Somerset, Somerset, England, United Kingdom',
                    'address' => [
                        'town' => 'Langport',
                        'county' => 'Somerset',
                        'country' => 'United Kingdom',
                    ],
                ],
            ], 200),
        ]);

        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('Langport');

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['in_area']);
        $this->assertSame(51.0358, $result['lat']);
        $this->assertSame(-2.8318, $result['lng']);
        $this->assertSame('somerset', $result['region']);
        $this->assertStringContainsString('Langport', $result['display_name']);
    }

    public function test_returns_error_for_empty_input(): void
    {
        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('');

        $this->assertFalse($result['valid']);
        $this->assertSame('Please enter a postcode or location.', $result['errors']);
    }

    public function test_returns_error_when_place_name_not_found(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('XyzzyNowhere');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Location not found', $result['errors']);
    }

    public function test_rejects_out_of_area_postcode(): void
    {
        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('SW1A 1AA');

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['in_area']);
        $this->assertStringContainsString('outside the South West', $result['errors']);
    }

    public function test_rejects_out_of_area_place_from_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '51.5074',
                    'lon' => '-0.1278',
                    'display_name' => 'London, England, United Kingdom',
                    'address' => [
                        'city' => 'London',
                        'county' => 'Greater London',
                        'country' => 'United Kingdom',
                    ],
                ],
            ], 200),
        ]);

        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('London');

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['in_area']);
        $this->assertStringContainsString('outside the South West', $result['errors']);
    }
}
