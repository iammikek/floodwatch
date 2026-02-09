<?php

namespace App\Console\Commands;

use App\Services\FloodWatchService;
use Illuminate\Console\Command;

class FloodWatchWarmCacheCommand extends Command
{
    protected $signature = 'flood-watch:warm-cache
                            {--locations= : Comma-separated cache keys to warm (e.g. Langport, TA10). Default: default location only}';

    protected $description = 'Pre-warm the Flood Watch cache for common locations to improve first-request latency';

    public function handle(FloodWatchService $service): int
    {
        if (empty(config('openai.api_key'))) {
            $this->warn('OPENAI_API_KEY not set. Skipping cache warm.');

            return self::SUCCESS;
        }

        $locations = $this->option('locations')
            ? array_filter(array_map('trim', explode(',', $this->option('locations'))))
            : ['default'];

        $this->info('Warming cache for: '.implode(', ', $locations));

        foreach ($locations as $location) {
            $this->line("  Warming: {$location}");
            $service->chat('Check flood and road status', [], $location);
        }

        $this->info('Cache warm complete.');

        return self::SUCCESS;
    }
}
