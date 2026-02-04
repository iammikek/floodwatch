<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Coordinates (Langport, South West)
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
        'polygon_cache_hours' => (int) env('FLOOD_WATCH_POLYGON_CACHE_HOURS', 168),
        'max_polygons_per_request' => (int) env('FLOOD_WATCH_MAX_POLYGONS', 10),
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
    | Weather (Open-Meteo - free, no API key)
    |--------------------------------------------------------------------------
    */

    'weather' => [
        'base_url' => env('WEATHER_API_URL', 'https://api.open-meteo.com/v1'),
        'timeout' => (int) env('WEATHER_API_TIMEOUT', 10),
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
    | Trends (Redis)
    |--------------------------------------------------------------------------
    |
    | Store search results in Redis for trend analysis. Set to false to disable.
    | Retention days: how long to keep trend records.
    |
    */

    'trends_enabled' => env('FLOOD_WATCH_TRENDS_ENABLED', true),
    'trends_retention_days' => (int) env('FLOOD_WATCH_TRENDS_RETENTION_DAYS', 30),
    'trends_key' => env('FLOOD_WATCH_TRENDS_KEY', 'flood-watch:trends'),

    /*
    |--------------------------------------------------------------------------
    | Region-Specific Prompts
    |--------------------------------------------------------------------------
    |
    | Geographic guidance injected into the system prompt based on the user's
    | postcode. Each region maps postcode areas (BS, BA, TA, etc.) to tailored
    | flood/road correlation logic.
    |
    */

    'regions' => [
        'somerset' => [
            'areas' => ['BA', 'TA'],
            'prompt' => "**Somerset Levels focus**: Muchelney is prone to being cut off. If River Parrett levels are rising, warn about Muchelney access even if the Highways API has not updated (predictive warning). Cross-reference North Moor or King's Sedgemoor flood warnings with GetHighwaysIncidents for the A361 at East Lyng. Key routes: A361, A372, M5 J23–J25.",
        ],
        'bristol' => [
            'areas' => ['BS'],
            'prompt' => '**Bristol focus**: Key routes M5, M4, A38. Avonmouth and Severn estuary coastal flood risk. Cross-reference flood warnings with M5 and M4 junction incidents.',
        ],
        'devon' => [
            'areas' => ['EX', 'TQ', 'PL'],
            'prompt' => '**Devon focus**: Key routes A38, A30, A303, M5. Exeter (River Exe), Torbay, Plymouth (River Tamar, coastal) flood risk. Cross-reference flood warnings with A38 and A30 incidents.',
        ],
        'cornwall' => [
            'areas' => ['TR'],
            'prompt' => '**Cornwall focus**: Key routes A30, A38. Coastal and river flood risk, especially west Cornwall. Cross-reference flood warnings with A30 incidents.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Correlation Rules
    |--------------------------------------------------------------------------
    |
    | Deterministic rules for cross-referencing flood warnings with road
    | incidents. Used by RiskCorrelationService for testable, auditable logic.
    |
    */

    'correlation' => [
        'somerset' => [
            'flood_area_road_pairs' => [
                ['North Moor', 'A361'],
                ['Sedgemoor', 'A361'],
            ],
            'predictive_rules' => [
                [
                    'river_pattern' => 'parrett',
                    'trigger_level' => 'elevated',
                    'warning' => 'Muchelney may be cut off when River Parrett is elevated. Check route before travelling.',
                ],
            ],
            'key_routes' => ['A361', 'A372', 'M5 J23', 'M5 J24', 'M5 J25'],
        ],
        'bristol' => [
            'key_routes' => ['M5', 'M4', 'A38'],
        ],
        'devon' => [
            'key_routes' => ['A38', 'A30', 'A303', 'M5'],
        ],
        'cornwall' => [
            'key_routes' => ['A30', 'A38'],
        ],
    ],

];
