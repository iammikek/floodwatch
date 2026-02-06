<?php

namespace App\Http\Controllers\Api\V1;

use App\Flood\Services\FloodForecastService;
use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Http\JsonResponse;

class ForecastController extends Controller
{
    public function __construct(
        protected FloodForecastService $forecastService
    ) {}

    public function index(): JsonResponse
    {
        $forecast = $this->forecastService->getForecast();

        return JsonApiResource::document(JsonApiResource::resource('forecast', 'england', $forecast));
    }
}
