# Flood Watch

Laravel 12 application integrating flood data with National Highways road status for the **South West** (Bristol, Somerset, Devon, Cornwall). Correlates Environment Agency flood warnings, river and sea levels, and road incidents into a **Single Source of Truth** for flood and road viability.

## Scope: South West

The assistant covers the South West with **region-specific prompts** that tailor advice to the user's location:

| Region   | Postcode Areas | Focus                                                                 |
|----------|----------------|-----------------------------------------------------------------------|
| Somerset | BA, TA         | Somerset Levels, Muchelney cut-off risk, River Parrett, A361         |
| Bristol  | BS             | M5/M4, Avonmouth, Severn estuary                                      |
| Devon    | EX, TQ, PL     | A38, A30, A303, Exeter, Torbay, Plymouth, River Exe/Tamar            |
| Cornwall | TR             | A30, A38, coastal and river flood risk                                |

## LLM Tools

| Tool                 | Data Source                    | Description                                                                 |
|----------------------|--------------------------------|-----------------------------------------------------------------------------|
| **GetFloodData**     | Environment Agency             | Flood warnings and alerts by location                                      |
| **GetHighwaysIncidents** | National Highways v2.0 (DATEX II v3.4) | Planned and unplanned road/lane closures (M5, A38, A30, A303, A361, A372, etc.) |
| **GetFloodForecast** | Flood Guidance Statement (FGS) | 5-day flood risk outlook                                                    |
| **GetRiverLevels**   | Environment Agency             | Real-time river and sea levels (same data as [check-for-flooding.service.gov.uk](https://check-for-flooding.service.gov.uk/river-and-sea-levels)) |

Weather (5-day forecast with icons) and flood forecast are pre-fetched; the LLM calls the other tools as needed.

## Correlated Scenarios

| Scenario                | Expected AI Behavior                                                                 |
|-------------------------|----------------------------------------------------------------------------------------|
| High Water + Open Roads | "Flood Alert active. Roads are currently clear, but key routes are at risk. Plan your journey now." |
| High Water + Closed Roads | "⚠️ CRITICAL: Area may be isolated. Roads closed due to flooding. Do not attempt to travel." |
| Road Incident + No Flood | "Note: There is a road incident, but no active flood warnings for your area."           |
| Rising River Levels     | Correlates GetRiverLevels with flood warnings; predictive advice when levels are rising |

## System Prompt

The assistant uses a base prompt plus **region-specific guidance** injected from config:

- **Data Correlation**: Cross-reference flood warnings with road incidents and river levels
- **Contextual Awareness**: Somerset Levels (Muchelney), Bristol (Avonmouth), Devon (Exeter, Plymouth), Cornwall (coastal)
- **Prioritization**: "Danger to Life" → road closures → general flood alerts
- **Output Format**: "Current Status" section + "Action Steps" bulleted list

## Technical: LLM Tool Calling

The assistant uses **OpenAI tool calling** (openai-php/laravel). The LLM receives GetFloodData, GetHighwaysIncidents, GetFloodForecast, and GetRiverLevels, and orchestrates API calls to synthesize a correlated response.

## User Experience

- **Postcode (optional)**: Validates UK format and restricts to South West (BS, BA, TA, EX, TQ, PL, TR). Geocodes via postcodes.io for lat/long.
- **Dashboard**: Flood warnings (expandable full message, times), 5-day flood outlook, 5-day weather forecast with icons, road status, LLM summary.
- **Caching**: Redis (configurable TTL); falls back to array cache when Redis unavailable.
- **Graceful Degradation**: API timeouts and errors return empty data; the assistant states what it cannot access.

## AI Development

- **Laravel Boost** – MCP server, guidelines, documentation search
- **Cursor skills** – `.cursor/skills/` (livewire-development, pest-testing, tailwindcss-development) and `.cursor/rules/` tracked in version control

## Tech Stack

- **Laravel 12.x** – PHP 8.2+
- **Laravel Sail** – Docker development environment (includes Redis)
- **Livewire 4** – Real-time UI
- **Laravel Boost** – AI-assisted development (MCP, guidelines)
- **LLM integration** – openai-php/laravel for OpenAI tool calling
- **Redis** – Caching for flood/road/forecast data
- **TDD** – PHPUnit + Pest

## Requirements

- Docker & Docker Compose
- Composer

## Getting Started

```bash
# Install dependencies
composer install
yarn install

# Start Sail
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate

# Install Laravel Boost (for AI agents)
./vendor/bin/sail composer require laravel/boost --dev
./vendor/bin/sail artisan boost:install
```

Optional: add a `sail` alias to your shell for shorter commands.

## Development

```bash
sail up -d          # Start containers
sail test           # Run tests
sail artisan ...    # Artisan commands
```

Development plan (backlog, milestones): `docs/DEVELOPMENT.md`

## Build Pipeline

GitHub Actions runs a test pipeline on push/PR. The pipeline:

- Triggers on push and pull requests to the default branch
- Builds the frontend (`yarn install && yarn build`)
- Runs the full Pest test suite

Workflow file: `.github/workflows/tests.yml`

## Data Sources

| Source                 | API / Service                                      | Use                          |
|------------------------|----------------------------------------------------|------------------------------|
| Environment Agency     | [Real-Time flood-monitoring API](https://environment.data.gov.uk/flood-monitoring/doc/reference) | Flood warnings, river/sea levels |
| Check for flooding     | Same EA API as above                               | [river-and-sea-levels](https://check-for-flooding.service.gov.uk/river-and-sea-levels) |
| Flood Guidance Statement | FGS API (Met Office / EA)                        | 5-day flood risk forecast    |
| National Highways      | [Road and Lane Closures v2.0](https://developer.data.nationalhighways.co.uk/) (DATEX II v3.4) | Planned and unplanned road/lane closures |
| Open-Meteo             | Free weather API                                   | 5-day weather forecast       |
| postcodes.io           | Free geocoding                                     | Postcode → lat/long          |

## Attribution

Data use requires attribution. The dashboard displays a footer with:

- **Environment Agency** – Flood and river level data from the [Real-Time data API](https://environment.data.gov.uk/flood-monitoring/doc/reference) (Open Government Licence)
- **National Highways** – Road and lane closure data (DATEX II v3.4, [Developer Portal](https://developer.data.nationalhighways.co.uk/))
- **Open-Meteo** – Weather data under [CC-BY 4.0](https://open-meteo.com/en/licence)
- **postcodes.io** – Geocoding (contains OS, Royal Mail, ONS data © Crown copyright)

## Configuration

- **Default coordinates**: Langport (51.0358, -2.8318) in `config/flood-watch.php`
- **Region prompts**: `config/flood-watch.php` → `regions`
- **OpenAI API key**: Add `OPENAI_API_KEY` to `.env`
- **National Highways API key**: Register at the [National Highways Developer Portal](https://developer.data.nationalhighways.co.uk/) and add `NATIONAL_HIGHWAYS_API_KEY` to `.env`. API v2.0: `GET /roads/v2.0/closures?closureType=planned|unplanned`
- **Redis**: Use `REDIS_HOST=redis` with Sail; `flood-watch-array` cache store for tests

## License

Approved for non-commercial use.
