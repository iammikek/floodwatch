# Agents and LLM

Single reference for how Flood Watch uses the LLM: tools, APIs, outputs, limitations, and fallbacks. For correlation rules see [Risk Correlation](RISK_CORRELATION.md). For architecture see [architecture](architecture.md).

---

## Overview

The LLM **orchestrates** tool calls and **synthesises** a short narrative (Current Status + Action Steps). It does not decide which floods or roads exist; that comes from tool results. Structured data (floods, incidents, forecast, weather, river levels) are **always** taken from tool outputs, not from parsing LLM text.

- **Model**: `config('openai.model')` — default `gpt-4o-mini`.
- **Orchestration**: `FloodWatchService::chat()` in `app/Services/FloodWatchService.php`.
- **Prompts**: `resources/prompts/{version}/system.txt`. Version from `config('flood-watch.prompt_version')` (env: `FLOOD_WATCH_PROMPT_VERSION`). Region-specific blocks from `config('flood-watch.regions.{region}.prompt'`.

---

## Registered tools

| Tool | Purpose | Backing service / API |
|------|---------|----------------------|
| **GetFloodData** | Flood warnings and alerts by location | Environment Agency flood-monitoring API |
| **GetHighwaysIncidents** | Road and lane closures (M5, A38, A30, etc.) | National Highways v2.0 (DATEX II) |
| **GetFloodForecast** | 5-day flood risk outlook | Flood Guidance Statement (FGS) API |
| **GetRiverLevels** | Real-time river and sea levels | Environment Agency readings API |
| **GetCorrelationSummary** | Deterministic flood↔road correlation | RiskCorrelationService (no external API) |

---

## Per-tool: API calls and expected output

### GetFloodData

- **API**: Environment Agency Real-Time flood-monitoring API.
- **Call**: `GET {base_url}/id/floods?lat={lat}&long={lng}&dist={radius_km}`. Base URL from `config('flood-watch.environment_agency.base_url')` (default `https://environment.data.gov.uk/flood-monitoring`). Also fetches flood areas and polygons for map data; polygons are **not** sent to the LLM.
- **Params**: `lat`, `lng`, `radius_km` (defaults from config: Langport 51.0358, -2.8318, 15 km).
- **Output**: Array of flood objects. Each has `floodAreaID`, `description`, `severity`, `severityLevel`, `message`, `timeRaised`, `timeMessageChanged`, `timeSeverityChanged`, `lat`, `lng`. Optional `polygon` (stripped before sending to LLM).
- **Sent to LLM**: Max `llm_max_floods` (default 12). Flood `message` truncated to `llm_max_flood_message_chars` (default 150). No polygon.

### GetHighwaysIncidents

- **API**: National Highways Road and Lane Closures v2.0. DATEX II v3.4, JSON response.
- **Call**: `GET {base_url}/closures?closureType=planned` and optionally `closureType=unplanned`. Base URL from `config('flood-watch.national_highways.base_url')`. Header `Ocp-Apim-Subscription-Key: {api_key}`. Requires `NATIONAL_HIGHWAYS_API_KEY` in `.env`.
- **Params**: None (uses region from chat context; service merges planned + unplanned and filters by region/proximity).
- **Output**: Array of incidents. Each has `id`, `road`, `status`, `incidentType`, `delayTime`, `startTime`, `endTime`, `locationDescription`, `managementType`, `isFloodRelated`, etc. Filtered by region and proximity to user location before being passed to the LLM.
- **Sent to LLM**: Max `llm_max_incidents` (default 12).

### GetFloodForecast

- **API**: Flood Guidance Statement (FGS) API (Met Office / EA).
- **Call**: `GET {base_url}/...` (config: `flood-watch.flood_forecast.base_url`).
- **Params**: None.
- **Output**: Object with `england_forecast`, `flood_risk_trend`, `sources`. Pre-fetched in parallel with weather and river levels at the start of `chat()`.
- **Sent to LLM**: `england_forecast` truncated to `llm_max_forecast_chars` (1200). Correlation block truncated to `llm_max_correlation_chars` (8000).

### GetRiverLevels

