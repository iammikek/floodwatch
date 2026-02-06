<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::forget('flood-watch:infrastructure:previous-state');
});

test('command runs successfully', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'environment.data.gov.uk')) {
            return Http::response(['items' => []], 200);
        }
        if (str_contains($request->url(), 'api.data.nationalhighways') || str_contains($request->url(), 'api.example.com')) {
            return Http::response(['D2Payload' => ['situation' => []]], 200);
        }

        return Http::response(null, 404);
    });

    Config::set('flood-watch.national_highways.api_key', 'test-key');
    Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
    Config::set('flood-watch.national_highways.fetch_unplanned', false);

    $this->artisan('flood-watch:fetch-infrastructure')
        ->assertSuccessful();
});
