<?php

use App\Roads\DTOs\RoadIncident;

test('fromArray creates RoadIncident from standard keys', function () {
    $data = [
        'road' => 'A361',
        'status' => 'Closed',
        'incidentType' => 'Flooding',
        'delayTime' => '30 minutes',
    ];

    $incident = RoadIncident::fromArray($data);

    expect($incident->road)->toBe('A361');
    expect($incident->status)->toBe('Closed');
    expect($incident->incidentType)->toBe('Flooding');
    expect($incident->delayTime)->toBe('30 minutes');
});

test('fromArray uses alternative keys from National Highways API', function () {
    $data = [
        'roadName' => 'M5',
        'closureStatus' => 'Partial closure',
        'type' => 'Lane closure',
        'delay' => '15 mins',
    ];

    $incident = RoadIncident::fromArray($data);

    expect($incident->road)->toBe('M5');
    expect($incident->status)->toBe('Partial closure');
    expect($incident->incidentType)->toBe('Lane closure');
    expect($incident->delayTime)->toBe('15 mins');
});

test('fromArray uses location as fallback for road', function () {
    $data = [
        'location' => 'A30 near Exeter',
        'status' => '',
        'incidentType' => '',
        'delayTime' => '',
    ];

    $incident = RoadIncident::fromArray($data);

    expect($incident->road)->toBe('A30 near Exeter');
});

test('fromArray handles empty data', function () {
    $incident = RoadIncident::fromArray([]);

    expect($incident->road)->toBe('');
    expect($incident->status)->toBe('');
    expect($incident->incidentType)->toBe('');
    expect($incident->delayTime)->toBe('');
});

test('toArray returns correct structure', function () {
    $data = [
        'road' => 'A372',
        'status' => 'Open',
        'incidentType' => 'Roadworks',
        'delayTime' => 'None',
    ];

    $incident = RoadIncident::fromArray($data);
    $arr = $incident->toArray();

    expect($arr)->toBe([
        'road' => 'A372',
        'status' => 'Open',
        'incidentType' => 'Roadworks',
        'delayTime' => 'None',
    ]);
});

test('round trip fromArray toArray preserves data', function () {
    $data = [
        'road' => 'M5',
        'status' => 'Lane closed',
        'incidentType' => 'Flood damage',
        'delayTime' => '45 minutes',
    ];

    $incident = RoadIncident::fromArray($data);
    $arr = $incident->toArray();
    $rehydrated = RoadIncident::fromArray($arr);

    expect($rehydrated->road)->toBe($incident->road);
    expect($rehydrated->status)->toBe($incident->status);
    expect($rehydrated->incidentType)->toBe($incident->incidentType);
    expect($rehydrated->delayTime)->toBe($incident->delayTime);
});
