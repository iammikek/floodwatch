<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized config keys for the flood-watch namespace.
 * Use these constants to avoid typos and ease future renames.
 */
final class ConfigKey
{
    // General
    public const PREFIX = 'flood-watch.';

    // Prompt / regions
    public const PROMPT_VERSION = 'flood-watch.prompt_version';

    public const REGIONS = 'flood-watch.regions';

    // LLM limits
    public const LLM_MAX_FLOODS = 'flood-watch.llm_max_floods';

    public const LLM_MAX_INCIDENTS = 'flood-watch.llm_max_incidents';

    public const LLM_MAX_RIVER_LEVELS = 'flood-watch.llm_max_river_levels';

    public const LLM_MAX_FORECAST_CHARS = 'flood-watch.llm_max_forecast_chars';

    public const LLM_MAX_FLOOD_MESSAGE_CHARS = 'flood-watch.llm_max_flood_message_chars';

    public const LLM_MAX_CONTEXT_TOKENS = 'flood-watch.llm_max_context_tokens';

    public const LLM_MAX_CORRELATION_CHARS = 'flood-watch.llm_max_correlation_chars';

    // Defaults / location
    public const DEFAULT_LAT = 'flood-watch.default_lat';

    public const DEFAULT_LNG = 'flood-watch.default_lng';

    public const DEFAULT_RADIUS_KM = 'flood-watch.default_radius_km';

    // Cache
    public const CACHE_TTL_MINUTES = 'flood-watch.cache_ttl_minutes';

    public const CACHE_STORE = 'flood-watch.cache_store';

    public const CACHE_KEY_PREFIX = 'flood-watch.cache_key_prefix';

    // Providers
    public const ENVIRONMENT_AGENCY = 'flood-watch.environment_agency';

    public const FLOOD_FORECAST = 'flood-watch.flood_forecast';

    public const WEATHER = 'flood-watch.weather';

    public const NATIONAL_HIGHWAYS = 'flood-watch.national_highways';

    // Circuit breaker
    public const CIRCUIT_BREAKER = 'flood-watch.circuit_breaker';
}
