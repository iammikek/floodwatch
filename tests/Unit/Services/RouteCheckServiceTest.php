<?php

uses(Tests\TestCase::class);

use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Roads\Services\NationalHighwaysService;
use App\Services\LocationResolver;
use App\Services\RouteCheckService;

beforeEach(function () {
    $this->service = new RouteCheckService(
        $this->createMock(LocationResolver::class),
        $this->createMock(EnvironmentAgencyFloodService::class),
        $this->createMock(NationalHighwaysService::class),
    );
});

/**
 * Call private extractPolygonBbox via reflection.
 *
 * @param  array<string, mixed>  $polygon
 * @return array{minLng: float, minLat: float, maxLng: float, maxLat: float}
 */
function extractPolygonBbox(RouteCheckService $service, array $polygon): array
{
    $method = (new ReflectionClass(RouteCheckService::class))->getMethod('extractPolygonBbox');

    return $method->invoke($service, $polygon);
}

test('extractPolygonBbox handles Polygon geometry', function () {
    $polygon = [
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [
                        [[-2.9, 51.0], [-2.8, 51.0], [-2.8, 51.1], [-2.9, 51.1], [-2.9, 51.0]],
                    ],
                ],
            ],
        ],
    ];

    $bbox = extractPolygonBbox($this->service, $polygon);

    expect($bbox)->toBe([
        'minLng' => -2.9,
        'minLat' => 51.0,
        'maxLng' => -2.8,
        'maxLat' => 51.1,
    ]);
});

test('extractPolygonBbox handles MultiPolygon geometry', function () {
    $polygon = [
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'MultiPolygon',
                    'coordinates' => [
                        [
                            [[-3.0, 50.9], [-2.9, 50.9], [-2.9, 51.0], [-3.0, 51.0], [-3.0, 50.9]],
                        ],
                        [
                            [[-2.7, 51.2], [-2.6, 51.2], [-2.6, 51.3], [-2.7, 51.3], [-2.7, 51.2]],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $bbox = extractPolygonBbox($this->service, $polygon);

    expect($bbox)->toBe([
        'minLng' => -3.0,
        'minLat' => 50.9,
        'maxLng' => -2.6,
        'maxLat' => 51.3,
    ]);
});

test('extractPolygonBbox handles polygon with holes', function () {
    $polygon = [
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [
                        [[-2.9, 51.0], [-2.8, 51.0], [-2.8, 51.1], [-2.9, 51.1], [-2.9, 51.0]],
                        [[-2.85, 51.02], [-2.82, 51.02], [-2.82, 51.05], [-2.85, 51.05], [-2.85, 51.02]],
                    ],
                ],
            ],
        ],
    ];

    $bbox = extractPolygonBbox($this->service, $polygon);

    expect($bbox)->toBe([
        'minLng' => -2.9,
        'minLat' => 51.0,
        'maxLng' => -2.8,
        'maxLat' => 51.1,
    ]);
});

test('extractPolygonBbox returns zero bbox when no coordinates', function () {
    $polygon = [
        'type' => 'FeatureCollection',
        'features' => [],
    ];

    $bbox = extractPolygonBbox($this->service, $polygon);

    expect($bbox)->toBe([
        'minLng' => 0.0,
        'minLat' => 0.0,
        'maxLng' => 0.0,
        'maxLat' => 0.0,
    ]);
});

/**
 * Call private filterIncidentsOnRoute via reflection.
 */
function filterIncidentsOnRoute(RouteCheckService $service, array $incidents, array $routeCoords, array $routeBbox, float $proximityKm): array
{
    $method = (new ReflectionClass(RouteCheckService::class))->getMethod('filterIncidentsOnRoute');

    return $method->invoke($service, $incidents, $routeCoords, $routeBbox, $proximityKm);
}

test('filterIncidentsOnRoute includes incident near segment midpoint (point-to-segment)', function () {
    $routeCoords = [
        [-2.83, 51.04],
        [-2.81, 51.04],
    ];
    $routeBbox = ['minLng' => -2.83, 'minLat' => 51.04, 'maxLng' => -2.81, 'maxLat' => 51.04];
    $incidents = [
        ['lat' => 51.044, 'lng' => -2.82, 'description' => 'Near segment midpoint'],
    ];
    $proximityKm = 0.5;

    $result = filterIncidentsOnRoute($this->service, $incidents, $routeCoords, $routeBbox, $proximityKm);

    expect($result)->toHaveCount(1)
        ->and($result[0]['description'])->toBe('Near segment midpoint')
        ->and($result[0]['distanceKm'])->toBeLessThan(0.5);
});

test('filterIncidentsOnRoute excludes incident outside bbox prefilter', function () {
    $routeCoords = [
        [-2.83, 51.04],
        [-2.81, 51.04],
    ];
    $routeBbox = ['minLng' => -2.83, 'minLat' => 51.04, 'maxLng' => -2.81, 'maxLat' => 51.04];
    $incidents = [
        ['lat' => 51.5, 'lng' => -2.0, 'description' => 'Far away'],
    ];
    $proximityKm = 0.5;

    $result = filterIncidentsOnRoute($this->service, $incidents, $routeCoords, $routeBbox, $proximityKm);

    expect($result)->toHaveCount(0);
});
