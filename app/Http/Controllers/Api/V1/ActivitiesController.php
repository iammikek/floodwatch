<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use App\Models\SystemActivity;
use Illuminate\Http\JsonResponse;

class ActivitiesController extends Controller
{
    public function index(): JsonResponse
    {
        $activities = SystemActivity::recent(20);
        $resources = $activities->map(fn (SystemActivity $a) => JsonApiResource::resource('activity', (string) $a->id, [
            'type' => $a->type,
            'description' => $a->description,
            'severity' => $a->severity,
            'occurred_at' => $a->occurred_at->toIso8601String(),
            'metadata' => $a->metadata,
        ]))->all();

        return JsonApiResource::document(JsonApiResource::collection($resources), ['total' => count($resources)]);
    }
}
