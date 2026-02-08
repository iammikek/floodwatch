<?php

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
    $response = $this->post(route('bookmarks.store'), [
        'label' => 'Home',
        'location' => 'Langport',
    ]);

    $response->assertRedirect(route('login'));
});

test('passes with valid label and location', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('bookmarks.store'), [
        'label' => 'Home',
        'location' => 'Langport',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('status', 'bookmark-created');
});

test('fails when label is missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('bookmarks.store'), [
        'location' => 'Langport',
    ]);

    $response->assertSessionHasErrors('label', null, 'bookmark-store');
});

test('fails when label exceeds max length', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('bookmarks.store'), [
        'label' => str_repeat('a', 51),
        'location' => 'Langport',
    ]);

    $response->assertSessionHasErrors('label', null, 'bookmark-store');
});

test('fails when location is missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('bookmarks.store'), [
        'label' => 'Home',
    ]);

    $response->assertSessionHasErrors('location', null, 'bookmark-store');
});

test('fails when max bookmarks reached', function () {
    $user = User::factory()->create();
    $user->locationBookmarks()->create([
        'label' => 'Home',
        'location' => 'Langport',
        'lat' => 51.0358,
        'lng' => -2.8318,
        'is_default' => true,
    ]);
    for ($i = 0; $i < 9; $i++) {
        $user->locationBookmarks()->create([
            'label' => "Bookmark {$i}",
            'location' => 'Langport',
            'lat' => 51.0358,
            'lng' => -2.8318,
            'is_default' => false,
        ]);
    }

    $response = $this->actingAs($user)->post(route('bookmarks.store'), [
        'label' => 'Extra',
        'location' => 'Langport',
    ]);

    $response->assertSessionHasErrors('location', null, 'bookmark-store');
});
