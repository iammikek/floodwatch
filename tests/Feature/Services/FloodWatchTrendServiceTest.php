<?php

namespace Tests\Feature\Services;

use App\Services\FloodWatchTrendService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FloodWatchTrendServiceTest extends TestCase
{
    public function test_record_does_nothing_when_trends_disabled(): void
    {
        Config::set('flood-watch.trends_enabled', false);

        $service = app(FloodWatchTrendService::class);
        $service->record('Langport', 51.0358, -2.8318, 'somerset', 2, 0, now()->toIso8601String());

        $trends = $service->getTrends();
        $this->assertEmpty($trends);
    }

    public function test_get_trends_returns_empty_when_trends_disabled(): void
    {
        Config::set('flood-watch.trends_enabled', false);

        $service = app(FloodWatchTrendService::class);
        $trends = $service->getTrends();

        $this->assertIsArray($trends);
        $this->assertEmpty($trends);
    }
}
