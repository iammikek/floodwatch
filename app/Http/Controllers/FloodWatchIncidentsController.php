<?php

namespace App\Http\Controllers;

use App\Roads\Services\RoadIncidentOrchestrator;
use App\Support\CoordinateMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloodWatchIncidentsController extends Controller
{
    public function __construct(
        protected RoadIncidentOrchestrator $orchestrator
    ) {}

    /**
     * Return road incidents near a point for the cockpit / map overlays.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $region = $request->query('region', 'SOM');

        if ($lat === null || $lng === null || ! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json(['items' => []]);
        }

        $items = $this->orchestrator->getFilteredIncidents(
            region: is_string($region) && $region !== '' ? $region : 'SOM',
            lat: (float) $lat,
            lng: (float) $lng,
        );

        $mapped = [];
        foreach ($items as $index => $incident) {
            if (! is_array($incident)) {
                continue;
            }
            $coords = CoordinateMapper::normalize($incident);
            $road = trim((string) ($incident['road'] ?? 'Road'));
            $status = trim((string) ($incident['status'] ?? $incident['statusLabel'] ?? ''));
            $type = trim((string) ($incident['incidentType'] ?? ''));
            $location = trim((string) ($incident['locationDescription'] ?? ''));
            $description = $location !== ''
                ? $location
                : trim(implode(' · ', array_filter([$type, $status])));

            $mapped[] = [
                'id' => (string) ($incident['id'] ?? ('inc-'.($index + 1).'-'.md5($road.$status.$type.$location))),
                'type' => 'incident',
                'road' => $road !== '' ? $road : 'Road',
                'statusLabel' => $status !== '' ? $status : ($type !== '' ? $type : 'Update'),
                'description' => $description !== '' ? $description : 'Road incident',
                'lat' => $coords['lat'] ?? null,
                'lng' => $coords['lng'] ?? null,
                'isFloodRelated' => (bool) ($incident['isFloodRelated'] ?? false),
            ];
        }

        return response()->json(['items' => $mapped]);
    }
}
