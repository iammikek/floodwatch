# Flood Watch

Laravel 12 POC integrating high-impact rural flood data with National Highways v2.0 (DATEX II) infrastructure for the Somerset Levels. In the Levels, a flood is rarely an isolated event; it is a logistical crisis. The goal is to provide a **Single Source of Truth** that correlates rising water levels with road viability.

## POC Scope: Somerset Levels Logic

The AI is refined to handle the specific geography of the Somerset Levels—Sedgemoor and South Somerset districts.

| Tool | Data Source | Coverage |
|------|-------------|----------|
| **Primary Tool A (Floods)** | Environment Agency | River Parrett and River Tone (Langport, Muchelney, Burrowbridge) |
| **Primary Tool B (Highways)** | National Highways v2.0 (DATEX II) | A361, A372, M5 (J23–J25) |

**Muchelney Logic**: If the flood level at Langport exceeds a specific threshold, the AI must proactively check road status for Muchelney, which becomes an "island" during floods.

## Correlated Scenarios (Acceptance Criteria)

| Scenario | Expected AI Behavior |
|----------|----------------------|
| High Water + Open Roads | "Flood Alert active at Langport. Roads are currently clear, but the A361 is at risk. Plan your journey now." |
| High Water + Closed Roads | "⚠️ CRITICAL: Muchelney is currently isolated. The A372 is closed due to flooding. Do not attempt to travel." |
| Road Accident + No Flood | "Note: There is a road incident on the M5 near Bridgwater, but no active flood warnings for the Levels." |

## Correlated Insight System Prompt

The system prompt instructs the AI to think like a Somerset local coordinator:

- **Data Correlation**: If GetFloodData shows a warning for North Moor or King's Sedgemoor, immediately cross-reference GetRoadStatus for the A361 at East Lyng.
- **Contextual Awareness**: Muchelney is prone to being cut off. If River Parrett levels are rising, warn users about access to Muchelney even if the Highways API has not updated (predictive warning).
- **Prioritization**: Prioritize "Danger to Life" alerts, then road closures, then general flood alerts.

## Technical: LLM Tool Calling

The Somerset Assistant uses **OpenAI tool calling** (openai-php/laravel). The LLM receives two tools—`GetFloodData` (Environment Agency) and `GetHighwaysIncidents` (National Highways)—and decides when to call them. The LLM orchestrates the API calls and synthesizes a correlated response. No Concurrency facade is used; the LLM drives the flow.

## Summary Table for Stakeholders

| Category    | Requirement        | Metric for Success                                                                 |
|-------------|--------------------|------------------------------------------------------------------------------------|
| Reliability | API Error Handling | < 1% of queries result in a generic "Server Error."                                |
| Safety      | Hallucination Rate | 0% instances of the AI inventing flood warnings not present in the API.             |
| Utility     | Actionability      | 90% of test users can identify their local flood risk level within 2 prompts.      |

## User Experience & Reliability

The POC should feel like a cohesive tool, not a disjointed chat interface.

- **Latency Threshold**: Total "Time to First Token" (the time it takes for the AI to start typing) should not exceed 3 seconds under normal network conditions.
- **Structured Output**: 100% of LLM responses must follow a consistent Markdown format, including a "Current Status" header and an "Action Steps" bulleted list.
- **Graceful Degradation**: If the Environment Agency API is down, the AI must explicitly state it cannot access real-time data and provide general safety links instead of making up hypothetical risks.
- **Asynchronous Handling**: The UI must remain responsive. Success is defined by the Livewire component displaying a "Searching real-time records..." state without blocking the main browser thread.
- **Token Efficiency**: Redis caching (15-minute TTL) ensures identical postcode queries return cached results without a new LLM prompt. Falls back to array cache when Redis is unavailable.

## Geospatial & Data Requirements

- **Geospatial Relevance**: The agent must correctly filter the Environment Agency API results to only include warnings within a 10km radius centre, as defined by a postcode.
- **Postcode Validation**: `PostcodeValidator` validates UK postcode format and restricts to Somerset Levels (TA3–TA11, BA3–BA9, BS26–BS28). Invalid or out-of-area postcodes show an error before the LLM is called. Optional geocoding via postcodes.io provides lat/long for the LLM's GetFloodData tool.
- **Severity Mapping**: The AI must accurately translate EA severity levels (1–4) into human-readable advice (e.g., Level 1 "Severe Flood Warning" must trigger advice to "Protect life and evacuate if instructed").

## Testing Strategy

### Automated Tests (Pest / PHPUnit)

- **API Integration**: Mock Environment Agency and National Highways responses; assert correct parsing, geospatial filtering (10km radius), and severity mapping. Verify graceful degradation when APIs return errors or timeouts.
- **Postcode Validation**: Unit tests for valid Somerset Levels postcodes, invalid formats, and out-of-area postcodes; assert the LLM receives correct context and returns the restricted-area message when appropriate.
- **Hallucination Guardrails**: Given fixed API fixtures, assert that the AI response contains only flood warnings present in the mock data; no invented warnings or road closures.
- **Structured Output**: Assert that LLM responses include "Current Status" and "Action Steps" sections in the expected Markdown format.
- **Caching**: Assert that identical postcode queries within 15 minutes hit the cache and do not trigger a new LLM call.
- **Closed Road Correlation**: When mock data includes A361 closure and River Parrett flood, assert the AI correctly correlates and reports both.

### Manual / Exploratory Testing

- **Latency**: Measure Time to First Token under normal conditions; confirm it stays under 3 seconds.
- **UI Responsiveness**: Confirm "Searching real-time records..." appears without blocking the main thread; no frozen UI during API or LLM calls.
- **Actionability**: User testing with Somerset Levels residents; measure whether 90% can identify their local flood risk within 2 prompts.

### Error Rate Monitoring

- **Reliability**: Log and track generic "Server Error" responses; target < 1% of queries.

## Tech Stack

- **Laravel 12.x** – PHP 8.2+
- **Laravel Sail** – Docker development environment (includes Redis)
- **Livewire 4** – Real-time UI
- **Laravel Boost** – AI-assisted development (MCP, guidelines)
- **LLM integration** – openai-php/laravel for OpenAI tool calling
- **Redis** – Caching for flood/road data (15-min TTL)
- **TDD** – PHPUnit + Pest, fully test-driven

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

## Build Pipeline

GitHub Actions runs a test pipeline on push/PR. The pipeline:

- Triggers on push and pull requests to the default branch
- Builds the frontend (e.g. `yarn install && yarn build`) and fails if the build fails
- Runs the full Pest test suite
- Fails the build if any step fails

Workflow file: `.github/workflows/tests.yml`

## Data Sources

- **Environment Agency** – Flood monitoring API (River Parrett, River Tone, Somerset)
- **National Highways v2.0** – Road and lane closures via DATEX II (A361, A372, M5 J23–J25)

Data use requires attribution. See [agents.md](agents.md) for AI assistant context.

## Configuration

- **Default coordinates**: Langport (51.0358, -2.8318) in `config/flood-watch.php`
- **OpenAI API key**: Add `OPENAI_API_KEY` to `.env`
- **National Highways API key**: Register at the [National Highways Developer Portal](https://developer.data.nationalhighways.co.uk/) and add `NATIONAL_HIGHWAYS_API_KEY` to `.env`
- **Redis**: Use `REDIS_HOST=redis` with Sail; `flood-watch-array` cache store for tests

## License

Approved for non-commercial use.
