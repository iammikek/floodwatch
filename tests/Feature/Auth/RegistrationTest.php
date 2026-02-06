<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('registration screen shows support text when donation url is configured', function () {
    Config::set('app.donation_url', 'https://ko-fi.com/automicalabs');

    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertSee('Flood Watch is free to use', false);
    $response->assertSee('consider supporting development', false);
    $response->assertSee('https://ko-fi.com/automicalabs', false);
});

test('registration screen does not show support text when donation url is not configured', function () {
    Config::set('app.donation_url', null);

    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertDontSee('consider supporting development', false);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/');

    $user = User::where('email', 'test@example.com')->first();
    expect($user->role)->toBe('user');
});

test('user registering with admin email receives admin role', function () {
    Config::set('flood-watch.admin_email', 'mike@automica.io');

    $response = $this->post('/register', [
        'name' => 'Mike',
        'email' => 'mike@automica.io',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/');

    $user = User::where('email', 'mike@automica.io')->first();
    expect($user->role)->toBe('admin');
});

test('user registering with non-admin email receives user role', function () {
    Config::set('flood-watch.admin_email', 'mike@automica.io');

    $response = $this->post('/register', [
        'name' => 'Other User',
        'email' => 'other@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'other@example.com')->first();
    expect($user->role)->toBe('user');
});