- **API**: Environment Agency monitoring stations (same data as [check-for-flooding.service.gov.uk](https://check-for-flooding.service.gov.uk/river-and-sea-levels)).
- **Call**: Readings endpoint keyed by location/radius (config: EA base URL).
- **Params**: `lat`, `lng`, `radius_km` (defaults as above).
- **Output**: Array of station objects: station name, river, town, level, unit, levelStatus (e.g. elevated, expected, low), reading time. Pre-fetched at start of `chat()`.
- **Sent to LLM**: Max `llm_max_river_levels` (default 8).

### GetCorrelationSummary

- **API**: None. Pure PHP.
- **Call**: `RiskCorrelationService::correlate($floods, $incidents, $riverLevels, $region)`. Uses in-memory data from prior tool calls in the same chat.
- **Params**: None (context from conversation).
- **Output**: `RiskAssessment` with `severeFloods`, `floodWarnings`, `roadIncidents`, `crossReferences`, `predictiveWarnings`, `keyRoutes`. Rendered for the LLM; total size limited by `llm_max_correlation_chars`.
- **See**: [RISK_CORRELATION.md](RISK_CORRELATION.md) for rules and config.

---

## Limitations

| Area | Detail |
|------|--------|
| **Latency** | Full chat = pre-fetch (forecast, weather, river levels) + up to 8 LLM iterations. Each tool call adds a round-trip. Typical: several seconds. |
| **Costs** | Token usage: input (system prompt + user message + tool defs + tool results) and output (summary). Model default gpt-4o-mini. Track via admin dashboard (LlmRequest, optional OpenAI Usage API). Config: `flood-watch.llm_cost_*`, `llm_budget_*`. |
| **Rate limits** | OpenAI account rate limits apply. App: guest users 1 search per 15 min; registered unlimited. |
| **Context** | Model context cap (e.g. 128k). We trim tool results (max items, max chars per tool) via `prepareToolResultForLlm()`. Config: `llm_max_floods`, `llm_max_incidents`, `llm_max_river_levels`, `llm_max_forecast_chars`, `llm_max_flood_message_chars`, `llm_max_correlation_chars`. |

---

## Fallback behaviours

### When an external API fails

- **Environment Agency** (floods, river levels): Circuit breaker after N failures (config: `flood-watch.circuit_breaker`). On timeout or 5xx, service returns `[]`. LLM receives empty array; can say e.g. "Flood data temporarily unavailable."
- **National Highways**: Same pattern. Missing or invalid API key → empty incidents; `/health` reports "skipped."
- **Flood Forecast / Weather**: Timeout or failure → empty or partial; pre-fetch still returns what succeeded. LLM sees whatever was returned.
- **Circuit open**: No external call; tool returns `[]`. Cooldown after `cooldown_seconds` before retrying.

### When the LLM fails

- OpenAI timeout, rate limit, or error → `chat()` returns a result with an error message (e.g. `response` or `error_key`). Dashboard shows a friendly message; no crash. Cached data is not overwritten with the error.

### Display rule

- **Critical data** (flood list, road list, river levels, forecast) is **always** from tool results in the chat result (`floods`, `incidents`, `riverLevels`, `forecast`, `weather`). Do not parse the LLM narrative to extract numbers or IDs for display.

---

## Example request and response

**Request** (conceptually):

- User message: "Check flood status for Bristol"
- Coordinates: lat 51.4545, lng -2.5879, region `bristol`
- System prompt: base (`resources/prompts/v1/system.txt`) + region block for Bristol

**Response shape** (from `FloodWatchService::chat()`):

```php
[
    'response' => '...',           // LLM narrative: Current Status + Action Steps
    'floods' => [...],             // From GetFloodData (full, for UI/map)
    'incidents' => [...],         // From GetHighwaysIncidents
    'forecast' => [...],          // From GetFloodForecast (pre-fetched)
    'weather' => [...],           // Pre-fetched
    'riverLevels' => [...],       // From GetRiverLevels (pre-fetched)
    'lastChecked' => '...',       // ISO 8601
]
```

**Example tool call (GetFloodData)**:

- Input (LLM passes): `{ "lat": 51.4545, "lng": -2.5879, "radius_km": 15 }`
- Output (trimmed for LLM): array of up to 12 floods; each `message` truncated to 150 chars; no `polygon` key.

---

## Data flow (summary)

1. User submits location → `LocationResolver` → coordinates + region.
2. `chat()` pre-fetches forecast, weather, river levels in parallel.
3. LLM receives system prompt + user message + tool definitions. It may call GetFloodData, GetHighwaysIncidents, GetFloodForecast, GetRiverLevels, GetCorrelationSummary in any order (typically floods + incidents first, then GetCorrelationSummary).
4. Each tool call is executed; results are trimmed and appended to the conversation. LLM continues until it returns a final assistant message.
5. Result (narrative + `floods`, `incidents`, etc.) is cached (if enabled) and returned. Dashboard renders from these fields; the narrative is shown as the AI summary.

**Pre-fetch vs on-demand**: Forecast, weather, and river levels are pre-fetched so the final result always includes them. GetFloodData and GetHighwaysIncidents run only when the LLM calls those tools. When the LLM does call GetFloodForecast or GetRiverLevels, the tools perform fresh API calls (pre-fetched values are used for the returned result, not for the tool response payload to the LLM).
