<?php

namespace Tests\Feature;

use App\Models\LocationBookmark;
use App\Models\User;
use Tests\TestCase;

class FloodWatchBookmarksControllerTest extends TestCase
{
    public function test_bookmarks_require_flood_watch_session(): void
    {
        $response = $this->getJson('/flood-watch/bookmarks');

        $response->assertForbidden();
    }

    public function test_guest_receives_empty_bookmarks(): void
    {
        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/bookmarks');

        $response->assertOk()
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('items', []);
    }

    public function test_authenticated_user_receives_bookmarks(): void
    {
        $user = User::factory()->create();
        LocationBookmark::factory()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'location' => 'TA10 0DP',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/bookmarks');

        $response->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('items.0.label', 'Home')
            ->assertJsonPath('items.0.location', 'TA10 0DP')
            ->assertJsonPath('items.0.is_default', true);
    }
}
