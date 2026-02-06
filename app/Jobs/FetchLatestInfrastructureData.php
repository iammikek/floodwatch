<?php

namespace App\Jobs;

use App\Services\FloodWatchService;
use App\Services\InfrastructureDeltaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class FetchLatestInfrastructureData implements ShouldQueue
{
    use Queueable;

    private const STATE_KEY = 'flood-watch:infrastructure:previous-state';

    public function handle(FloodWatchService $floodWatchService, InfrastructureDeltaService $deltaService): void
    {
        $lat = config('flood-watch.default_lat');
        $long = config('flood-watch.default_long');

        $current = $floodWatchService->getMapDataUncached($lat, $long, null);

        $previous = Cache::get(self::STATE_KEY);
        if ($previous === null) {
            $previous = ['floods' => [], 'incidents' => [], 'riverLevels' => []];
        }

        $deltaService->compareAndCreateActivities($previous, $current);

        Cache::put(self::STATE_KEY, $current, now()->addHours(24));
    }
}
