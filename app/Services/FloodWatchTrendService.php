<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class FloodWatchTrendService
{
    public function record(
        ?string $location,
        ?float $lat,
        ?float $long,
        ?string $region,
        int $floodCount,
        int $incidentCount,
        ?string $lastChecked = null
    ): void {
        if (! config('flood-watch.trends_enabled', true)) {
            return;
        }

        $payload = json_encode([
            'location' => $location,
            'lat' => $lat,
            'long' => $long,
            'region' => $region,
            'flood_count' => $floodCount,
            'incident_count' => $incidentCount,
            'checked_at' => $lastChecked ?? now()->toIso8601String(),
        ]);

        try {
            $key = config('flood-watch.trends_key', 'flood-watch:trends');
            $score = (float) now()->timestamp;
            Redis::connection()->zadd($key, $score, $payload);
            $this->trimOldEntries($key);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<int, array{location: ?string, lat: ?float, long: ?float, region: ?string, flood_count: int, incident_count: int, checked_at: string}>
     */
    public function getTrends(?int $sinceDays = null, ?int $limit = null): array
    {
        if (! config('flood-watch.trends_enabled', true)) {
            return [];
        }

        try {
            $key = config('flood-watch.trends_key', 'flood-watch:trends');
            $minScore = $sinceDays !== null
                ? (float) now()->subDays($sinceDays)->timestamp
                : '-inf';
            $maxScore = '+inf';

            $raw = Redis::connection()->zrangebyscore($key, $minScore, $maxScore, [
                'limit' => [0, $limit ?? 1000],
                'withscores' => false,
            ]);

            $results = [];
            foreach ($raw as $item) {
                $decoded = json_decode($item, true);
                if (is_array($decoded)) {
                    $results[] = $decoded;
                }
            }

            return array_reverse($results);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function trimOldEntries(string $key): void
    {
        $retentionDays = config('flood-watch.trends_retention_days', 30);
        $cutoff = (float) now()->subDays($retentionDays)->timestamp;

        try {
            Redis::connection()->zremrangebyscore($key, '-inf', $cutoff);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
