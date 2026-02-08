<?php

use App\Enums\Region;

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

    'donation_url' => env('FLOOD_WATCH_DONATION_URL', ''),

    'warm_cache_locations' => collect(Region::cases())
        ->mapWithKeys(fn (Region $r) => [$r->value => $r->warmCacheLocation()])
        ->all(),

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
    | LLM Cost Tracking (Admin Dashboard)
    |--------------------------------------------------------------------------
    |
    | Uses OpenAI Usage API. Budget in USD; alert at 80%.
    | Token pricing: gpt-4o-mini input $0.15/1M, output $0.60/1M.
    | llm_budget_initial: prepaid credit (e.g. 10 for $10). Remaining = initial - spend.
    |
    */

    'llm_cost_input_per_m' => (float) env('FLOOD_WATCH_LLM_COST_INPUT_PER_M', 0.15),
    'llm_cost_output_per_m' => (float) env('FLOOD_WATCH_LLM_COST_OUTPUT_PER_M', 0.60),
    'llm_budget_monthly' => (float) env('FLOOD_WATCH_LLM_BUDGET_MONTHLY', 0),
    'llm_budget_initial' => (float) env('FLOOD_WATCH_LLM_BUDGET_INITIAL', 0),

    /*
    |--------------------------------------------------------------------------
    | Default Coordinates (Langport, South West)
    |--------------------------------------------------------------------------
    |
    | FLOOD_WATCH_LAT and FLOOD_WATCH_LNG for the default map center.
    |
    */

    'default_lat' => (float) env('FLOOD_WATCH_LAT', 51.0358),
    'default_lng' => (float) env('FLOOD_WATCH_LNG', -2.8318),
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
    | Fallback Coordinates for Road Incidents (Map Display)
    |--------------------------------------------------------------------------
    |
    | When the National Highways API does not return geometry (posList), use
    | these approximate center points for South West roads so incidents still
    | appear on the map. Format: road => [lat, lng].
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

    /*
    |--------------------------------------------------------------------------
    | Emergency Services (Danger to Life)
    |--------------------------------------------------------------------------
    |
    | Phone numbers and instructions shown when severe flood warnings
    | (danger to life) are detected. Used by the UI and LLM prompts.
    |
    */

    'emergency' => [
        '999' => 'Emergency (life at risk)',
        'floodline' => '0345 988 1188',
        'floodline_label' => 'Environment Agency Floodline (24/7)',
        'instructions' => [
            'Evacuate if advised by emergency services.',
            'Move to higher ground.',
            'Do not enter floodwater.',
            'Call 999 if life at risk.',
        ],
    ],

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
