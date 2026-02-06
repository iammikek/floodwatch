<?php

namespace App\Http\Controllers\Api\V1;

use App\Flood\Services\RiverLevelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiverLevelsController extends Controller
{
    public function __construct(
        protected RiverLevelService $riverLevelService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $lat = $request->query('lat') ?? config('flood-watch.default_lat');
        $long = $request->query('long') ?? config('flood-watch.default_long');
        $radiusKm = $request->query('radius_km') ?? config('flood-watch.default_radius_km', 15);

        $levels = $this->riverLevelService->getLevels((float) $lat, (float) $long, (int) $radiusKm);

        $resources = array_map(fn ($r, $i) => JsonApiResource::resource('river-levels', (string) $i, $r), $levels, array_keys($levels));

        return JsonApiResource::document($resources);
    }
}
