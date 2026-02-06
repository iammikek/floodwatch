<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __construct(
        protected WeatherService $weatherService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $lat = $request->query('lat') ?? config('flood-watch.default_lat');
        $long = $request->query('long') ?? config('flood-watch.default_long');

        $weather = $this->weatherService->getForecast((float) $lat, (float) $long);

        return JsonApiResource::document(JsonApiResource::resource('weather', "{$lat}-{$long}", $weather));
    }
}
