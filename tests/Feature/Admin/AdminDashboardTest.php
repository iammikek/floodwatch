<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $usageResponse = [
        'object' => 'page',
        'data' => [
            [
                'object' => 'bucket',
                'start_time' => now()->startOfMonth()->timestamp,
                'end_time' => now()->timestamp,
                'results' => [
                    [
                        'object' => 'organization.usage.completions.result',
                        'num_model_requests' => 3,
                        'input_tokens' => 1000,
                        'output_tokens' => 200,
                    ],
                ],
            ],
        ],
        'has_more' => false,
        'next_page' => null,
    ];

    Http::fake([
        '*environment.data.gov.uk*' => Http::response(['items' => []], 200),
        '*api.ffc-environment-agency.fgs.metoffice.gov.uk*' => Http::response(['statement' => []], 200),
        '*api.open-meteo.com*' => Http::response(['daily' => []], 200),
        '*api.data.nationalhighways.co.uk*' => Http::response([], 200),
        '*api.openai.com*' => Http::response($usageResponse, 200),
    ]);
});

test('admin dashboard redirects guest to login', function () {
    $response = $this->get('/admin');

    $response->assertRedirect(route('login'));
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

test('admin dashboard displays top regions when user searches exist', function () {
    $admin = User::factory()->admin()->create();

    \App\Models\UserSearch::factory()->count(3)->create(['region' => 'somerset']);
    \App\Models\UserSearch::factory()->count(2)->create(['region' => 'bristol']);
    \App\Models\UserSearch::factory()->count(1)->create(['region' => 'devon']);

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Top regions by search count', false);
    $response->assertSeeInOrder(['Somerset', '3', 'Bristol', '2', 'Devon', '1'], false);
});

test('admin dashboard displays llm cost section', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('LLM Cost', false);
});

test('admin dashboard displays llm usage from openai api', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(\App\Services\OpenAiUsageService::class, function ($mock) {
        $mock->shouldReceive('getUsage')
            ->once()
            ->andReturn([
                'requests_today' => 5,
                'requests_this_month' => 42,
                'input_tokens_this_month' => 1000,
                'output_tokens_this_month' => 200,
                'cost_this_month' => 0.27,
                'remaining_budget' => null,
                'chart_daily' => [],
                'error' => null,
            ]);
    });

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Requests today', false);
    $response->assertSee('Requests this month', false);
    $response->assertSee('Est. cost this month', false);
    $response->assertSee('5', false);
    $response->assertSee('42', false);
});

test('admin dashboard displays recent llm requests section', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Recent LLM Requests', false);
});

test('admin dashboard displays llm requests table when populated', function () {
    $admin = User::factory()->admin()->create();
    \App\Models\LlmRequest::factory()->create([
        'model' => 'gpt-4o-mini',
        'input_tokens' => 100,
        'output_tokens' => 50,
        'region' => 'somerset',
    ]);

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Recent LLM Requests', false);
    $response->assertSee('gpt-4o-mini', false);
    $response->assertSee('100', false);
    $response->assertSee('50', false);
    $response->assertSee('somerset', false);
});

test('admin dashboard displays remaining budget when llm_budget_initial is set', function () {
    Config::set('flood-watch.llm_budget_initial', 10);
    $admin = User::factory()->admin()->create();

    $this->mock(\App\Services\OpenAiUsageService::class, function ($mock) {
        $mock->shouldReceive('getUsage')
            ->once()
            ->andReturn([
                'requests_today' => 2,
                'requests_this_month' => 15,
                'input_tokens_this_month' => 500,
                'output_tokens_this_month' => 100,
                'cost_this_month' => 0.14,
                'remaining_budget' => 9.86,
                'chart_daily' => [],
                'error' => null,
            ]);
    });

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Remaining (est.)', false);
});
