<?php

namespace App\Http\Controllers\Api\V1;

use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloodsController extends Controller
{
    public function __construct(
        protected EnvironmentAgencyFloodService $floodService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $lat = $request->query('lat') ?? config('flood-watch.default_lat');
        $long = $request->query('long') ?? config('flood-watch.default_long');
        $radiusKm = $request->query('radius_km') ?? config('flood-watch.default_radius_km', 15);

        $floods = $this->floodService->getFloods((float) $lat, (float) $long, (int) $radiusKm);

        $resources = array_map(fn ($f, $i) => JsonApiResource::resource('floods', (string) $i, $f), $floods, array_keys($floods));

        return JsonApiResource::document($resources);
    }
}
