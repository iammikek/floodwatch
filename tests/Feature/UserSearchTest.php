<?php

use App\Models\User;
use App\Models\UserSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('openai.api_key', 'test-key');
    Config::set('flood-watch.national_highways.api_key', 'test-key');
    Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'postcodes.io')) {
            return Http::response([
                'result' => ['latitude' => 51.0358, 'longitude' => -2.8318],
            ], 200);
        }
        if (str_contains($request->url(), 'nominatim.openstreetmap.org')) {
            return Http::response([[
                'lat' => '51.0358',
                'lon' => '-2.8318',
                'display_name' => 'Langport, Somerset, UK',
                'address' => ['town' => 'Langport', 'county' => 'Somerset'],
            ]], 200);
        }
        if (str_contains($request->url(), 'environment.data.gov.uk')) {
            return Http::response(['items' => []], 200);
        }
        if (str_contains($request->url(), 'api.example.com')) {
            return Http::response(['D2Payload' => ['situation' => []]], 200);
        }
        if (str_contains($request->url(), 'api.ffc-environment-agency') || str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
            return Http::response(['statement' => []], 200);
        }
        if (str_contains($request->url(), 'open-meteo.com')) {
            return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
        }

        return Http::response(null, 404);
    });

    $directResponse = CreateResponse::fake([
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Checked Langport.',
                    'tool_calls' => [],
                ],
                'logprobs' => null,
                'finish_reason' => 'stop',
            ],
        ],
    ]);

    OpenAI::fake([$directResponse]);
});

test('successful search creates user search record for guest', function () {
    $this->get('/');

    Livewire::test('flood-watch-dashboard')
        ->set('location', 'Langport')
        ->call('search')
        ->assertSet('assistantResponse', 'Checked Langport.');

    $this->assertDatabaseCount('user_searches', 1);
    $record = UserSearch::first();
    expect($record->user_id)->toBeNull()
        ->and($record->session_id)->not->toBeNull()
        ->and($record->location)->toBe('Langport')
        ->and($record->region)->not->toBeNull();
    expect($record->lat)->toEqualWithDelta(51.0358, 0.0001);
    expect($record->lng)->toEqualWithDelta(-2.8318, 0.0001);
});

test('successful search creates user search record for registered user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('flood-watch-dashboard')
        ->set('location', 'Langport')
        ->call('search')
        ->assertSet('assistantResponse', 'Checked Langport.');

    $this->assertDatabaseCount('user_searches', 1);
    $record = UserSearch::first();
    expect($record->user_id)->toBe($user->id)
        ->and($record->location)->toBe('Langport');
});

test('recent searches returns last five for guest', function () {
    $this->get('/');
    $sessionId = session()->getId();

    UserSearch::factory()->count(3)->sequence(
        ['location' => 'Langport', 'session_id' => $sessionId, 'user_id' => null, 'searched_at' => now()->subMinutes(1)],
        ['location' => 'TA10 0', 'session_id' => $sessionId, 'user_id' => null, 'searched_at' => now()->subMinutes(2)],
        ['location' => 'Bristol', 'session_id' => $sessionId, 'user_id' => null, 'searched_at' => now()->subMinutes(3)],
    )->create(['lat' => 51.0358, 'lng' => -2.8318]);

    $component = Livewire::test('flood-watch-dashboard');

    $recentSearches = $component->get('recentSearches') ?? [];

    expect($recentSearches)->toHaveCount(3)
        ->and($recentSearches[0]['location'])->toBe('Langport')
        ->and($recentSearches[1]['location'])->toBe('TA10 0')
        ->and($recentSearches[2]['location'])->toBe('Bristol');
});

test('recent searches returns last five for registered user', function () {
    $user = User::factory()->create();

    UserSearch::factory()->count(2)->sequence(
        ['location' => 'Langport', 'user_id' => $user->id, 'searched_at' => now()->subMinutes(1)],
        ['location' => 'Exeter', 'user_id' => $user->id, 'searched_at' => now()->subMinutes(2)],
    )->create(['lat' => 51.0358, 'lng' => -2.8318]);

    $component = Livewire::actingAs($user)->test('flood-watch-dashboard');
    $recentSearches = $component->get('recentSearches') ?? [];

    expect($recentSearches)->toHaveCount(2)
        ->and($recentSearches[0]['location'])->toBe('Langport')
        ->and($recentSearches[1]['location'])->toBe('Exeter');
});

test('recent searches excludes other guests sessions', function () {
    UserSearch::factory()->guest()->create([
        'location' => 'Other guest',
        'session_id' => 'different-session-id',
        'lat' => 51.0358,
        'lng' => -2.8318,
    ]);

    $component = Livewire::test('flood-watch-dashboard');
    $recentSearches = $component->get('recentSearches') ?? [];

    expect($recentSearches)->toHaveCount(0);
});

test('recent searches excludes other users records', function () {
    $otherUser = User::factory()->create();
    UserSearch::factory()->create([
        'location' => 'Other user',
        'user_id' => $otherUser->id,
        'lat' => 51.0358,
        'lng' => -2.8318,
    ]);

    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test('flood-watch-dashboard');
    $recentSearches = $component->get('recentSearches') ?? [];

    expect($recentSearches)->toHaveCount(0);
});
