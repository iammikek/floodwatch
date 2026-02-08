<?php

namespace App\Services;

use App\Models\UserSearch;

class UserSearchService
{
    public function record(
        string $location,
        ?float $lat,
        ?float $lng,
        ?string $region,
        ?int $userId,
        ?string $sessionId
    ): void {
        $lat = $lat ?? config('flood-watch.default_lat');
        $lng = $lng ?? config('flood-watch.default_lng');

        UserSearch::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'location' => $location,
            'lat' => $lat,
            'lng' => $lng,
            'region' => $region,
            'searched_at' => now(),
        ]);
    }
}
