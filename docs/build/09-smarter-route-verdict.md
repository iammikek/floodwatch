# Build: Smarter Route Verdict (Future Enhancement)

Extend `RouteCheckService` verdict logic with predictive/hydrological context: rivers on route, wet area detection (Somerset Levels), Muchelney rule.

**Ref**: `docs/build/04-route-check.md` (Future Enhancements), `config/flood-watch.correlation`, `RiskCorrelationService`

---

## Goals

1. **Rivers on route**: Identify rivers the route crosses or runs near; check levels via `RiverLevelService`; flag "at risk" if elevated/rising even without active flood warning.
2. **Wet area detection (Somerset Levels)**: Route segments in known flood-prone areas; Parrett/Tone levels trending up; Muchelney rule.

---

## Current State

| Component | What exists |
|-----------|-------------|
| `RouteCheckService` | Verdict from floods (bbox overlap) + incidents (proximity). No river levels, no predictive rules. |
| `RiverLevelService` | `getLevels(lat, lng, radiusKm)` → stations with `levelStatus` (elevated/expected/low), `river`, `lat`/`lng`. |
| `RiskCorrelationService` | `correlate(floods, incidents, riverLevels, region)` → cross-references, predictive warnings (Parrett elevated → Muchelney). |
| `config.flood-watch.correlation` | `flood_area_road_pairs`, `predictive_rules` (river_pattern, flood_pattern), `key_routes`. |

---

## Phase A: Rivers on Route

### A.1 Identify rivers the route crosses or runs near

**Options**:

| Option | Approach | Effort | Accuracy |
|--------|----------|--------|----------|
| **A** | EA stations near route – fetch `RiverLevelService::getLevels(midLat, midLng, radiusKm)`; stations in route bbox = "on route". | Low | Medium (stations ≠ rivers; some rivers have no stations) |
| **B** | Config: route segments → rivers (e.g. "A361 Langport–Muchelney" → River Parrett). | Medium | High for known routes |
| **C** | OSM/water features – Overpass API for rivers/lines near route. | High | High (complete) |

**Recommendation**: Start with **A** – EA stations near route. Reuse existing `RiverLevelService`. Stations include `river` name; we can treat "station near route" as "river on route" for MVP.

**Implementation**:
- In `RouteCheckService::fetchAndAnalyzeRoute()`, after fetching floods/incidents:
  - Call `RiverLevelService::getLevels($midLat, $midLng, $radiusKm)` (reuse `flood_radius_km` or separate config).
  - Filter stations: keep those within `incident_proximity_km` of route line (point-to-line distance, same as incidents).
- Pass `riversOnRoute` (or `riverLevelsOnRoute`) to verdict logic.

### A.2 Check levels and flag "at risk"

**Logic**:
- If any station on route has `levelStatus === 'elevated'` → upgrade verdict to `at_risk` if currently `clear` (or add to summary).
- Optional: "rising" = compare latest reading to previous (would need historical data or trend API – defer).

**Verdict priority**: Blocked > At risk (floods) > At risk (elevated rivers) > Delays > Clear.

**Implementation**:
- New helper: `hasElevatedRiversOnRoute(array $riverLevelsOnRoute): bool`.
- In `computeVerdict()`: if `empty($floodsOnRoute)` and `empty($incidentsOnRoute)` but `hasElevatedRiversOnRoute()` → return `at_risk`.
- If floods or incidents already trigger at_risk/blocked, add elevated rivers to summary text.

### A.3 Config

```php
'route_check' => [
    // ... existing
    'river_proximity_km' => (float) env('FLOOD_WATCH_ROUTE_RIVER_PROXIMITY_KM', 0.5), // same as incidents
    'elevated_river_upgrades_verdict' => (bool) env('FLOOD_WATCH_ROUTE_ELEVATED_RIVER_UPGRADE', true),
],
```

---

## Phase B: Wet Area Detection (Somerset Levels)

### B.1 Route segments within known flood-prone polygons

**Data**: Need polygons for North Moor, King's Sedgemoor, Parrett/Tone catchments.

**Options**:
- **EA flood areas**: Active flood warnings include polygons. Inactive = no polygon. Gap: we need "flood-prone" areas even when no current warning.
- **Config polygons**: Add GeoJSON polygons to config for North Moor, King's Sedgemoor, etc. Manual curation.
- **EA "flood areas" API**: `/id/floodAreas` returns areas; some have geometry. Check if we can get polygons for areas without active warnings.

**Recommendation**: Config polygons for Somerset Levels (Phase B.2). Use same bbox/intersection logic as floods.

**Implementation**:
- New config: `flood_watch.wet_areas` or `flood_watch.route_check.flood_prone_polygons`:
  ```php
  'flood_prone_polygons' => [
      'somerset' => [
          ['id' => 'north_moor', 'name' => 'North Moor', 'geometry' => [...], 'region' => 'somerset'],
          ['id' => 'kings_sedgemoor', 'name' => "King's Sedgemoor", ...],
      ],
  ],
  ```
