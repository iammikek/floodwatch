<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Http\JsonResponse;

class ActivitiesController extends Controller
{
    /**
     * Return the live activity feed. SystemActivity model not yet implemented;
     * returns empty collection for now.
     */
    public function index(): JsonResponse
    {
        return JsonApiResource::document([], ['total' => 0]);
    }
}
