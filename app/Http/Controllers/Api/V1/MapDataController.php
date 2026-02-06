<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use App\Roads\IncidentIcon;
use App\Services\FloodWatchService;
use App\Services\LocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapDataController extends Controller
{
    public function __construct(
        protected FloodWatchService $floodWatchService,
        protected LocationResolver $locationResolver
    ) {}

    public function index(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $long = $request->query('long');
        $location = $request->query('location');
        $bounds = $this->parseBounds($request->query('bounds'));

        if ($location !== null && $location !== '') {
            $validation = $this->locationResolver->resolve((string) $location);
            if (! $validation['valid']) {
                return JsonApiResource::errors([
                    ['status' => '422', 'title' => 'Invalid location', 'detail' => $validation['error'] ?? 'Invalid location'],
                ], 422);
            }
            if (! $validation['in_area']) {
                return JsonApiResource::errors([
                    ['status' => '422', 'title' => 'Outside area', 'detail' => $validation['error'] ?? 'Location is outside the supported area'],
                ], 422);
            }
            $lat = $validation['lat'] ?? config('flood-watch.default_lat');
            $long = $validation['long'] ?? config('flood-watch.default_long');
        }

        $lat = $lat !== null ? (float) $lat : config('flood-watch.default_lat');
        $long = $long !== null ? (float) $long : config('flood-watch.default_long');
        $region = $request->query('region');

        $result = $this->floodWatchService->getMapData($lat, $long, $region, $bounds);

        return JsonApiResource::document([
            'floods' => $result['floods'],
            'incidents' => IncidentIcon::enrich($result['incidents']),
            'riverLevels' => $result['riverLevels'],
            'forecast' => $result['forecast'],
            'weather' => $result['weather'],
            'lastChecked' => $result['lastChecked'],
            'mapCenter' => ['lat' => $lat, 'long' => $long],
        ]);
    }

    /**
     * Parse bounds string "minLat,minLng,maxLat,maxLng" to [minLat, maxLat, minLng, maxLng].
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    private function parseBounds(mixed $bounds): ?array
    {
        if (! is_string($bounds)) {
            return null;
        }
        $parts = array_map('floatval', explode(',', $bounds));
        if (count($parts) !== 4) {
            return null;
        }

        return [$parts[0], $parts[2], $parts[1], $parts[3]];
    }
}