- Helper: `routeIntersectsFloodProneArea(array $routeCoords, string $region): array`.
- If route intersects → add to summary: "Route passes through flood-prone area (North Moor). Monitor conditions."

**Verdict**: Does not auto-upgrade to `at_risk` (no active warning). Informational only. Optional: if Parrett/Tone elevated *and* route in flood-prone polygon → `at_risk`.

### B.2 River Parrett / Tone levels trending up

**Data**: `RiverLevelService` returns latest value. "Trending up" requires historical readings.

**Options**:
- EA API: readings endpoint can return multiple; compare latest vs previous.
- Defer: "trending" is Phase C. For MVP, "elevated" is sufficient (Phase A).

**Recommendation**: Defer to later phase. Document as future work.

### B.3 Muchelney rule

**Rule**: When River Parrett is elevated → warn about Muchelney access even if NH API shows no closure.

**Current**: `RiskCorrelationService` implements this for main search (LLM gets predictive warnings). `RouteCheckService` does not call it.

**Implementation**:
- Detect if route passes through Muchelney (config: Muchelney bbox or point + proximity).
- If route includes Muchelney (or road to Muchelney, e.g. from Langport):
  - Fetch river levels for Parrett (via `RiverLevelService` or specific station).
  - If Parrett elevated → add predictive warning to summary; optionally upgrade to `at_risk`.
- Alternative: call `RiskCorrelationService::correlate(floods, incidents, riverLevels, 'somerset')` and merge `predictiveWarnings` into route check result. Filter to those relevant to route (e.g. Muchelney when route goes there).

**Recommendation**: Reuse `RiskCorrelationService`. When region is Somerset and route bbox includes Muchelney (or Langport–Muchelney corridor), call correlate and append predictive warnings to summary. If Muchelney warning present and route goes there → `at_risk`.

**Config**: Already exists: `correlation.somerset.predictive_rules` (Parrett elevated, Langport flood).

---

## Implementation Order

| Phase | Deliverable | Dependencies | Est. |
|-------|-------------|--------------|------|
| **A.1** | Rivers on route: fetch river levels, filter stations by proximity | None | 2–3 h |
| **A.2** | Elevated river → at_risk verdict | A.1 | 1 h |
| **A.3** | Config, tests | A.2 | 1 h |
| **B.3** | Muchelney rule: call RiskCorrelationService when route in Somerset/Muchelney | A.1 | 2 h |
| **B.1** | Flood-prone polygons: config + intersection check | None | 3–4 h |
| **B.2** | Parrett/Tone trending (defer) | — | — |

**Suggested order**: A.1 → A.2 → A.3 → B.3 → B.1. B.2 deferred.

---

## Service Changes

### RouteCheckService

```php
// New dependencies
public function __construct(
    // ... existing
    protected RiverLevelService $riverLevelService,
    protected RiskCorrelationService $correlationService,
) {}

// In fetchAndAnalyzeRoute():
$riverLevels = $this->riverLevelService->getLevels($midLat, $midLng, $radiusKm);
$riverLevelsOnRoute = $this->filterRiverStationsOnRoute($riverLevels, $routeCoords, $proximityKm);

// Optional: if region is somerset and route near Muchelney
$region = $this->resolveRegionFromRoute($routeCoords);
$assessment = $this->correlationService->correlate($floods, $incidents, $riverLevels, $region);
// Merge assessment->predictiveWarnings into summary when route relevant

$verdict = $this->computeVerdict($floodsOnRoute, $incidentsOnRoute, $riverLevelsOnRoute);
```

### RouteCheckResult

Extend to include:
- `riverLevelsOnRoute`: array of stations with elevated levels on route
- `predictiveWarnings`: array (from RiskCorrelationService when applicable)

---

## Config Additions

```php
'route_check' => [
    // ... existing
    'river_proximity_km' => 0.5,
    'elevated_river_upgrades_verdict' => true,
    'muchelney_bbox' => [51.02, -2.85, 51.04, -2.80], // optional: for route relevance check
],

'flood_prone_polygons' => [ // optional, Phase B.1
    'somerset' => [
        // GeoJSON or bbox for North Moor, King's Sedgemoor
    ],
],
```

---

## Tests

- Unit: `filterRiverStationsOnRoute` – stations near route line.
- Unit: `computeVerdict` with elevated river → at_risk.
- Feature: Route Langport–Muchelney, Parrett elevated → Muchelney warning in summary.
- Feature: Route clear of floods/incidents but river elevated → at_risk.

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| EA stations sparse; some rivers unmonitored | Document limitation; config override for known routes (Phase B, option B). |
| Polygons for flood-prone areas hard to source | Start with bbox; refine with EA/OSM data later. |
| Muchelney rule false positives | Only apply when route actually passes through Muchelney/Langport corridor. |
| Extra API calls (RiverLevelService) | Cache river levels; same TTL as route cache. |

---

## Out of Scope (Defer)

- OSM/Overpass for river lines (Option C).
- "Trending up" detection (historical readings).
- Non-Somerset wet areas (would need equivalent config per region).
