<?php

namespace App\Http\Controllers;

use App\Services\RouteCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloodWatchRouteCheckController extends Controller
{
    public function __construct(
        protected RouteCheckService $routeCheckService
    ) {}

    /**
     * Run a From→To route check (OSRM + floods + NH incidents) for the cockpit.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $from = trim((string) $request->query(
            'from',
            (string) config('flood-watch.cockpit.default_route_from', 'Muchelney, Somerset')
        ));
        $to = trim((string) $request->query(
            'to',
            (string) config('flood-watch.cockpit.default_route_to', 'Bridgwater, Somerset')
        ));

        if ($from === '' || $to === '') {
            return response()->json([
                'verdict' => 'error',
                'verdict_label' => 'Error',
                'summary' => 'From and To locations are required.',
                'floods_on_route' => [],
                'incidents_on_route' => [],
                'alternatives' => [],
                'route_geometry' => [],
                'route_key' => null,
                'from' => $from,
                'to' => $to,
            ], 422);
        }

        $result = $this->routeCheckService->check($from, $to);
        $payload = $result->toArray();
        $payload['verdict_label'] = $this->verdictLabel($result->verdict);
        $payload['route_geometry'] = is_array($payload['route_geometry'] ?? null)
            ? $payload['route_geometry']
            : [];
        $payload['from'] = $from;
        $payload['to'] = $to;

        return response()->json($payload);
    }

    private function verdictLabel(string $verdict): string
    {
        return match ($verdict) {
            'blocked' => 'Blocked',
            'at_risk' => 'At risk',
            'delays' => 'Delays',
            'clear' => 'Clear',
            default => 'Error',
        };
    }
}
