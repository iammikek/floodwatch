<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('pulse.enabled', true);
});

test('pulse dashboard is accessible to admin user', function () {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->get('/pulse');

    $response->assertStatus(200);
});

test('pulse dashboard returns 403 for non-admin user', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get('/pulse');

    $response->assertStatus(403);
});

test('pulse dashboard redirects to login when unauthenticated', function () {
    $response = $this->get('/pulse');

    $response->assertRedirect(route('login'));
});
