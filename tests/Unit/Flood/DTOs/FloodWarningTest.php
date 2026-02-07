<?php

use App\Flood\DTOs\FloodWarning;
use App\Flood\Enums\SeverityLevel;

test('fromArray creates FloodWarning from api response', function () {
    $data = [
        'description' => 'North Moor',
        'severity' => 'Flood Warning',
        'severityLevel' => 2,
        'message' => 'River levels are rising.',
        'floodAreaID' => '123',
        'timeRaised' => '2024-01-15T10:00:00Z',
        'lat' => 51.0,
        'long' => -2.8,
    ];

    $flood = FloodWarning::fromArray($data);

    expect($flood->description)->toBe('North Moor');
    expect($flood->severity)->toBe('Flood Warning');
    expect($flood->severityLevel)->toBe(SeverityLevel::Warning);
    expect($flood->floodAreaId)->toBe('123');
    expect($flood->lat)->toBe(51.0);
    expect($flood->lng)->toBe(-2.8);
});

test('fromArray handles empty and partial data', function () {
    $flood = FloodWarning::fromArray([]);

    expect($flood->description)->toBe('');
    expect($flood->severity)->toBe('');
    expect($flood->severityLevel)->toBe(SeverityLevel::Inactive);
    expect($flood->message)->toBe('');
    expect($flood->floodAreaId)->toBe('');
    expect($flood->timeRaised)->toBeNull();
    expect($flood->timeMessageChanged)->toBeNull();
    expect($flood->lat)->toBeNull();
    expect($flood->lng)->toBeNull();
    expect($flood->polygon)->toBeNull();
    expect($flood->distanceKm)->toBeNull();
});

test('fromArray parses timestamps correctly', function () {
    $data = [
        'description' => 'Test',
        'severity' => 'Alert',
        'severityLevel' => 3,
        'message' => '',
        'floodAreaID' => 'x',
        'timeRaised' => '2024-01-15T10:30:00Z',
        'timeMessageChanged' => '2024-01-15T11:00:00+00:00',
    ];

    $flood = FloodWarning::fromArray($data);

    expect($flood->timeRaised)->not->toBeNull();
    expect($flood->timeRaised->format('Y-m-d H:i'))->toBe('2024-01-15 10:30');
    expect($flood->timeMessageChanged)->not->toBeNull();
});

test('fromArray handles invalid timestamps as null', function () {
    $data = [
        'description' => 'Test',
        'severity' => 'Alert',
        'severityLevel' => 3,
        'message' => '',
        'floodAreaID' => 'x',
        'timeRaised' => 'not-a-date',
    ];

    $flood = FloodWarning::fromArray($data);

    expect($flood->timeRaised)->toBeNull();
});

test('toArray returns array without polygon by default', function () {
    $data = [
        'description' => 'Test',
        'severity' => 'Alert',
        'severityLevel' => 3,
        'message' => '',
        'floodAreaID' => 'x',
    ];

    $flood = FloodWarning::fromArray($data);
    $arr = $flood->toArray();

    expect($arr)->not->toHaveKey('polygon');
    expect($arr['severityLevel'])->toBe(3);
});

test('toArray includes distanceKm when set', function () {
    $data = [
        'description' => 'Test',
        'severity' => 'Alert',
        'severityLevel' => 3,
        'message' => '',
        'floodAreaID' => 'x',
        'distanceKm' => 5.2,
    ];

    $flood = FloodWarning::fromArray($data);
    $arr = $flood->toArray();

    expect($arr)->toHaveKey('distanceKm');
    expect($arr['distanceKm'])->toBe(5.2);
});

test('toArray includes polygon when set', function () {
    $polygon = ['type' => 'FeatureCollection', 'features' => []];
    $data = [
        'description' => 'Test',
        'severity' => 'Alert',
        'severityLevel' => 3,
        'message' => '',
        'floodAreaID' => 'x',
        'polygon' => $polygon,
    ];

    $flood = FloodWarning::fromArray($data);
    $arr = $flood->toArray();

    expect($arr['polygon'])->toBe($polygon);
});

test('withoutPolygon strips polygon from copy', function () {
    $data = [
        'description' => 'Test',
        'severity' => 'Alert',
        'severityLevel' => 3,
        'message' => '',
        'floodAreaID' => 'x',
        'polygon' => ['type' => 'FeatureCollection', 'features' => []],
    ];

    $flood = FloodWarning::fromArray($data);
    $stripped = $flood->withoutPolygon();

    expect($stripped->toArray())->not->toHaveKey('polygon');
    expect($stripped->polygon)->toBeNull();
});

test('round trip fromArray toArray preserves data', function () {
    $data = [
        'description' => 'North Moor',
        'severity' => 'Flood Warning',
        'severityLevel' => 2,
        'message' => 'River levels rising.',
        'floodAreaID' => '123',
        'timeRaised' => '2024-01-15T10:00:00Z',
        'lat' => 51.0,
        'long' => -2.8,
        'distanceKm' => 3.5,
    ];

    $flood = FloodWarning::fromArray($data);
    $arr = $flood->toArray();
    $rehydrated = FloodWarning::fromArray($arr);

    expect($rehydrated->description)->toBe($flood->description);
    expect($rehydrated->severityLevel)->toBe($flood->severityLevel);
    expect($rehydrated->distanceKm)->toBe($flood->distanceKm);
});
