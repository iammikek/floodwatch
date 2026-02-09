<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Models\UserSearch;
use App\Services\UserSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UserSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_creates_user_search(): void
    {
        $user = User::factory()->create();
        $service = app(UserSearchService::class);

        $service->record('Langport', 51.0358, -2.8318, 'somerset', $user->id, null);

        $this->assertDatabaseHas('user_searches', [
            'user_id' => $user->id,
            'location' => 'Langport',
            'lat' => 51.0358,
            'lng' => -2.8318,
            'region' => 'somerset',
        ]);
    }

    public function test_record_uses_default_coords_when_lat_lng_null(): void
    {
        Config::set('flood-watch.default_lat', 51.0);
        Config::set('flood-watch.default_lng', -2.5);

        $service = app(UserSearchService::class);
        $service->record('Somerset', null, null, 'somerset', null, 'session-123');

        $search = UserSearch::latest()->first();
        $this->assertSame(51.0, $search->lat);
        $this->assertSame(-2.5, $search->lng);
        $this->assertNull($search->user_id);
        $this->assertSame('session-123', $search->session_id);
    }
}
