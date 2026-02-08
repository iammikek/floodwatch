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

test('admin dashboard displays llm cost section', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('LLM Cost', false);
});

test('admin dashboard displays llm usage from openai api', function () {
    Config::set('openai.org_api_key', 'sk-org-admin-test');
    $admin = User::factory()->admin()->create();

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
            return Http::response([
                'object' => 'page',
                'data' => [
                    [
                        'object' => 'bucket',
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
            ], 200);
        }
        if (str_contains($request->url(), 'environment.data.gov.uk')) {
            return Http::response(['items' => []], 200);
        }
        if (str_contains($request->url(), 'api.ffc-environment-agency') || str_contains($request->url(), 'api.open-meteo.com') || str_contains($request->url(), 'api.data.nationalhighways.co.uk')) {
            return Http::response([], 200);
        }

        return Http::response([], 404);
    });

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Requests today', false);
    $response->assertSee('Requests this month', false);
    $response->assertSee('Est. cost this month', false);
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
    Config::set('openai.org_api_key', 'sk-org-admin-test');
    Config::set('flood-watch.llm_budget_initial', 10);
    $admin = User::factory()->admin()->create();

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.openai.com/v1/organization/usage')) {
            return Http::response([
                'object' => 'page',
                'data' => [
                    [
                        'object' => 'bucket',
                        'results' => [
                            [
                                'object' => 'organization.usage.completions.result',
                                'num_model_requests' => 2,
                                'input_tokens' => 500,
                                'output_tokens' => 100,
                            ],
                        ],
                    ],
                ],
                'has_more' => false,
                'next_page' => null,
            ], 200);
        }
        if (str_contains($request->url(), 'environment.data.gov.uk')) {
            return Http::response(['items' => []], 200);
        }
        if (str_contains($request->url(), 'api.ffc-environment-agency') || str_contains($request->url(), 'api.open-meteo.com') || str_contains($request->url(), 'api.data.nationalhighways.co.uk')) {
            return Http::response([], 200);
        }

        return Http::response([], 404);
    });

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Remaining (est.)', false);
});
