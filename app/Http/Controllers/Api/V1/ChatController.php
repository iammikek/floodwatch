<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use App\Services\FloodWatchService;
use App\Services\LocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        protected FloodWatchService $floodWatchService,
        protected LocationResolver $locationResolver
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location' => 'required|string|max:100',
            'preFetchedMapData' => 'nullable|array',
        ]);

        $location = $validated['location'];
        $preFetched = $validated['preFetchedMapData'] ?? null;

        $validation = $this->locationResolver->resolve($location);
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

        $message = "Check flood and road status for {$location}";
        $result = $this->floodWatchService->chat(
            $message,
            [],
            $location,
            $validation['lat'] ?? null,
            $validation['long'] ?? null,
            $validation['region'] ?? null,
            null,
            $preFetched
        );

        return JsonApiResource::document(JsonApiResource::resource('chat', uniqid(), [
            'response' => $result['response'],
            'floods' => $result['floods'],
            'incidents' => $result['incidents'],
            'forecast' => $result['forecast'] ?? [],
            'weather' => $result['weather'] ?? [],
            'riverLevels' => $result['riverLevels'] ?? [],
            'lastChecked' => $result['lastChecked'] ?? null,
        ]));
    }
}
