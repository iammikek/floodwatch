<?php

namespace App\Jobs;

use App\Roads\Services\NationalHighwaysService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchNationalHighwaysIncidentsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job. Fetches National Highways incidents and stores in cache for UI/LLM.
     */
    public function handle(NationalHighwaysService $service): void
    {
        $service->fetchAndStoreInCache();
    }
}
