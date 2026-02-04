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
        'timeout' => (int) env('ENVIRONMENT_AGENCY_TIMEOUT', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flood Guidance Statement (5-day forecast)
    |--------------------------------------------------------------------------
    */

    'flood_forecast' => [
        'base_url' => env('FLOOD_FORECAST_URL', 'https://api.ffc-environment-agency.fgs.metoffice.gov.uk'),
        'timeout' => (int) env('FLOOD_FORECAST_TIMEOUT', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | National Highways API (DATEX II)
    |--------------------------------------------------------------------------
    */

    'national_highways' => [
        'base_url' => env('NATIONAL_HIGHWAYS_URL', 'https://api.data.nationalhighways.co.uk'),
        'api_key' => env('NATIONAL_HIGHWAYS_API_KEY'),
        'timeout' => (int) env('NATIONAL_HIGHWAYS_TIMEOUT', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache TTL in minutes for flood and road data. Set to 0 to disable caching.
    | Identical queries within this window return cached results without hitting the APIs.
    |
    */

    'cache_ttl_minutes' => (int) env('FLOOD_WATCH_CACHE_TTL_MINUTES', 0),

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

    /*
    |--------------------------------------------------------------------------
    | Muchelney Rule (Prompt-Only)
    |--------------------------------------------------------------------------
    |
    | The Muchelney rule is implemented via the system prompt: when River Parrett
    | levels are rising, the LLM proactively warns about Muchelney access. No
    | code-level threshold is required; the LLM correlates flood data with
    | road status from the tools.
    |
    */

];
