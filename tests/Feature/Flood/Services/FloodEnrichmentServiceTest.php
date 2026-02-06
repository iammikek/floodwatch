<?php

use App\Flood\Services\FloodEnrichmentService;

beforeEach(function () {
    $this->service = new FloodEnrichmentService;
});

test('haversine returns zero for same point', function () {
    $km = $this->service->haversineDistanceKm(51.0358, -2.8318, 51.0358, -2.8318);

    expect($km)->toBe(0.0);
});

test('haversine returns positive distance for different points', function () {
    $km = $this->service->haversineDistanceKm(51.0, -2.8, 52.0, -2.8);

    expect($km)->toBeGreaterThan(0);
    expect($km)->toBeLessThan(200);
});

test('enrich with distance adds distance when user location given', function () {
    $floods = [
        ['lat' => 51.04, 'long' => -2.83, 'area' => 'A'],
        ['lat' => 51.10, 'long' => -2.90, 'area' => 'B'],
    ];

    $result = $this->service->enrichWithDistance($floods, 51.0358, -2.8318);

    expect($result[0])->toHaveKey('distanceKm');
    expect($result[0]['distanceKm'])->toBeNumeric();
    expect($result[1])->toHaveKey('distanceKm');
    expect($result[0]['distanceKm'])->toBeLessThan($result[1]['distanceKm']);
});

test('enrich with distance sorts by proximity when user location given', function () {
    $floods = [
        ['lat' => 51.10, 'long' => -2.90, 'area' => 'Far'],
        ['lat' => 51.04, 'long' => -2.83, 'area' => 'Near'],
    ];

    $result = $this->service->enrichWithDistance($floods, 51.0358, -2.8318);

    expect($result[0]['area'])->toBe('Near');
    expect($result[1]['area'])->toBe('Far');
});

test('enrich with distance leaves distance null when no user location', function () {
    $floods = [
        ['lat' => 51.04, 'long' => -2.83, 'area' => 'A'],
    ];

    $result = $this->service->enrichWithDistance($floods, null, null);

    expect($result[0]['distanceKm'])->toBeNull();
});

test('enrich with distance sorts by time when no user location', function () {
    $floods = [
        ['lat' => 51.04, 'long' => -2.83, 'timeRaised' => '2024-01-01', 'area' => 'Old'],
        ['lat' => 51.05, 'long' => -2.84, 'timeMessageChanged' => '2024-01-02', 'area' => 'New'],
    ];

    $result = $this->service->enrichWithDistance($floods, null, null);

    expect($result[0]['area'])->toBe('New');
});

test('enrich with distance handles floods without coords', function () {
    $floods = [
        ['area' => 'NoCoords'],
    ];

    $result = $this->service->enrichWithDistance($floods, 51.0, -2.8);

    expect($result[0]['distanceKm'])->toBeNull();
});

test('enrich with distance returns empty array for empty input', function () {
    $result = $this->service->enrichWithDistance([], 51.0, -2.8);

    expect($result)->toBe([]);
});
