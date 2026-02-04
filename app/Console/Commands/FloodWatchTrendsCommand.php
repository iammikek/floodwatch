<?php

namespace App\Console\Commands;

use App\Services\FloodWatchTrendService;
use Illuminate\Console\Command;

class FloodWatchTrendsCommand extends Command
{
    protected $signature = 'flood-watch:trends
                            {--days=7 : Number of days to include}
                            {--limit=100 : Maximum number of records to show}';

    protected $description = 'Show Flood Watch search trends from Redis';

    public function handle(FloodWatchTrendService $trendService): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');

        $trends = $trendService->getTrends($days, $limit);

        if (empty($trends)) {
            $this->info('No trends recorded.');

            return self::SUCCESS;
        }

        $rows = array_map(fn (array $t) => [
            $t['location'] ?? '—',
            $t['region'] ?? '—',
            (string) ($t['flood_count'] ?? 0),
            (string) ($t['incident_count'] ?? 0),
            $t['checked_at'] ?? '—',
        ], $trends);

        $this->table(
            ['Location', 'Region', 'Floods', 'Incidents', 'Checked at'],
            $rows
        );

        $this->newLine();
        $this->info(sprintf('Showing %d searches from the last %d days.', count($trends), $days));

        return self::SUCCESS;
    }
}
