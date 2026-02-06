<?php

use App\Models\SystemActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('system activity can be created with required attributes', function () {
    $activity = SystemActivity::factory()->create([
        'type' => 'flood_warning',
        'description' => 'New flood warning: North Moor',
        'severity' => 'severe',
        'occurred_at' => now(),
    ]);

    expect($activity->type)->toBe('flood_warning')
        ->and($activity->description)->toBe('New flood warning: North Moor')
        ->and($activity->severity)->toBe('severe')
        ->and($activity->occurred_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('recent returns activities ordered by occurred_at descending', function () {
    SystemActivity::factory()->create(['occurred_at' => now()->subMinutes(10)]);
    $latest = SystemActivity::factory()->create(['occurred_at' => now()]);
    SystemActivity::factory()->create(['occurred_at' => now()->subMinutes(5)]);

    $recent = SystemActivity::recent(3);

    expect($recent)->toHaveCount(3)
        ->and($recent->first()->id)->toBe($latest->id);
});
