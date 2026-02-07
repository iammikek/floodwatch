<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'environment.data.gov.uk/*' => Http::response(['items' => []], 200),
        'api.ffc-environment-agency.fgs.metoffice.gov.uk/*' => Http::response([], 200),
        'api.open-meteo.com/*' => Http::response([], 200),
        'api.data.nationalhighways.co.uk/*' => Http::response([], 200),
    ]);
});

test('admin dashboard returns 403 for guest', function () {
    $response = $this->get('/admin');

    $response->assertStatus(403);
});

test('admin dashboard returns 403 for non-admin user', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertStatus(403);
});

test('admin dashboard returns 200 for admin user', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertStatus(200);
});

test('admin dashboard displays api health section', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('API Health', false);
});

test('admin dashboard displays user metrics section', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('User Metrics', false);
});

test('admin dashboard displays llm cost section', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('LLM Cost', false);
});
