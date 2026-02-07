<?php

use App\Models\User;
use App\Models\UserSearch;

test('factory creates user search with user', function () {
    $search = UserSearch::factory()->create([
        'location' => 'Langport',
        'lat' => 51.0358,
        'lng' => -2.8318,
        'region' => 'somerset',
    ]);

    expect($search->id)->toBeGreaterThan(0)
        ->and($search->location)->toBe('Langport')
        ->and($search->user_id)->toBeGreaterThan(0)
        ->and($search->user)->toBeInstanceOf(User::class);
});

test('factory guest state creates search with session_id and null user_id', function () {
    $search = UserSearch::factory()->guest()->create([
        'location' => 'TA10 0',
        'lat' => 51.0358,
        'lng' => -2.8318,
        'searched_at' => now(),
    ]);

    expect($search->user_id)->toBeNull()
        ->and($search->session_id)->not->toBeNull()
        ->and($search->session_id)->toStartWith('test-session-');
});

test('searched_at is cast to datetime', function () {
    $search = UserSearch::factory()->create([
        'searched_at' => '2026-02-07 12:00:00',
    ]);

    expect($search->searched_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($search->searched_at->format('Y-m-d'))->toBe('2026-02-07');
});

test('user has userSearches relationship', function () {
    $user = User::factory()->create();

    UserSearch::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->userSearches)->toHaveCount(2);
});
