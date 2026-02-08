<?php

use App\Models\LocationBookmark;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response([
            'result' => [
                'latitude' => 51.0358,
                'longitude' => -2.8318,
            ],
        ], 200),
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'lat' => '51.0358',
                'lon' => '-2.8318',
                'display_name' => 'Langport, Somerset, England',
                'address' => [
                    'town' => 'Langport',
                    'county' => 'Somerset',
                    'country' => 'United Kingdom',
                ],
            ],
        ], 200),
    ]);
});

test('guest cannot access bookmark store', function () {
    $response = $this->post(route('bookmarks.store'), [
        'label' => 'Home',
        'location' => 'Langport',
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated user can create bookmark', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('bookmarks.store'), [
        'label' => 'Home',
        'location' => 'Langport',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('status', 'bookmark-created');
    $this->assertDatabaseHas('location_bookmarks', [
        'user_id' => $user->id,
        'label' => 'Home',
        'location' => 'Langport, Somerset, England',
        'is_default' => true,
    ]);
});

test('first bookmark is set as default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('bookmarks.store'), [
        'label' => 'Home',
        'location' => 'TA10 0',
    ]);

    $bookmark = $user->locationBookmarks()->first();
    expect($bookmark->is_default)->toBeTrue();
});

test('second bookmark is not default when first exists', function () {
    $user = User::factory()->create();
    LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Home',
        'location' => 'Langport',
        'is_default' => true,
    ]);

    $this->actingAs($user)->post(route('bookmarks.store'), [
        'label' => 'Work',
        'location' => 'TA10 0',
    ]);

    $work = $user->locationBookmarks()->where('label', 'Work')->first();
    expect($work->is_default)->toBeFalse();
});

test('user can set bookmark as default', function () {
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

    $response = $this->actingAs($user)->post(route('bookmarks.set-default', $second));

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('status', 'bookmark-default-set');
    expect($first->fresh()->is_default)->toBeFalse();
    expect($second->fresh()->is_default)->toBeTrue();
});

test("user cannot set another user's bookmark as default", function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $bookmark = LocationBookmark::factory()->create([
        'user_id' => $other->id,
        'label' => 'Home',
        'is_default' => false,
    ]);

    $response = $this->actingAs($user)->post(route('bookmarks.set-default', $bookmark));

    $response->assertStatus(403);
    expect($bookmark->fresh()->is_default)->toBeFalse();
});

test('user can delete bookmark', function () {
    $user = User::factory()->create();
    $bookmark = LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Home',
    ]);

    $response = $this->actingAs($user)->delete(route('bookmarks.destroy', $bookmark));

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('status', 'bookmark-deleted');
    $this->assertDatabaseMissing('location_bookmarks', ['id' => $bookmark->id]);
});

test("user cannot delete another user's bookmark", function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $bookmark = LocationBookmark::factory()->create([
        'user_id' => $other->id,
        'label' => 'Home',
    ]);

    $response = $this->actingAs($user)->delete(route('bookmarks.destroy', $bookmark));

    $response->assertStatus(403);
    $this->assertDatabaseHas('location_bookmarks', ['id' => $bookmark->id]);
});

test('max bookmarks reached prevents new bookmark', function () {
    Config::set('flood-watch.bookmarks_max_per_user', 10);

    $user = User::factory()->create();
    LocationBookmark::factory()->count(10)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('bookmarks.store'), [
        'label' => 'Extra',
        'location' => 'Langport',
    ]);

    $response->assertSessionHasErrors('location', null, 'bookmark-store');
    expect($user->locationBookmarks()->count())->toBe(10);
});
