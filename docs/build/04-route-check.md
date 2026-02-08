# Build: Route Check (From/To)

Geocode From and To; compute route; overlay incidents/floods; produce summary: Clear / Blocked / At risk / Delays.

**Ref**: `docs/BRIEF.md` §4, `docs/ACCEPTANCE_CRITERIA.md` §3

---

## Acceptance Criteria

- [ ] Route check section visible in dashboard with From and To inputs
- [ ] Both locations geocoded via LocationResolver; must be in South West
- [ ] Route fetched from OSRM (or chosen engine); geometry obtained
- [ ] Verdict returned: Clear / Blocked / At risk / Delays
- [ ] Summary includes which floods/incidents affect route; alternatives when blocked
- [ ] Outside-area From/To shows error
- [ ] Feature test: valid From/To returns verdict; invalid shows error
- [ ] `sail test` passes

---

## Questions (answer before or during implementation)

1. ~~**OSRM production**~~ **Decided**: Use public demo `router.project-osrm.org` for production. Self-host later if needed; see `docs/PERFORMANCE.md`.

2. ~~**From default**~~ **Decided**: Pre-fill From from default bookmark when logged in. Otherwise, From is empty and we prompt for location with a "Use my location" button (reuse GPS flow from main search).

3. ~~**Alternatives when blocked**~~ **Decided**: Show 2 alternative routes when the primary route is blocked. Use `alternatives=2` in OSRM request.

---

## Routing Engine

**OSRM** (free, self-hosted or public demo): `GET https://router.project-osrm.org/route/v1/driving/{lon1},{lat1};{lon2},{lat2}?overview=full&geometries=geojson&alternatives=2&steps=true`

- `overview=full`, `geometries=geojson` – route geometry (LineString) for map and intersection checks.
- `alternatives=2` – up to 2 alternative routes when blocked.
- `steps=true` – road names for alternative summaries (e.g. "M5 J25 → A358 → Taunton").

**Alternative**: Mapbox Directions API (requires key), Valhalla – document choice in implementation.

---

## Flow

1. From: default bookmark (if logged in) or user enters postcode/place or taps "Use my location".
2. To: user enters postcode/place.
3. Geocode both via `LocationResolver::resolve()`
4. Call OSRM for route geometry
5. Fetch floods and incidents for route corridor (see Geometry)
6. Check if route intersects any flood polygon or is near any incident
7. Produce verdict: **Clear** | **Blocked** | **At risk** | **Delays**
8. Deterministic summary (MVP); optional LLM prose later

---

## Implementation

**Service**: `App\Services\RouteCheckService`

- `check(string $from, string $to): RouteCheckResult`
- `RouteCheckResult`: `verdict`, `summary`, `alternatives?`, `intersections` (floods/incidents on route), `routeGeometry?` (GeoJSON LineString for map on desktop)

**UI**: Section in dashboard (see wireframes) – two inputs, button "Check route", result panel. Always visible.

**Route presentation** (per wireframes):
- **Mobile**: Text only – verdict badge, summary, list of affected floods/incidents, alternatives. No map (map omitted on mobile).
- **Desktop**: Map with route polyline drawn on existing Leaflet map; floods and incidents overlaid. Plus text summary in Route Check block.

**Summary**: Deterministic for MVP (faster, cheaper). Option to add LLM narrative later.

---

## Rate Limiting & Cache

- **Rate limit**: Same as main search. Guests: 1 route check per 15 min; registered: unlimited.
- **Cache**: Cache route results for same From/To coordinates. TTL: 15 min (configurable). Reduces OSRM calls.
- **Alternatives**: Do not re-check alternatives for floods/incidents in MVP. Show alternatives as text only (road names from OSRM steps).

---

## Map (Desktop)

When route check runs on desktop (`lg` breakpoint or above):

- Fit map to route bbox.
- Draw route polyline on Leaflet map.
- Show floods and incidents that affect the route (reuse data from route check).
- Breakpoint: match dashboard (typically `lg` = 1024px for map visibility).

---

## Verdict Logic

| Verdict | Condition | Priority |
|---------|-----------|----------|
| **Blocked** | Road closure incident on or very near route | 1 |
| **At risk** | Route intersects flood polygon | 2 |
| **Delays** | Lane closures, delays on route | 3 |
| **Clear** | None of the above | 4 |

When multiple apply, use highest priority.

---

## Geometry

**Flood data**: Use `EnvironmentAgencyFloodService::getFloods(lat, lng, radiusKm)` centred on route midpoint with radius covering the route (e.g. 25 km). Floods include polygons when available.

**Incidents**: `NationalHighwaysService::getIncidents()` – each incident has `lat`/`lng` when available. Use distance-from-route threshold (e.g. 500 m) to treat incident as "on route".

**Route**: OSRM returns GeoJSON LineString. Compute route bbox for overlap checks.

**Intersection** (no turf.php for MVP):
- **Flood**: Route bbox overlaps flood polygon bbox → "at risk". For stricter check, add turf.php later.
- **Incident**: Incident point within `incident_proximity_km` of route line (point-to-line distance) → on route.

---

## Configuration

Add to `config/flood-watch.php`:

```php
'route_check' => [
    'osrm_url' => env('FLOOD_WATCH_OSRM_URL', 'https://router.project-osrm.org'),
    'osrm_timeout' => (int) env('FLOOD_WATCH_OSRM_TIMEOUT', 15),
    'flood_radius_km' => (int) env('FLOOD_WATCH_ROUTE_FLOOD_RADIUS_KM', 25),
    'incident_proximity_km' => (float) env('FLOOD_WATCH_ROUTE_INCIDENT_PROXIMITY_KM', 0.5),
    'cache_ttl_minutes' => (int) env('FLOOD_WATCH_ROUTE_CACHE_TTL_MINUTES', 15),
],
```

---

## Wireframe Placement (incremental UI)

Place in revised wireframe position so the section appears as you build:

- **Desktop**: Route Check in its own card/block – side by side with Risk (or above map). See `public/wireframes/revised-brief.html` desktop grid.
- **Mobile**: Route Check section below Action Steps (or below main content). Blue-tinted block (`bg-blue-50 border-blue-200`).
- **Structure**: Heading "Route Check", From input, To input, [Check route] button, result panel below.
- **From**: Pre-fill from default bookmark when logged in. Otherwise empty with "Use my location" button next to From input.
- **To**: Empty until user enters.
- **Result panel**: Hidden until check runs.
- **Mobile** (< `lg`): Text verdict + summary + alternatives (no map).
- **Desktop** (`lg`+): Text in Route Check block + route polyline on map (floods/incidents overlaid). See `docs/archive/WIREFRAME_REVISED_BRIEF.md`.

---

## Fallback

If OSRM fails or route not found: "Unable to compute route. Check that both locations are in the South West."

---

## Tests

- Mock OSRM response; mock flood/incident data; assert verdict
- From/To outside area → error
