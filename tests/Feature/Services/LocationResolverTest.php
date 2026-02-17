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

    public function test_resolves_place_name_in_dorset(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '50.716',
                    'lon' => '-2.438',
                    'display_name' => 'Dorchester, Dorset, England, United Kingdom',
                    'address' => [
                        'city' => 'Dorchester',
                        'county' => 'Dorset',
                        'country' => 'United Kingdom',
                    ],
                ],
            ], 200),
        ]);

        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('Dorchester');

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['in_area']);
        $this->assertSame('dorset', $result['region']);
        $this->assertStringContainsString('Dorset', $result['display_name']);
    }

    public function test_returns_error_for_empty_input(): void
    {
        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('');

        $this->assertFalse($result['valid']);
        $this->assertSame(__('flood-watch.errors.invalid_location'), $result['error']);
    }

    public function test_returns_error_when_place_name_not_found(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('XyzzyNowhere');

        $this->assertFalse($result['valid']);
        $this->assertSame(__('flood-watch.bookmarks.unable_to_resolve'), $result['error']);
    }

    public function test_rejects_out_of_area_postcode(): void
    {
        $resolver = app(LocationResolver::class);
        $result = $resolver->resolve('SW1A 1AA');

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['in_area']);
        $this->assertSame(__('flood-watch.errors.outside_area'), $result['error']);
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
        $this->assertSame(__('flood-watch.errors.outside_area'), $result['error']);
    }

    public function test_resolve_place_name_returns_cached_result_on_second_call_without_http(): void
    {
        $this->app['config']->set('flood-watch.geocode_place_cache_minutes', 60);

        $requestCount = 0;
        Http::fake(function () use (&$requestCount) {
            $requestCount++;

            return Http::response([
                [
                    'lat' => '51.04',
                    'lon' => '-2.83',
                    'display_name' => 'Langport, Somerset, UK',
                    'address' => ['town' => 'Langport', 'county' => 'Somerset', 'country' => 'United Kingdom'],
                ],
            ], 200);
        });

        $resolver = app(LocationResolver::class);
        $result1 = $resolver->resolve('Langport');
        $result2 = $resolver->resolve('Langport');

        $this->assertTrue($result1['valid']);
        $this->assertTrue($result2['valid']);
        $this->assertSame(51.04, $result2['lat']);
        $this->assertSame(-2.83, $result2['lng']);
        $this->assertSame(1, $requestCount);
    }

    public function test_resolve_place_name_does_not_use_cache_when_ttl_zero(): void
    {
        $this->app['config']->set('flood-watch.geocode_place_cache_minutes', 0);

        $requestCount = 0;
        Http::fake(function () use (&$requestCount) {
            $requestCount++;

            return Http::response([
                [
                    'lat' => '51.0358',
                    'lon' => '-2.8318',
                    'display_name' => 'Langport, Somerset',
                    'address' => ['town' => 'Langport', 'county' => 'Somerset', 'country' => 'United Kingdom'],
                ],
            ], 200);
        });

        $resolver = app(LocationResolver::class);
        $resolver->resolve('Langport');
        $resolver->resolve('Langport');

        $this->assertSame(2, $requestCount, 'Expected two HTTP requests when place cache TTL is 0');
    }

    public function test_resolve_place_name_does_not_cache_outside_area_result(): void
    {
        $this->app['config']->set('flood-watch.geocode_place_cache_minutes', 60);

        $requestCount = 0;
        Http::fake(function () use (&$requestCount) {
            $requestCount++;

            return Http::response([
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
            ], 200);
        });

        $resolver = app(LocationResolver::class);
        $result1 = $resolver->resolve('London');
        $result2 = $resolver->resolve('London');

        $this->assertTrue($result1['valid']);
        $this->assertFalse($result1['in_area']);
        $this->assertSame($result1['error'], $result2['error']);
        $this->assertSame(2, $requestCount, 'Outside-area results must not be cached; second call should hit Nominatim again');
    }
}
