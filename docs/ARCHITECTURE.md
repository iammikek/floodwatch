# Flood Watch Architecture

## Overview

Flood Watch correlates Environment Agency flood data with National Highways road status to provide a single source of truth for flood and road viability in the South West (Bristol, Somerset, Devon, Cornwall).

## Domain Structure

```
app/
├── Flood/                    # Flood domain
│   ├── DTOs/FloodWarning
│   ├── Enums/SeverityLevel
│   └── Services/
│       ├── EnvironmentAgencyFloodService
│       ├── FloodForecastService
│       └── RiverLevelService
├── Roads/                    # Roads domain
│   ├── DTOs/RoadIncident
│   └── Services/NationalHighwaysService
├── Services/                 # Orchestration
│   ├── FloodWatchService     # Main LLM orchestration
│   ├── FloodWatchPromptBuilder
│   ├── RiskCorrelationService
│   ├── LocationResolver
│   ├── PostcodeValidator
│   └── WeatherService
├── DTOs/RiskAssessment       # Cross-cutting
├── Enums/Region
└── ValueObjects/Postcode
```

## Data Flow

1. **User input** → LocationResolver (postcode/place) → coordinates + region
2. **FloodWatchService.chat()** fetches in parallel: forecast, weather, river levels
3. **LLM** receives system prompt + tools; calls GetFloodData, GetHighwaysIncidents, GetCorrelationSummary, etc.
4. **RiskCorrelationService** applies deterministic rules (flood↔road pairs, predictive warnings)
5. **Response** synthesized by LLM, cached, returned with floods/incidents/forecast

## Extension Points

### Prompts

- **Location**: `resources/prompts/{version}/system.txt`
- **Config**: `flood-watch.prompt_version` (env: `FLOOD_WATCH_PROMPT_VERSION`)
- **Snapshot tests**: `tests/Feature/Services/FloodWatchPromptBuilderTest.php` – update snapshots when changing prompts (`sail test -- --update-snapshots`)

### Region Logic

- **Config**: `config/flood-watch.regions` – prompt snippets per region
- **Correlation**: `config/flood-watch.correlation` – flood_area_road_pairs, predictive_rules, key_routes

### External APIs

| Service | Config key | Fixture |
|---------|------------|---------|
| Environment Agency | `flood-watch.environment_agency` | `tests/fixtures/environment_agency_*.json` |
| National Highways | `flood-watch.national_highways` | `tests/fixtures/national_highways_closures.json` |
| Flood Forecast | `flood-watch.flood_forecast` | - |
| Weather | `flood-watch.weather` | - |

### Resilience

- **Retry**: All HTTP calls use `retry(times, sleepMs, null, false)` – configurable per service
- **Circuit breaker**: Wired into all external API services (Environment Agency, Flood Forecast, River Level, National Highways, Weather). After N consecutive failures, the circuit opens and requests return empty until cooldown expires. Config: `flood-watch.circuit_breaker` (enabled, failure_threshold, cooldown_seconds). Set `FLOOD_WATCH_CIRCUIT_BREAKER_ENABLED=false` to disable.
- **Cache**: `flood-watch.cache_key_prefix` ensures distinct keys across services

### Logging

- **LogMasker**: Redacts user content, tool arguments, API responses before debug logs
- Sensitive data never appears in logs

## Performance & Scalability

### Cache Pre-warming

Run `php artisan flood-watch:warm-cache` to pre-populate the cache for common locations. Schedule in `routes/console.php`:

```php
Schedule::command('flood-watch:warm-cache --locations=Langport,TA10,Bristol')->hourly();
```

### Background Refresh

The main `chat()` flow is synchronous. For high traffic, consider:

- **Queue**: Move `FloodWatchService::chat()` to a job for async processing; return a job ID and poll for results
- **Cache TTL**: `flood-watch.cache_ttl_minutes` – balance freshness vs API load
- **Correlation**: `RiskCorrelationService::correlate()` is fast and in-memory; no queuing needed

### Scaling Notes

- **Redis**: Use for cache and trends in production (`flood-watch.cache_store`, `flood-watch.trends_enabled`)
- **Concurrency**: FloodWatchService fetches forecast, weather, river levels sequentially before LLM; could parallelize with `Concurrency::run()` if needed
- **Polygon limit**: `flood-watch.environment_agency.max_polygons_per_request` caps polygon fetches per request

## Key Files

| Purpose | File |
|---------|------|
| Main entry | `FloodWatchDashboard` (Livewire) |
| Orchestration | `FloodWatchService` |
| Correlation rules | `RiskCorrelationService` + config |
| Prompts | `FloodWatchPromptBuilder` + `resources/prompts/` |
| Cache warm | `flood-watch:warm-cache` command |
