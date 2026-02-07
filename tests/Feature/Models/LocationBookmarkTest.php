<?php

use App\Models\LocationBookmark;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('factory creates bookmark', function () {
    $bookmark = LocationBookmark::factory()->create([
        'label' => 'Home',
        'location' => 'Langport',
        'is_default' => true,
    ]);

    expect($bookmark->id)->toBeGreaterThan(0)
        ->and($bookmark->label)->toBe('Home')
        ->and($bookmark->user)->toBeInstanceOf(User::class)
        ->and($bookmark->is_default)->toBeTrue();
});

test('is_default is cast to boolean', function () {
    $bookmark = LocationBookmark::factory()->create(['is_default' => false]);

    expect($bookmark->is_default)->toBeFalse();
});

test('creating new bookmark with is_default clears other defaults for user', function () {
    $user = User::factory()->create();

    $first = LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Home',
        'is_default' => true,
    ]);

    $second = LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Work',
        'is_default' => true,
    ]);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

test('updating existing bookmark to is_default clears other defaults', function () {
    $user = User::factory()->create();

    $first = LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Home',
        'is_default' => true,
    ]);

    $second = LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Work',
        'is_default' => false,
    ]);

    $second->update(['is_default' => true]);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

test('user has locationBookmarks relationship', function () {
    $user = User::factory()->create();

    LocationBookmark::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->locationBookmarks)->toHaveCount(2);
});

test('database constraint prevents multiple defaults per user', function () {
    $user = User::factory()->create();

    LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'First',
        'location' => 'Langport',
        'lat' => 51.0,
        'lng' => -2.8,
        'is_default' => true,
    ]);

    expect(fn () => DB::table('location_bookmarks')->insert([
        'user_id' => $user->id,
        'label' => 'Second',
        'location' => 'Bristol',
        'lat' => 51.4,
        'lng' => -2.6,
        'region' => null,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});
