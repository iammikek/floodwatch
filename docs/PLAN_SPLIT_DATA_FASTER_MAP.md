# Split Data Architecture for Faster Map Loading

**Created**: 2026-02-05  
**Status**: Draft for review

## Overview

Architectural plan to split map data from AI summary so the Leaflet map can render sooner. The core change is pre-fetching floods and incidents at the start (in parallel with forecast/weather/river levels) and emitting that data to the client before the AI completes.

---

## Current Data Flow

```
User clicks "Check status"
    → Livewire calls FloodWatchService.chat()
    → Service fetches forecast, weather, riverLevels (parallel)
    → Service calls OpenAI with tools
    → AI loop: AI requests GetFloodData, GetHighwaysIncidents
    → Service fetches floods, incidents from APIs
    → AI returns final response
    → Service returns full result to Livewire
    → User sees map + summary (10-20 seconds later)
```

**Bottleneck**: Map data (floods, incidents) is only available after the AI finishes its tool loop. River levels are already fetched at the start but not used for the map until the end.

---

## Map Data Requirements

| Data       | Source                        | Params              | Available today      |
|------------|-------------------------------|---------------------|----------------------|
| mapCenter  | user location or default      | lat, long           | At start (validation)|
| riverLevels| RiverLevelService             | lat, long, radius   | At start (line 69-74)|
| floods     | EnvironmentAgencyFloodService  | lat, long, radius   | Via AI tool call only|
| incidents  | NationalHighwaysService       | region filter       | Via AI tool call only|

Floods and incidents use fixed/sensible params: `getFloods(lat, long, 15)` and `getIncidents()` filtered by region. We have lat, long, region from location validation before the AI runs.

---

## Option A: Pre-fetch + Stream Map Data (Single Request)

**Idea**: Fetch floods and incidents at the start (with forecast, weather, river levels). As soon as we have map data, stream it to the client. Continue with AI. Client shows map immediately; summary appears when AI finishes.

### Challenges

- Livewire `stream()` targets HTML content. Streaming JSON/script to update component state is possible but brittle.
- Would require new `wire:stream` target and custom client parsing.
- **Verdict**: Not recommended; Option B is cleaner.

---

## Option B: Two-Phase Requests (Recommended)

**Idea**: Split into two Livewire actions. First fetches and returns map data only. Second runs the AI. The client orchestrates: call phase 1, render map when it returns, then call phase 2 for the summary.

### Flow

1. User clicks "Check status"
2. Livewire calls `fetchMapData()` → FloodWatchService.getMapData()
3. Service fetches floods, incidents, river levels, forecast, weather in parallel (2-5 sec)
4. Livewire re-renders with map data → **map visible**
5. Client auto-triggers `fetchSummary()` (e.g. via dispatched event)
6. Livewire calls `fetchSummary()` → FloodWatchService.chat() with pre-fetched data
7. AI runs with tools returning cached data (no extra API calls)
8. Summary appears (~10-20 sec total, but map was visible at step 4)

### Architecture Changes

#### 1. New Service Method: `FloodWatchService::getMapData()`

**File**: `app/Services/FloodWatchService.php`

- New method `getMapData(float $lat, float $long, ?string $region): array`
- Runs `Concurrency::run()` for: forecast, weather, riverLevels, floods, incidents
- Floods: `$this->floodService->getFloods($lat, $long, 15)` (or config)
- Incidents: `$this->filterIncidentsByRegion($this->highwaysService->getIncidents(), $region)`
- Returns: `['floods' => ..., 'incidents' => ..., 'riverLevels' => ..., 'forecast' => ..., 'weather' => ..., 'lastChecked' => ...]`
- Check cache first (reuse existing cache key pattern or add `map:{location}`)
- No AI call; typically completes in 2-5 seconds

#### 2. Refactor `chat()` to Accept Pre-fetched Data

**File**: `app/Services/FloodWatchService.php`

