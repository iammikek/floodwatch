<?php

namespace App\Services;

use Illuminate\Support\Facades\Concurrency;

class FloodWatchService
{
    public function __construct(
        protected EnvironmentAgencyFloodService $floodService,
        protected NationalHighwaysService $highwaysService
    ) {}

    /**
     * Fetch flood and road data in parallel.
     *
     * @return array{floods: array, incidents: array}
     */
    public function getFloodAndRoadData(
        ?float $lat = null,
        ?float $long = null,
        ?int $radiusKm = null
    ): array {
        [$floods, $incidents] = Concurrency::run([
            fn () => $this->floodService->getFloods($lat, $long, $radiusKm),
            fn () => $this->highwaysService->getIncidents(),
        ]);

        return [
            'floods' => $floods,
            'incidents' => $incidents,
        ];
    }
}
