<?php

use App\Models\LocationBookmark;
use App\Models\User;
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

test('rejects unauthenticated request', function () {
    $user = User::factory()->create();
    $bookmark = LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Home',
    ]);

    $response = $this->patch(route('bookmarks.update', $bookmark), [
        'label' => 'Parents',
    ]);

    $response->assertRedirect(route('login'));
});

test('rejects request when user does not own bookmark', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $bookmark = LocationBookmark::factory()->create([
        'user_id' => $other->id,
        'label' => 'Home',
    ]);

    $response = $this->actingAs($user)->patch(route('bookmarks.update', $bookmark), [
        'label' => 'Hacked',
    ]);

    $response->assertStatus(403);
});

test('passes when updating label only', function () {
    $user = User::factory()->create();
    $bookmark = LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Home',
        'location' => 'Langport',
    ]);

    $response = $this->actingAs($user)->patch(route('bookmarks.update', $bookmark), [
        'label' => 'Parents',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('status', 'bookmark-updated');
    expect($bookmark->fresh()->label)->toBe('Parents');
});

test('passes when updating is_default only', function () {
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

    $response = $this->actingAs($user)->patch(route('bookmarks.update', $second), [
        'is_default' => true,
    ]);

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('status', 'bookmark-updated');
});

test('fails when label exceeds max length', function () {
    $user = User::factory()->create();
    $bookmark = LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Home',
    ]);

    $response = $this->actingAs($user)->patch(route('bookmarks.update', $bookmark), [
        'label' => str_repeat('a', 51),
    ]);

    $response->assertSessionHasErrors('label');
});

test('flashes editing_bookmark_id when validation fails', function () {
    $user = User::factory()->create();
    $bookmark = LocationBookmark::factory()->create([
        'user_id' => $user->id,
        'label' => 'Home',
    ]);

    $response = $this->actingAs($user)->patch(route('bookmarks.update', $bookmark), [
        'label' => str_repeat('a', 51),
    ]);

    $response->assertSessionHas('editing_bookmark_id', $bookmark->id);
});
