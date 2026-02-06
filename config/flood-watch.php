<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prompt Version
    |--------------------------------------------------------------------------
    |
    | Version of the system prompt to load from resources/prompts/{version}/.
    | Bump this when making breaking prompt changes; snapshot tests will fail
    | until updated.
    |
    */

    'prompt_version' => env('FLOOD_WATCH_PROMPT_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Admin Email
    |--------------------------------------------------------------------------
    |
    | Users who register with this email receive the admin role automatically.
    |
    */

    'admin_email' => env('ADMIN_EMAIL', 'mike@automica.io'),

    /*
    |--------------------------------------------------------------------------
    | LLM Token Limits
    |--------------------------------------------------------------------------
    |
    | Limits applied to tool results before sending to the LLM to avoid
    | exceeding context length (128k tokens). Reduce these if you hit limits.
    |
    */

    'llm_max_floods' => (int) env('FLOOD_WATCH_LLM_MAX_FLOODS', 12),
    'llm_max_incidents' => (int) env('FLOOD_WATCH_LLM_MAX_INCIDENTS', 12),
    'llm_max_river_levels' => (int) env('FLOOD_WATCH_LLM_MAX_RIVER_LEVELS', 8),
    'llm_max_forecast_chars' => (int) env('FLOOD_WATCH_LLM_MAX_FORECAST_CHARS', 1200),
    'llm_max_flood_message_chars' => (int) env('FLOOD_WATCH_LLM_MAX_FLOOD_MESSAGE_CHARS', 150),
    'llm_max_context_tokens' => (int) env('FLOOD_WATCH_LLM_MAX_CONTEXT_TOKENS', 110000),
    'llm_max_correlation_chars' => (int) env('FLOOD_WATCH_LLM_MAX_CORRELATION_CHARS', 8000),

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
        'trend_hours' => (int) env('FLOOD_WATCH_TREND_HOURS', 24),
        'polygon_cache_hours' => (int) env('FLOOD_WATCH_POLYGON_CACHE_HOURS', 168),
        'max_polygons_per_request' => (int) env('FLOOD_WATCH_MAX_POLYGONS', 10),
        'river_boundary_geojson_url' => env('FLOOD_WATCH_RIVER_BOUNDARY_GEOJSON_URL'),
        'retry_times' => (int) env('FLOOD_WATCH_EA_RETRY_TIMES', 3),
        'retry_sleep_ms' => (int) env('FLOOD_WATCH_EA_RETRY_SLEEP_MS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flood Guidance Statement (5-day forecast)
    |--------------------------------------------------------------------------
    */

    'flood_forecast' => [
        'base_url' => env('FLOOD_FORECAST_URL', 'https://api.ffc-environment-agency.fgs.metoffice.gov.uk'),
        'timeout' => (int) env('FLOOD_FORECAST_TIMEOUT', 25),
        'retry_times' => (int) env('FLOOD_WATCH_FORECAST_RETRY_TIMES', 3),
        'retry_sleep_ms' => (int) env('FLOOD_WATCH_FORECAST_RETRY_SLEEP_MS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Weather (Open-Meteo - free, no API key)
    |--------------------------------------------------------------------------
    */

    'weather' => [
        'base_url' => env('WEATHER_API_URL', 'https://api.open-meteo.com/v1'),
        'timeout' => (int) env('WEATHER_API_TIMEOUT', 10),
        'retry_times' => (int) env('FLOOD_WATCH_WEATHER_RETRY_TIMES', 3),
        'retry_sleep_ms' => (int) env('FLOOD_WATCH_WEATHER_RETRY_SLEEP_MS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | National Highways API (DATEX II)
    |--------------------------------------------------------------------------
    */

    'national_highways' => [
        'base_url' => env('NATIONAL_HIGHWAYS_URL', 'https://api.data.nationalhighways.co.uk/roads/v2.0'),
        'api_key' => env('NATIONAL_HIGHWAYS_API_KEY'),
        'timeout' => (int) env('NATIONAL_HIGHWAYS_TIMEOUT', 25),
        'retry_times' => (int) env('FLOOD_WATCH_NH_RETRY_TIMES', 3),
        'retry_sleep_ms' => (int) env('FLOOD_WATCH_NH_RETRY_SLEEP_MS', 100),
        'closures_path' => env('NATIONAL_HIGHWAYS_CLOSURES_PATH', 'closures'),
        'fetch_unplanned' => env('NATIONAL_HIGHWAYS_FETCH_UNPLANNED', true),
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
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all flood-watch cache keys. Ensures distinct keys across
    | services (flood data, polygons, circuit breaker, etc.).
    |
    */

    'cache_key_prefix' => env('FLOOD_WATCH_CACHE_PREFIX', 'flood-watch'),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | After this many consecutive failures, the circuit opens and requests
    | are skipped for cooldown_seconds. Set to 0 to disable.
    |
    */

    'circuit_breaker' => [
        'failure_threshold' => (int) env('FLOOD_WATCH_CIRCUIT_FAILURE_THRESHOLD', 5),
        'cooldown_seconds' => (int) env('FLOOD_WATCH_CIRCUIT_COOLDOWN', 60),
        'enabled' => env('FLOOD_WATCH_CIRCUIT_BREAKER_ENABLED', true),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Incident Road Filter (County Limits)
    |--------------------------------------------------------------------------
    |
    | Road incidents are filtered to only show M4, M5 and A roads within the
    | South West. When the user's region is known, only that region's key_routes
    | are shown. When unknown, this union of all South West roads is used.
    |
    */

    'incident_allowed_roads' => [
        'A30', 'A303', 'A361', 'A372', 'A38', 'M4', 'M5',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Grid: Monitored Routes Count
    |--------------------------------------------------------------------------
    |
    | Number of South West routes we monitor (A30, A303, A361, A372, A38, M4, M5).
    | Used for "X incidents on N monitored routes" in the status grid.
    |
    */
    'status_grid_monitored_routes' => 7,

    /*
    |--------------------------------------------------------------------------
    | Fallback Coordinates for Road Incidents (Map Display)
    |--------------------------------------------------------------------------
    |
    | When the National Highways API does not return geometry (posList), use
    | these approximate center points for South West roads so incidents still
    | appear on the map. Format: road => [lat, long].
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Road Incident Type Icons
    |--------------------------------------------------------------------------
    |
    | Maps incident types (from National Highways DATEX II) to emoji icons
    | for the road status UI and map. Keys are matched case-insensitively
    | against incidentType and managementType. First match wins.
    | UK road sign equivalents: 🚧 (road works), 🚫 (road closed), etc.
    |
    */

    'incident_icons' => [
        'flooding' => '🌊',
        'roadClosed' => '🚫',
        'laneClosures' => '⚠️',
        'lane closure' => '⚠️',
        'constructionWork' => '🚧',
        'maintenanceWork' => '🛠️',
        'sweepingOfRoad' => '🧹',
        'roadworks' => '🚧',
        'roadWorks' => '🚧',
        'road works' => '🚧',
        'accident' => '🚗',
        'vehicleObstruction' => '🚗',
        'default' => '🛣️',
    ],

    'incident_road_coordinates' => [
        'A361' => [51.04, -2.83],
        'A372' => [51.07, -2.90],
        'A30' => [50.72, -3.53],
        'A303' => [51.02, -2.44],
        'A38' => [50.72, -3.53],
        'M5' => [51.45, -2.58],
        'M4' => [51.45, -2.58],
    ],

    /*
    |--------------------------------------------------------------------------
    | Infrastructure Points (Reservoirs, Pumping Stations, etc.)
    |--------------------------------------------------------------------------
    |
    | Reservoirs and water infrastructure across the South West (Somerset, Devon,
    | Cornwall, Bristol, Gloucestershire, Wiltshire, Dorset). Coordinates from
    | Wikipedia/OpenStreetMap. Add entries: ['name' => string, 'lat' => float,
    | 'long' => float, 'type' => 'reservoir'|'pumping_station'|etc].
    |
    */
    'infrastructure_points' => [
        // Somerset (Bristol Water, Wessex Water)
        ['name' => 'Chew Valley Lake', 'lat' => 51.3347, 'long' => -2.6180, 'type' => 'reservoir'],
        ['name' => 'Blagdon Lake', 'lat' => 51.3667, 'long' => -2.7167, 'type' => 'reservoir'],
        ['name' => 'Cheddar Reservoir', 'lat' => 51.2833, 'long' => -2.7667, 'type' => 'reservoir'],
        ['name' => 'Wimbleball Lake', 'lat' => 51.0500, 'long' => -3.4333, 'type' => 'reservoir'],
        ['name' => 'Clatworthy Reservoir', 'lat' => 51.0833, 'long' => -3.3167, 'type' => 'reservoir'],
        ['name' => 'Durleigh Reservoir', 'lat' => 51.1167, 'long' => -3.0500, 'type' => 'reservoir'],
        ['name' => 'Sutton Bingham Reservoir', 'lat' => 50.9000, 'long' => -2.7167, 'type' => 'reservoir'],
        ['name' => 'Hawkridge Reservoir', 'lat' => 51.0833, 'long' => -3.1833, 'type' => 'reservoir'],
        ['name' => 'Chard Reservoir', 'lat' => 50.8667, 'long' => -2.9667, 'type' => 'reservoir'],
        ['name' => 'Ashford Reservoir', 'lat' => 51.3833, 'long' => -2.6167, 'type' => 'reservoir'],
        ['name' => 'Barrow Gurney Reservoirs', 'lat' => 51.4000, 'long' => -2.6667, 'type' => 'reservoir'],
        ['name' => 'Otterhead Lakes', 'lat' => 50.9333, 'long' => -2.9833, 'type' => 'reservoir'],
        ['name' => 'Nutscale Reservoir', 'lat' => 51.1500, 'long' => -3.5667, 'type' => 'reservoir'],
        ['name' => 'Leigh Reservoir', 'lat' => 51.2333, 'long' => -2.6500, 'type' => 'reservoir'],
        ['name' => 'Litton Reservoirs', 'lat' => 51.2833, 'long' => -2.5833, 'type' => 'reservoir'],
        ['name' => 'Luxhay Reservoir', 'lat' => 51.0833, 'long' => -3.3833, 'type' => 'reservoir'],
        // Devon (South West Water)
        ['name' => 'Roadford Lake', 'lat' => 50.7000, 'long' => -4.2292, 'type' => 'reservoir'],
        ['name' => 'Wistlandpound Reservoir', 'lat' => 51.1833, 'long' => -3.9000, 'type' => 'reservoir'],
        ['name' => 'Burrator Reservoir', 'lat' => 50.5167, 'long' => -4.0333, 'type' => 'reservoir'],
        ['name' => 'Fernworthy Reservoir', 'lat' => 50.6167, 'long' => -3.8500, 'type' => 'reservoir'],
        ['name' => 'Meldon Reservoir', 'lat' => 50.7000, 'long' => -4.0167, 'type' => 'reservoir'],
        ['name' => 'Upper Tamar Lake', 'lat' => 50.8833, 'long' => -4.4500, 'type' => 'reservoir'],
        ['name' => 'Lower Tamar Lake', 'lat' => 50.8500, 'long' => -4.4500, 'type' => 'reservoir'],
        ['name' => 'Tottiford Reservoir', 'lat' => 50.6867, 'long' => -3.6417, 'type' => 'reservoir'],
        ['name' => 'Trenchford Reservoir', 'lat' => 50.6817, 'long' => -3.6250, 'type' => 'reservoir'],
        ['name' => 'Kennick Reservoir', 'lat' => 50.6783, 'long' => -3.6333, 'type' => 'reservoir'],
        ['name' => 'Venford Reservoir', 'lat' => 50.5167, 'long' => -3.8833, 'type' => 'reservoir'],
        ['name' => 'Avon Dam Reservoir', 'lat' => 50.4833, 'long' => -3.8833, 'type' => 'reservoir'],
        ['name' => 'Slade Reservoir', 'lat' => 50.4833, 'long' => -3.5333, 'type' => 'reservoir'],
        // Cornwall (South West Water)
        ['name' => 'Colliford Lake', 'lat' => 50.5277, 'long' => -4.5710, 'type' => 'reservoir'],
        ['name' => 'Stithians Reservoir', 'lat' => 50.1815, 'long' => -5.2036, 'type' => 'reservoir'],
        ['name' => 'Siblyback Lake', 'lat' => 50.5000, 'long' => -4.4833, 'type' => 'reservoir'],
        ['name' => 'Porth Reservoir', 'lat' => 50.4167, 'long' => -5.0500, 'type' => 'reservoir'],
        ['name' => 'Drift Reservoir', 'lat' => 50.1167, 'long' => -5.5500, 'type' => 'reservoir'],
        ['name' => 'Crowdy Reservoir', 'lat' => 50.6833, 'long' => -4.5500, 'type' => 'reservoir'],
        ['name' => 'Argal Reservoir', 'lat' => 50.1500, 'long' => -5.0833, 'type' => 'reservoir'],
        ['name' => 'College Reservoir', 'lat' => 50.1500, 'long' => -5.0833, 'type' => 'reservoir'],
        ['name' => 'Bussow Reservoir', 'lat' => 50.2167, 'long' => -5.4833, 'type' => 'reservoir'],
        ['name' => 'Boscathnoe Reservoir', 'lat' => 50.1167, 'long' => -5.5500, 'type' => 'reservoir'],
        // Gloucestershire
        ['name' => 'Dowdeswell Reservoir', 'lat' => 51.8500, 'long' => -1.9167, 'type' => 'reservoir'],
        ['name' => 'Witcombe Reservoir', 'lat' => 51.8167, 'long' => -2.0833, 'type' => 'reservoir'],
        // Wiltshire
        ['name' => 'Wilton Water', 'lat' => 51.3833, 'long' => -1.8500, 'type' => 'reservoir'],
    ],

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
                [
                    'flood_pattern' => 'langport',
                    'trigger_severity_max' => 2,
                    'warning' => 'Muchelney may be cut off when Langport has flood warnings. Check route before travelling.',
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
