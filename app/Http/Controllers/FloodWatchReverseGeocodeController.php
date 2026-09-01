<?php

namespace App\Http\Controllers;

use App\Services\LocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloodWatchReverseGeocodeController extends Controller
{
    public function __construct(
        protected LocationResolver $locationResolver
    ) {}

    /**
     * Reverse geocode GPS coordinates for cockpit "Use my location" on route From.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json([
                'valid' => false,
                'in_area' => false,
                'location' => '',
                'error' => __('flood-watch.dashboard.gps_error'),
            ], 422);
        }

        $result = $this->locationResolver->reverseFromCoords((float) $lat, (float) $lng);

        $status = $result['valid'] ? 200 : 422;
        if ($result['valid'] && ! $result['in_area']) {
            $status = 422;
            $result['error'] = __('flood-watch.errors.outside_area');
        }

        return response()->json([
            'valid' => $result['valid'],
            'in_area' => $result['in_area'],
            'location' => $result['location'],
            'region' => $result['region'],
            'error' => $result['error'],
        ], $status);
    }
}
