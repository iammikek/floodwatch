<?php

use App\Models\SystemActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('activities page renders successfully', function () {
    $response = $this->get(route('activities'));

    $response->assertStatus(200);
    $response->assertSee('Activity Log');
    $response->assertSeeLivewire('activity-feed');
});

test('activities page shows recent activities', function () {
    SystemActivity::factory()->create([
        'description' => 'New flood warning: North Moor',
        'severity' => 'severe',
        'occurred_at' => now(),
    ]);

    $response = $this->get(route('activities'));

    $response->assertStatus(200);
    $response->assertSee('New flood warning: North Moor');
});

test('activities page shows empty state when no activities', function () {
    $response = $this->get(route('activities'));

    $response->assertStatus(200);
    $response->assertSee('No activity recorded yet.');
});

test('activities page has back link to dashboard', function () {
    $response = $this->get(route('activities'));

    $response->assertStatus(200);
    $response->assertSee(route('flood-watch.dashboard'));
});
