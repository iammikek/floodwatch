<?php

namespace App\Console\Commands;

use App\Services\FloodWatchService;
use App\Services\LocationResolver;
use Illuminate\Console\Command;

class FloodWatchWarmCacheCommand extends Command
{
    protected $signature = 'flood-watch:warm-cache
                            {--locations= : Comma-separated locations to warm (e.g. Langport, TA10, Bristol). Resolved to lat/lng/region per location.}';

    protected $description = 'Pre-warm the Flood Watch cache for common locations to improve first-request latency';

    public function handle(FloodWatchService $service, LocationResolver $resolver): int
    {
        if (empty(config('openai.api_key'))) {
            $this->warn('OPENAI_API_KEY not set. Skipping cache warm.');

            return self::SUCCESS;
        }

        $locationInputs = $this->option('locations')
            ? array_filter(array_map('trim', explode(',', $this->option('locations'))))
            : array_values(config('flood-watch.warm_cache_locations', []));

        if (empty($locationInputs)) {
            $this->warn('No locations configured. Set flood-watch.warm_cache_locations or pass --locations=Langport,Bristol.');

            return self::SUCCESS;
        }

        $this->info('Warming cache for: '.implode(', ', $locationInputs));

        foreach ($locationInputs as $location) {
            $result = $resolver->resolve($location);
            if (! ($result['valid'] ?? false) || ! ($result['in_area'] ?? false)) {
                $this->warn("  Skipping {$location}: ".($result['error'] ?? 'invalid or outside South West'));

                continue;
            }
            $lat = $result['lat'] ?? null;
            $lng = $result['lng'] ?? null;
            $region = $result['region'] ?? null;
            if ($lat === null || $lng === null) {
                $this->warn("  Skipping {$location}: could not resolve coordinates");

                continue;
            }
            $this->line("  Warming: {$location} ({$lat}, {$lng})");
            $service->chat(
                "Check flood and road status for {$location}",
                [],
                $location,
                $lat,
                $lng,
                $region,
            );
        }

        $this->info('Cache warm complete.');

        return self::SUCCESS;
    }
}
