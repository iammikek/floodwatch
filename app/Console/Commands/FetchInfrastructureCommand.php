<?php

namespace App\Console\Commands;

use App\Jobs\FetchLatestInfrastructureData;
use App\Services\FloodWatchService;
use App\Services\InfrastructureDeltaService;
use Illuminate\Console\Command;

class FetchInfrastructureCommand extends Command
{
    protected $signature = 'flood-watch:fetch-infrastructure';

    protected $description = 'Fetch latest flood/road/river data and create activity feed entries for changes';

    public function handle(FloodWatchService $floodWatchService, InfrastructureDeltaService $deltaService): int
    {
        $this->info('Fetching infrastructure data…');

        $job = new FetchLatestInfrastructureData;
        $job->handle($floodWatchService, $deltaService);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
