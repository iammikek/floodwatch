<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Coordinates (Langport, Somerset Levels)
    |--------------------------------------------------------------------------
    */

    'default_lat' => (float) env('FLOOD_WATCH_LAT', 51.0358),
    'default_long' => (float) env('FLOOD_WATCH_LONG', -2.8318),
    'default_radius_km' => (int) env('FLOOD_WATCH_RADIUS_KM', 15),

    /*
    |--------------------------------------------------------------------------
    | Environment Agency API
    |--------------------------------------------------------------------------
    */

    'environment_agency' => [
        'base_url' => env('ENVIRONMENT_AGENCY_URL', 'https://environment.data.gov.uk/flood-monitoring'),
        'timeout' => (int) env('ENVIRONMENT_AGENCY_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | National Highways API (DATEX II)
    |--------------------------------------------------------------------------
    */

    'national_highways' => [
        'base_url' => env('NATIONAL_HIGHWAYS_URL', 'https://api.data.nationalhighways.co.uk'),
        'api_key' => env('NATIONAL_HIGHWAYS_API_KEY'),
        'timeout' => (int) env('NATIONAL_HIGHWAYS_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache TTL in minutes for flood and road data. Identical queries within
    | this window return cached results without hitting the APIs.
    |
    */

    'cache_ttl_minutes' => (int) env('FLOOD_WATCH_CACHE_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | Cache store for flood/road data. Use "flood-watch" (Redis) in production,
    | "flood-watch-array" in testing (no Redis required).
    |
    */

    'cache_store' => env('FLOOD_WATCH_CACHE_STORE', 'flood-watch'),

];
