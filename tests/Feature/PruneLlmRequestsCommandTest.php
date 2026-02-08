<?php

use App\Models\LlmRequest;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('flood-watch.llm_requests_retention_days', 90);
});

test('prunes records older than retention period', function () {
    $kept = LlmRequest::factory()->create(['created_at' => now()->subDays(30)]);
    $pruned = LlmRequest::factory()->create(['created_at' => now()->subDays(100)]);

    $this->artisan('flood-watch:prune-llm-requests')
        ->assertSuccessful();

    $this->assertDatabaseHas('llm_requests', ['id' => $kept->id]);
    $this->assertDatabaseMissing('llm_requests', ['id' => $pruned->id]);
});

test('respects days option override', function () {
    $old = LlmRequest::factory()->create(['created_at' => now()->subDays(50)]);

    $this->artisan('flood-watch:prune-llm-requests', ['--days' => 30])
        ->assertSuccessful();

    $this->assertDatabaseMissing('llm_requests', ['id' => $old->id]);
});

test('skips when retention days is zero', function () {
    Config::set('flood-watch.llm_requests_retention_days', 0);

    $old = LlmRequest::factory()->create(['created_at' => now()->subDays(100)]);

    $this->artisan('flood-watch:prune-llm-requests')
        ->assertSuccessful();

    $this->assertDatabaseHas('llm_requests', ['id' => $old->id]);
});
