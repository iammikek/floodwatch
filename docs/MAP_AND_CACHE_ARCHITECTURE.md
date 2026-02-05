# Map and Cache Architecture

**Created**: 2026-02-05  
**Related**: [PLAN_SPLIT_DATA_FASTER_MAP.md](./PLAN_SPLIT_DATA_FASTER_MAP.md), [SITUATIONAL_AWARENESS_DASHBOARD.md](./SITUATIONAL_AWARENESS_DASHBOARD.md)

## Overview

Architectural decisions for map display performance, trend storage, and geographical caching. Goals: fast display, bounded rendering, and cache reuse across users in the same area.

---

## 1. Trend Data Storage

**Current state**: `FloodWatchTrendService` stores search summaries in Redis (sorted set, 30-day retention). Data: location, lat/long, region, flood_count, incident_count, checked_at.

**Proposal**: Persist trend data to the database for:

- **Sparklines**: Hydrological Activity in the Situational Awareness dashboard needs historical river-level or flood-count data over 7+ days.
- **Analytics**: Longer retention, aggregation by region, trend analysis.
- **Resilience**: Redis is ephemeral in some deployments; database survives restarts.

**Implementation**:

- Add `trend_records` (or similar) table: `location`, `lat`, `long`, `region`, `flood_count`, `incident_count`, `river_level_snapshot` (JSON), `checked_at`.
- `FloodWatchTrendService` writes to both Redis (for fast recent reads) and database (for persistence).
- Or: write to database only; Redis becomes optional for hot-path recent queries.
- Add river-level snapshots to the record so we can build sparklines from stored data.

---

## 2. Grid-Based Map Display

**Principle**: Do not render data that is off-screen. Only fetch and display markers/polygons within the visible map bounds.

**Approach**:

- Divide the map into a **grid** (e.g. 0.1° × 0.1° cells, or zoom-level–dependent tiles).
- When the map viewport changes (pan/zoom), compute which grid cells intersect the visible bounds.
- Fetch or filter data only for those cells.
- Render only markers/polygons in the visible cells.

**Benefits**:

- Fewer DOM nodes when zoomed in (e.g. Somerset only vs. whole South West).
- Less GeoJSON parsing for flood polygons outside the viewport.
- Faster initial paint when the default view is a small area.

**Implementation options**:

| Option | Description |
|--------|-------------|
| **Client-side filtering** | Fetch full dataset; filter by `bounds.contains(lat, long)` before adding to map. Simple; still fetches all data. |
| **Server-side bounds** | API accepts `bounds` (minLat, maxLat, minLng, maxLng); returns only floods/incidents/stations in bounds. Reduces payload. |
| **Grid-based API** | API accepts grid cell IDs; returns data for those cells. Cache keyed by cell. More complex; best cache reuse. |

**Recommendation**: Start with **server-side bounds**. Add optional `?bounds=51.0,-2.9,51.1,-2.8` to map data endpoint. Filter floods (by centroid or bbox), incidents, and river stations before returning. Client sends bounds on load and on `moveend` (with debounce).

---

## 3. Geographical Cache Keys

**Principle**: Users in the same area have similar geographical bounds. Cache map data keyed by bounds so that cache hits are shared across users.

**Cache key design**:

- **Option A – Bounding box**: `flood-watch:map:{round(minLat,2)}:{round(maxLat,2)}:{round(minLng,2)}:{round(maxLng,2)}`
  - Rounded to ~11 km; users within that box share cache.
- **Option B – Grid cells**: `flood-watch:map:cell:{zoom}:{tileX}:{tileY}`
  - Standard tile scheme; aligns with grid-based display.
- **Option C – Named region**: `flood-watch:map:region:{region}` (e.g. `somerset`, `bristol`)
  - Coarser; good when viewport maps to a known region.

**Recommendation**: Use **Option A** (rounded bounds) for the initial implementation. When the client requests map data with bounds, round to 2 decimal places (~1.1 km) to normalise keys. Users in Langport, Muchelney, and nearby will hit the same cache entry.

**Example**:

```
User A (Langport): bounds 51.02,-2.85,51.05,-2.80 → key flood-watch:map:51.02:51.05:-2.85:-2.8
User B (Muchelney): bounds 51.02,-2.84,51.06,-2.79 → same rounded key → cache hit
```

---

## 4. Data Flow (Proposed)

```
Client (map loads or viewport changes)
    │
    ├─ Send bounds (minLat, maxLat, minLng, maxLng)
    │
    ▼
Server: getMapData(bounds)
    │
    ├─ Round bounds for cache key
    ├─ Check cache: flood-watch:map:{key}
    │   └─ Hit → return cached
    │
    ├─ Miss → fetch from APIs (floods, incidents, river levels)
    ├─ Filter results to bounds
    ├─ Store in cache (TTL 15 min)
    └─ Return filtered data
```

---

## 5. Implementation Order

1. **Geographical cache key**: Add bounds rounding and cache key to `getMapData()` (from Split Data plan). No bounds param yet; use default centre + radius for key.
2. **Bounds parameter**: Add optional `bounds` to `getMapData()`; filter floods, incidents, stations by bounds before returning.
3. **Client sends bounds**: Map component sends viewport bounds on init and on `moveend` (debounced). Request map data with bounds.
4. **Trend persistence**: Add `trend_records` migration; extend `FloodWatchTrendService` to write to DB.
5. **Grid-based filtering**: If needed, refine to grid cells for finer cache granularity.

---

## 6. Config / Env

| Key | Purpose |
|-----|---------|
| `FLOOD_WATCH_MAP_CACHE_TTL_MINUTES` | TTL for map data cache (default 15) |
| `FLOOD_WATCH_MAP_BOUNDS_PRECISION` | Decimal places for bounds rounding (default 2) |
| `FLOOD_WATCH_TRENDS_PERSIST` | If true, also write trends to database |
