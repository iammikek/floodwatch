<?php

use App\Support\IncidentsOnRouteFilter;

beforeEach(function () {
    $this->filter = new IncidentsOnRouteFilter;
});

test('filter includes incident near segment midpoint (point-to-segment)', function () {
    $routeCoords = [
        [-2.83, 51.04],
        [-2.81, 51.04],
    ];
    $routeBbox = ['minLng' => -2.83, 'minLat' => 51.04, 'maxLng' => -2.81, 'maxLat' => 51.04];
    $incidents = [
        ['lat' => 51.044, 'lng' => -2.82, 'description' => 'Near segment midpoint'],
    ];
    $proximityKm = 0.5;
    $maxRoutePoints = 150;

    $result = $this->filter->filter($incidents, $routeCoords, $routeBbox, $proximityKm, $maxRoutePoints);

    expect($result)->toHaveCount(1)
        ->and($result[0]['description'])->toBe('Near segment midpoint')
        ->and($result[0]['distanceKm'])->toBeLessThan(0.5);
});

test('filter excludes incident outside bbox prefilter', function () {
    $routeCoords = [
        [-2.83, 51.04],
        [-2.81, 51.04],
    ];
    $routeBbox = ['minLng' => -2.83, 'minLat' => 51.04, 'maxLng' => -2.81, 'maxLat' => 51.04];
    $incidents = [
        ['lat' => 51.5, 'lng' => -2.0, 'description' => 'Far away'],
    ];
    $proximityKm = 0.5;
    $maxRoutePoints = 150;

    $result = $this->filter->filter($incidents, $routeCoords, $routeBbox, $proximityKm, $maxRoutePoints);

    expect($result)->toHaveCount(0);
});

test('filter excludes incident beyond proximity threshold', function () {
    $routeCoords = [
        [-2.83, 51.04],
        [-2.81, 51.04],
    ];
    $routeBbox = ['minLng' => -2.83, 'minLat' => 51.04, 'maxLng' => -2.81, 'maxLat' => 51.04];
    $incidents = [
        ['lat' => 51.2, 'lng' => -2.82, 'description' => 'Too far from route'],
    ];
    $proximityKm = 0.5;
    $maxRoutePoints = 150;

    $result = $this->filter->filter($incidents, $routeCoords, $routeBbox, $proximityKm, $maxRoutePoints);

    expect($result)->toHaveCount(0);
});

test('filter handles incident with longitude key instead of lng', function () {
    $routeCoords = [
        [-2.83, 51.04],
        [-2.82, 51.04],
    ];
    $routeBbox = ['minLng' => -2.83, 'minLat' => 51.04, 'maxLng' => -2.82, 'maxLat' => 51.04];
    $incidents = [
        ['latitude' => 51.04, 'longitude' => -2.825, 'description' => 'Uses latitude/longitude keys'],
    ];
    $proximityKm = 0.5;
    $maxRoutePoints = 150;

    $result = $this->filter->filter($incidents, $routeCoords, $routeBbox, $proximityKm, $maxRoutePoints);

    expect($result)->toHaveCount(1)
        ->and($result[0]['description'])->toBe('Uses latitude/longitude keys');
});

test('filter skips incidents without coordinates', function () {
    $routeCoords = [
        [-2.83, 51.04],
        [-2.81, 51.04],
    ];
    $routeBbox = ['minLng' => -2.83, 'minLat' => 51.04, 'maxLng' => -2.81, 'maxLat' => 51.04];
    $incidents = [
        ['description' => 'No coords'],
    ];
    $proximityKm = 0.5;
    $maxRoutePoints = 150;

    $result = $this->filter->filter($incidents, $routeCoords, $routeBbox, $proximityKm, $maxRoutePoints);

    expect($result)->toHaveCount(0);
});
