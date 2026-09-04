<?php

namespace App\Http\Controllers;

use App\Services\CorridorPredictionService;
use App\Services\DataLakeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloodWatchPredictionsController extends Controller
{
    public function __construct(
        protected CorridorPredictionService $predictions
    ) {}

    /**
     * Return floodwatch.prediction.v1 for a corridor (proxied from the data lake).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $corridor = (string) $request->query(
            'corridor',
            (string) config('flood-watch.predictions.default_corridor', 'a361-muchelney')
        );
        $historyDays = $request->query('history_days');
        $days = is_numeric($historyDays)
            ? max(7, min(400, (int) $historyDays))
            : null;
        $asOfRaw = $request->query('as_of');
        $asOf = is_string($asOfRaw) && $asOfRaw !== '' ? $asOfRaw : null;

        $doc = $this->predictions->getPrediction(
            $corridor !== '' ? $corridor : null,
            $days,
            $asOf
        );

        if ($doc === null) {
            return response()->json(['message' => 'Prediction unavailable.'], 503);
        }

        return response()->json($doc);
    }

    /**
     * List corridors that support predictions.
     */
    public function corridors(): JsonResponse
    {
        $client = new DataLakeClient;
        $res = $client->getPredictionCorridors();
        if ($res->status === 200 && is_array($res->body) && isset($res->body['corridors'])) {
            return response()->json($res->body);
        }

        $id = (string) config('flood-watch.predictions.default_corridor', 'a361-muchelney');

        return response()->json([
            'corridors' => [
                [
                    'id' => $id,
                    'label' => 'A361 Muchelney corridor',
                    'region' => 'SOM',
                ],
            ],
        ]);
    }

    /**
     * Curated storm catalogue for place-mode replay.
     */
    public function storms(Request $request): JsonResponse
    {
        $corridor = (string) $request->query(
            'corridor',
            (string) config('flood-watch.predictions.default_corridor', 'a361-muchelney')
        );
        $client = new DataLakeClient;
        $res = $client->getStorms($corridor !== '' ? $corridor : null);
        if ($res->status === 200 && is_array($res->body) && isset($res->body['storms'])) {
            return response()->json($res->body);
        }

        return response()->json(['storms' => []], 503);
    }
}
