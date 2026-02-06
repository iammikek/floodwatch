<?php

use App\Services\SearchMessageBuilder;

beforeEach(function () {
    $this->builder = new SearchMessageBuilder;
});

test('build returns default message when location empty', function () {
    $message = $this->builder->build('', null);

    expect($message)->toContain('South West');
    expect($message)->toContain('Bristol');
});

test('build returns location message with coords when validation has lat and long', function () {
    $validation = [
        'lat' => 51.0358,
        'long' => -2.8318,
        'display_name' => 'Langport',
    ];

    $message = $this->builder->build('TA10 0AA', $validation);

    expect($message)->toContain('Langport');
    expect($message)->toContain('51.0358');
    expect($message)->toContain('-2.8318');
});

test('build uses location as label when validation has no display_name', function () {
    $validation = [
        'lat' => 51.0,
        'long' => -2.8,
    ];

    $message = $this->builder->build('Bristol', $validation);

    expect($message)->toContain('Bristol');
});

test('build uses location when validation is null', function () {
    $message = $this->builder->build('Somerset', null);

    expect($message)->toContain('Somerset');
});
