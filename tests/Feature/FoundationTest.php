<?php

use App\Models\User;
use App\Services\LocationResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;

beforeEach(function () {
    $this->resolver = app(LocationResolver::class);
});

test('config flood-watch donation_url exists', function () {
    expect(array_key_exists('donation_url', config('flood-watch')))->toBeTrue();
});

test('config flood-watch warm_cache_locations exists with all regions', function () {
    $locations = config('flood-watch.warm_cache_locations');

    expect($locations)->toBeArray()
        ->and($locations)->toHaveKeys(['somerset', 'bristol', 'devon', 'cornwall', 'dorset'])
        ->and($locations['somerset'])->toBe('Langport');
});

test('accessAdmin gate allows admin user', function () {
    $admin = User::factory()->admin()->create();

    expect(Gate::forUser($admin)->allows('accessAdmin'))->toBeTrue();
});

test('accessAdmin gate denies regular user', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('accessAdmin'))->toBeFalse();
});

test('location resolver reverseFromCoords returns valid south west location', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'lat' => '51.0358',
            'lon' => '-2.8318',
            'display_name' => 'Langport, Somerset, England',
            'address' => [
                'town' => 'Langport',
                'county' => 'Somerset',
                'country' => 'United Kingdom',
            ],
        ], 200),
    ]);

    $result = $this->resolver->reverseFromCoords(51.0358, -2.8318);

    expect($result['valid'])->toBeTrue()
        ->and($result['in_area'])->toBeTrue()
        ->and($result['region'])->toBe('somerset')
        ->and($result['location'])->toContain('Langport');
});

test('location resolver reverseFromCoords rejects out of area coords', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'lat' => '51.5074',
            'lon' => '-0.1278',
            'display_name' => 'London, England',
            'address' => [
                'city' => 'London',
                'county' => 'Greater London',
                'country' => 'United Kingdom',
            ],
        ], 200),
    ]);

    $result = $this->resolver->reverseFromCoords(51.5074, -0.1278);

    expect($result['valid'])->toBeTrue()
        ->and($result['in_area'])->toBeFalse();
});

test('lang keys exist for use_my_location recent_searches gps_error', function () {
    $keys = [
        'flood-watch.dashboard.use_my_location',
        'flood-watch.dashboard.recent_searches',
        'flood-watch.dashboard.gps_error',
    ];
    foreach ($keys as $key) {
        expect(Lang::has($key))->toBeTrue("Lang key {$key} should exist");
    }
});

test('user factory admin state produces admin user', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->role)->toBe('admin');
});
