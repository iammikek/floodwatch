<?php

use App\Models\User;
use Laravel\Dusk\Browser;

/**
 * Browser tests for bookmark selection and loading indicator.
 *
 * Run with: sail up -d && sail dusk tests/Browser/BookmarkSelectionTest.php
 * Requires: Chrome, APP_URL pointing to running app (e.g. http://laravel.test).
 */
test('bookmark button shows loading indicator when clicked', function () {
    $user = User::factory()->create();
    $bookmark = $user->locationBookmarks()->create([
        'label' => 'Home',
        'location' => 'Langport',
        'lat' => 51.0358,
        'lng' => -2.8318,
        'region' => 'somerset',
        'is_default' => true,
    ]);

    $this->browse(function (Browser $browser) use ($user, $bookmark) {
        $browser->loginAs($user)
            ->visit('/')
            ->assertSee('Bookmarks')
            ->assertSee('Home')
            ->assertSee('Langport')
            ->click('[data-testid="bookmark-'.$bookmark->id.'"]')
            ->waitForText('Searching...', 2)
            ->pause(500)
            ->assertSourceHas('wire:loading');
    });
})->skip(fn () => ! getenv('DUSK_ENABLED'), 'Dusk tests require DUSK_ENABLED=1 and a running server. Run: sail up -d && DUSK_ENABLED=1 sail dusk');
