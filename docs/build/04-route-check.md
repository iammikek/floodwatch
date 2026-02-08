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

## Routing Engine

**OSRM** (free, self-hosted or public demo): `GET https://router.project-osrm.org/route/v1/driving/{lon1},{lat1};{lon2},{lat2}?overview=full&geometries=geojson`

Returns route geometry (LineString). Use to check if route intersects flood polygons or incident locations.

**Alternative**: Mapbox Directions API (requires key), Valhalla – document choice in implementation.

---

## Flow

1. User enters From (postcode/place) and To (postcode/place)
2. Geocode both via `LocationResolver::resolve()`
3. Call OSRM for route geometry
4. Fetch flood polygons and incidents (reuse existing services)
5. Check if route intersects any flood polygon or incident
6. Produce verdict: **Clear** | **Blocked** | **At risk** | **Delays**
7. LLM or deterministic logic for summary + alternatives

---

## Implementation

**Service**: `App\Services\RouteCheckService`

- `check(string $from, string $to): RouteCheckResult`
- `RouteCheckResult`: `verdict`, `summary`, `alternatives?`, `intersections` (floods/incidents on route)

**UI**: Section in dashboard (see wireframes) – two inputs, button "Check route", result panel.

**Deterministic vs LLM**: Option A – pure logic (faster, cheaper). Option B – pass route + floods + incidents to LLM for narrative summary. Plan suggests both: logic for verdict, LLM for prose if desired.

---

## Wireframe Placement (incremental UI)

Place in revised wireframe position so the section appears as you build:

- **Desktop**: Route Check in its own card/block – side by side with Risk (or above map). See `public/wireframes/revised-brief.html` desktop grid.
- **Mobile**: Route Check section below Action Steps (or below main content). Blue-tinted block (`bg-blue-50 border-blue-200`).
- **Structure**: Heading "Route Check", From input (default: current location), To input, [Check route] button, result panel below.
- **When no search yet**: From pre-filled with "Langport" or current location; To empty. Result panel hidden until check runs.

---

## Geometry

- Flood polygons: from `EnvironmentAgencyFloodService::getPolygonsForAreaIds()` or similar
- Incidents: `NationalHighwaysService` – geometry in DATEX II or use `incident_road_coordinates` fallback
- Route: OSRM returns GeoJSON LineString
- Intersection: Use turf.php or similar for `lineIntersect` / point-in-polygon. Or simplify: check if route bbox overlaps flood bbox and approximate.

---

## Fallback

If OSRM fails or route not found: "Unable to compute route. Check that both locations are in the South West."

---

## Tests

- Mock OSRM response; mock flood/incident data; assert verdict
- From/To outside area → error
