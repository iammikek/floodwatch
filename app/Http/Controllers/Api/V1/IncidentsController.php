<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use App\Roads\Services\NationalHighwaysService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentsController extends Controller
{
    public function __construct(
        protected NationalHighwaysService $highwaysService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $incidents = $this->highwaysService->getIncidents();

        $resources = array_map(fn ($i, $idx) => JsonApiResource::resource('incidents', (string) $idx, $i), $incidents, array_keys($incidents));

        return JsonApiResource::document($resources);
    }
}