- Add optional param: `?array $preFetchedData = null` (floods, incidents, riverLevels, forecast, weather)
- When `$preFetchedData` is provided:
  - Skip the initial `Concurrency::run()` for those keys; use pre-fetched values
  - When AI calls `GetFloodData` / `GetHighwaysIncidents` / `GetRiverLevels` / `GetFloodForecast`, return pre-fetched data from `$preFetchedData` instead of calling APIs
- When `$preFetchedData` is null, keep current behavior (backward compatible)

#### 3. New Livewire Methods: `fetchMapData()` and `fetchSummary()`

**File**: `app/Livewire/FloodWatchDashboard.php`

**fetchMapData()**:
- Validate location (same as search)
- Call `FloodWatchService::getMapData($lat, $long, $region)`
- Set `floods`, `incidents`, `riverLevels`, `mapCenter`, `hasUserLocation`, `lastChecked`, `forecast`, `weather`
- Store pre-fetched data for the next call (property or short-lived cache)
- Set `loading = false`
- Do not set `assistantResponse`; show map only

**fetchSummary()**:
- Requires map data already loaded
- Call `FloodWatchService::chat(..., preFetchedMapData: $this->getStoredMapData())`
- Set `assistantResponse`
- Rate limiting: apply to `fetchMapData()` for guests

#### 4. Client Orchestration

**File**: `resources/views/livewire/flood-watch-dashboard.blade.php`

- Single `search()` entry point that calls `fetchMapData()` first
- When `fetchMapData()` returns, component re-renders with map
- Dispatch `map-ready` event; client listener calls `$wire.fetchSummary()`
- Or: `search()` calls `fetchMapData()`, then in the same response we could use `$this->dispatch('map-ready')` and a Blade `@script` / Alpine listener to call `$wire.fetchSummary()` on next tick

#### 5. Caching and Cache Keys

- `getMapData()` uses same cache key as `chat()` for consistency
- Consider `flood-watch:map:{md5(location)}` for map-only cache

#### 6. Error Handling

- If `fetchMapData()` fails, show error; do not call `fetchSummary()`
- If `fetchSummary()` fails, map is already visible; show error for summary only
- Guest rate limit: apply to `fetchMapData()` (first phase)

---

## Option C: Pre-fetch Only, No Two-Phase (Simpler)

**Idea**: Keep single `search()` request. Pre-fetch floods and incidents at start of `chat()`. Do not stream. Feed AI pre-fetched data. Map still appears only when full response returns.

**Verdict**: Easiest to implement but does not materially speed up map rendering. Option B is preferred for UX.

---

## Recommended Path: Option B

| Step | Task |
|------|------|
| 1 | Add `FloodWatchService::getMapData()` – parallel fetch of floods, incidents, river levels, forecast, weather |
| 2 | Add `?array $preFetchedData` to `chat()`, make tools return pre-fetched data when provided |
| 3 | Add `fetchMapData()` and `fetchSummary()` to `FloodWatchDashboard` |
| 4 | Refactor `search()` to call `fetchMapData()` first, then dispatch event to trigger `fetchSummary()` |
| 5 | Add client listener for map-ready → `$wire.fetchSummary()` |
| 6 | Apply guest rate limit to `fetchMapData()` |
| 7 | Reuse cache between `getMapData()` and `chat()` where possible |

### Files to Modify

- `app/Services/FloodWatchService.php` – new `getMapData()`, `chat()` accepts pre-fetched data
- `app/Livewire/FloodWatchDashboard.php` – `fetchMapData()`, `fetchSummary()`, `search()` orchestration
- `resources/views/livewire/flood-watch-dashboard.blade.php` – wire up `fetchSummary` on map-ready
- `resources/views/layouts/flood-watch.blade.php` – possibly emit map-ready from floodMap Alpine when map renders

### Estimated Impact

- Map visible: **~2-5 seconds** after click (vs. **~10-20 seconds** today)
- Summary: same as today (~10-20 seconds)
- Extra request: one additional HTTP round-trip for `fetchSummary()`
