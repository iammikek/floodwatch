<?php

namespace App\Jobs;

use App\Roads\Services\SomersetCouncilRoadworksService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScrapeSomersetCouncilRoadworksJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job. Scrapes Somerset Council roadworks page and stores incidents in cache.
     */
    public function handle(SomersetCouncilRoadworksService $service): void
    {
        $service->scrapeAndStoreInCache();
    }
}
