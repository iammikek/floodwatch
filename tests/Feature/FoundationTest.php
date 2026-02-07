<?php

use App\Models\LocationBookmark;
use App\Models\User;
use App\Models\UserSearch;
use App\Services\LocationResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;

beforeEach(function () {
    $this->resolver = app(LocationResolver::class);
});

test('user_searches table exists and model can be created', function () {
    $user = User::factory()->create();

    $search = UserSearch::create([
        'user_id' => $user->id,
        'location' => 'Langport',
        'lat' => 51.0358,
        'lng' => -2.8318,
        'region' => 'somerset',
        'searched_at' => now(),
    ]);

    expect($search->id)->toBeGreaterThan(0)
        ->and($search->location)->toBe('Langport')
        ->and($search->user_id)->toBe($user->id)
        ->and($search->user)->toBeInstanceOf(User::class);
});

test('user_search can be created for guest with session_id', function () {
    $search = UserSearch::create([
        'user_id' => null,
        'session_id' => 'test-session-123',
        'location' => 'TA10 0',
        'lat' => 51.0358,
        'lng' => -2.8318,
        'region' => 'somerset',
        'searched_at' => now(),
    ]);

    expect($search->user_id)->toBeNull()
        ->and($search->session_id)->toBe('test-session-123');
});

test('location_bookmarks table exists and model can be created', function () {
    $user = User::factory()->create();

    $bookmark = LocationBookmark::create([
        'user_id' => $user->id,
        'label' => 'Home',
        'location' => 'Langport',
        'lat' => 51.0358,
        'lng' => -2.8318,
        'region' => 'somerset',
        'is_default' => true,
    ]);

    expect($bookmark->id)->toBeGreaterThan(0)
        ->and($bookmark->user)->toBeInstanceOf(User::class)
        ->and($bookmark->is_default)->toBeTrue();
});

test('creating new bookmark with is_default clears other defaults for user', function () {
    $user = User::factory()->create();

    $first = LocationBookmark::create([
        'user_id' => $user->id,
        'label' => 'Home',
        'location' => 'Langport',
        'lat' => 51.04,
        'lng' => -2.83,
        'is_default' => true,
    ]);

    $second = LocationBookmark::create([
        'user_id' => $user->id,
        'label' => 'Work',
        'location' => 'Bristol',
        'lat' => 51.45,
        'lng' => -2.58,
        'is_default' => true,
    ]);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

test('user has userSearches and locationBookmarks relationships', function () {
    $user = User::factory()->create();

    UserSearch::create([
        'user_id' => $user->id,
        'location' => 'Langport',
        'lat' => 51.04,
        'lng' => -2.83,
        'searched_at' => now(),
    ]);

    expect($user->userSearches)->toHaveCount(1)
        ->and($user->locationBookmarks)->toHaveCount(0);
});

test('config flood-watch donation_url exists', function () {
    config(['flood-watch.donation_url' => 'https://ko-fi.com/test']);

    expect(config('flood-watch.donation_url'))->toBe('https://ko-fi.com/test');
});

test('config flood-watch warm_cache_locations exists with all regions', function () {
    $locations = config('flood-watch.warm_cache_locations');

    expect($locations)->toBeArray()
        ->and($locations)->toHaveKeys(['somerset', 'bristol', 'devon', 'cornwall'])
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
