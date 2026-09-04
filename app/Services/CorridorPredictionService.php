<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches floodwatch.prediction.v1 corridor predictions from the data lake.
 */
class CorridorPredictionService
{
    public function __construct(
        private ?DataLakeClient $client = null
    ) {
        $this->client = $this->client ?? new DataLakeClient;
    }

    /**
     * @return array<string, mixed>|null Prediction document, or null if unavailable
     */
    public function getPrediction(
        ?string $corridor = null,
        ?int $historyDays = null,
        ?string $asOf = null
    ): ?array {
        if (! (bool) config('flood-watch.predictions.enabled', true)) {
            return null;
        }

        $corridorId = $corridor ?? (string) config('flood-watch.predictions.default_corridor', 'a361-muchelney');
        if ($corridorId === '') {
            return null;
        }

        $days = $historyDays ?? (int) config('flood-watch.predictions.history_days', 120);

        try {
            $res = $this->client->getPredictions($corridorId, $days, null, $asOf);
        } catch (Throwable $e) {
            Log::warning('Corridor prediction lake request failed', [
                'corridor' => $corridorId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($res->status !== 200 || ! is_array($res->body)) {
            Log::debug('Corridor prediction unavailable', [
                'corridor' => $corridorId,
                'status' => $res->status,
            ]);

            return null;
        }

        if (($res->body['schema'] ?? null) !== 'floodwatch.prediction.v1') {
            Log::warning('Corridor prediction schema mismatch', [
                'corridor' => $corridorId,
                'schema' => $res->body['schema'] ?? null,
            ]);

            return null;
        }

        return $res->body;
    }
}
