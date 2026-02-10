<?php

namespace App\Http\Controllers;

use App\Flood\Services\RiverLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloodWatchRiverLevelsController extends Controller
{
    public function __construct(
        protected RiverLevelService $riverLevelService
    ) {}

    /**
     * Return river level stations and readings for the given map center/area.
     * Used by the map to load stations for the visible viewport after zoom/pan.
     * Rate-limited via throttle:flood-watch-api (see routes).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $radius = $request->query('radius', config('flood-watch.default_radius_km', 15));

        if ($lat === null || $lng === null || ! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json([]);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $radius = min(50, max(5, (int) $radius));

        $levels = $this->riverLevelService->getLevels($lat, $lng, $radius);

        return response()->json($levels);
    }
}
