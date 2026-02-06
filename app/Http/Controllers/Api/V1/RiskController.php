<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\JsonApiResource;
use App\Services\RiskService;
use Illuminate\Http\JsonResponse;

class RiskController extends Controller
{
    public function __construct(
        protected RiskService $riskService
    ) {}

    public function index(): JsonResponse
    {
        $result = $this->riskService->calculate();

        $resource = JsonApiResource::resource('risk', 'south-west', [
            'index' => $result['index'],
            'label' => $result['label'],
            'summary' => $result['summary'],
            'rawScore' => $result['rawScore'],
        ]);

        return JsonApiResource::document($resource);
    }
}
