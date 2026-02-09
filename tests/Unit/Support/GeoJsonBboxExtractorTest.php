<?php

use App\Support\GeoJsonBboxExtractor;

beforeEach(function () {
    $this->extractor = new GeoJsonBboxExtractor;
});

test('extractBboxFromFeatureCollection handles Polygon geometry', function () {
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

    $bbox = $this->extractor->extractBboxFromFeatureCollection($polygon);

    expect($bbox)->toBe([
        'minLng' => -2.9,
        'minLat' => 51.0,
        'maxLng' => -2.8,
        'maxLat' => 51.1,
    ]);
});

test('extractBboxFromFeatureCollection handles MultiPolygon geometry', function () {
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

    $bbox = $this->extractor->extractBboxFromFeatureCollection($polygon);

    expect($bbox)->toBe([
        'minLng' => -3.0,
        'minLat' => 50.9,
        'maxLng' => -2.6,
        'maxLat' => 51.3,
    ]);
});

test('extractBboxFromFeatureCollection handles polygon with holes', function () {
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

    $bbox = $this->extractor->extractBboxFromFeatureCollection($polygon);

    expect($bbox)->toBe([
        'minLng' => -2.9,
        'minLat' => 51.0,
        'maxLng' => -2.8,
        'maxLat' => 51.1,
    ]);
});

test('extractBboxFromFeatureCollection returns zero bbox when no coordinates', function () {
    $polygon = [
        'type' => 'FeatureCollection',
        'features' => [],
    ];

    $bbox = $this->extractor->extractBboxFromFeatureCollection($polygon);

    expect($bbox)->toBe([
        'minLng' => 0.0,
        'minLat' => 0.0,
        'maxLng' => 0.0,
        'maxLat' => 0.0,
    ]);
});
