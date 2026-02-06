<?php

use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $lat = config('flood-watch.default_lat', 51.0358);
    $long = config('flood-watch.default_long', -2.8318);
    Cache::put("flood-watch:map-data:{$lat}:{$long}", [
        'floods' => [],
        'incidents' => [],
        'riverLevels' => [],
        'lastChecked' => null,
    ], 300);
    Cache::put('flood-watch-risk-gauge', [
        'index' => 0,
        'label' => 'Low',
        'summary' => 'No active alerts.',
    ], 900);
});

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
