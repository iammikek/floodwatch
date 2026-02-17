# Flood Watch – Performance & Rendering Optimisation Plan

This document outlines optimisations to improve performance and speed up rendering, based on a review of the codebase. Items are ordered by impact and effort.

---

## 1. Enable and tune main cache (high impact, low effort)

**Current**: `config('flood-watch.cache_ttl_minutes')` defaults to **0** (caching disabled). Every search runs the full LLM + tool chain and external APIs.

**Change**:
- Set `FLOOD_WATCH_CACHE_TTL_MINUTES=15` (or 10–20) in production so repeat searches for the same location return cached results.
- Ensure `FLOOD_WATCH_CACHE_STORE` points to Redis in production when available (faster than database store).

**Impact**: Repeat visits and same-location searches avoid LLM + EA + National Highways calls; response time drops from seconds to milliseconds for cache hits.

**Reference**: `FloodWatchService::chat()` already implements cache get/put; only config change required. See `docs/architecture.md` Caches & TTLs.

---

## 2. Reduce Livewire round-trips for location input (high impact, low effort) ✅ Done

**Before**: `wire:model="location"` in `resources/views/components/flood-watch/search/location-search.blade.php` syncs on every keystroke. Typing "Langport" can trigger many server round-trips and re-renders (including `getBookmarksProperty` / `getRecentSearchesProperty`).

**Change** (implemented):
- Use `wire:model.defer="location"` so the location is synced only when the next Livewire request runs (e.g. "Check status" click); no round-trips while typing.

**Impact**: Fewer Livewire requests and less server/DB work per page load while typing; faster perceived responsiveness.

**Files**: `resources/views/components/flood-watch/search/location-search.blade.php` (line 60).

---

## 3. Cache river levels for map API (high impact, medium effort) ✅ Done

**Before**: `GET /flood-watch/river-levels?lat=&lng=&radius=` is invoked by the map on load and on every `moveend` (debounced 400 ms). `FloodWatchRiverLevelsController` calls `RiverLevelService::getLevels()` with **no caching**; each request hits the Environment Agency API.

**Change**:
- In `RiverLevelService::getLevels()`, cache results by a key derived from rounded lat/lng and radius (e.g. `flood-watch:river-levels:{round(lat,2)}:{round(lng,2)}:{radius}`) with TTL 15 minutes (reuse `flood-watch.cache_ttl_minutes` or a dedicated `river_levels_cache_minutes`).
- Use the same cache store as the rest of the app (`config('flood-watch.cache_store')`).

**Impact**: Pan/zoom and repeat views of the same area no longer hammer the EA API; map feels snappier and stays within rate limits.

**Files**: `app/Flood/Services/RiverLevelService.php`, `config/flood-watch.php` (`river_levels_cache_minutes`, env: `FLOOD_WATCH_RIVER_LEVELS_CACHE_MINUTES`). Test coverage: `RiverLevelServiceTest` (cache hit, cache store on miss, no cache when TTL 0), `FloodWatchRiverLevelsControllerTest` (second request served from cache).

---

## 4. Cache geocoding (LocationResolver / PostcodeValidator) (medium impact, medium effort) ✅ Done

**Before**:
- `PostcodeValidator::geocode()` called postcodes.io on every postcode lookup with no cache.
- `LocationResolver::geocodePlaceName()` called Nominatim on every place-name lookup with no cache.

**Change** (implemented):
- **Postcodes**: Cache by normalised postcode. Key `{prefix}:geocode:postcode:{normalized}`. TTL configurable (`geocode_postcode_cache_minutes`, default 10080 = 7 days). Only successful lat/lng results cached.
- **Place names**: Cache by normalised (lowercase trim) place name. Key `{prefix}:geocode:place:{md5(normalized)}`. TTL configurable (`geocode_place_cache_minutes`, default 1440 = 24 hours). Full result array cached. Set TTL to 0 to disable either cache.

**Impact**: Repeated lookups for the same postcode or place (e.g. "Langport", "Bristol") avoid external HTTP calls; faster search validation and less risk of hitting Nominatim’s 1 req/s guideline.

**Files**: `app/Services/PostcodeValidator.php`, `app/Services/LocationResolver.php`, `config/flood-watch.php` (`geocode_postcode_cache_minutes`, `geocode_place_cache_minutes`; env: `FLOOD_WATCH_GEOCODE_POSTCODE_CACHE_MINUTES`, `FLOOD_WATCH_GEOCODE_PLACE_CACHE_MINUTES`). Test coverage: `PostcodeValidatorTest` (cache hit, cache store on miss, no cache when TTL 0), `LocationResolverTest` (place name cache hit on second call, no cache when TTL 0).

---

## 5. Avoid repeated DB queries for bookmarks and recent searches (medium impact, low effort)

**Current**: `FloodWatchDashboard::getBookmarksProperty()` and `getRecentSearchesProperty()` are computed properties used in the layout. They run on **every** Livewire request that renders the dashboard (initial load, after search, after any wire:model update if not deferred, etc.). Each triggers DB queries (bookmarks for auth users, recent searches for all).

**Change**:
- **Option A**: Cache per request: in the component, store the result of the first call in a private property and return it for the same request (e.g. `$this->cachedBookmarks ??= ...`). Livewire rehydrates the component each round-trip, so this only avoids duplicate access within a single request (e.g. if the view referenced bookmarks multiple times).
- **Option B (preferred)**: Load bookmarks and recent searches only when the "change location" / search UI is shown. E.g. pass a flag from the view so the dropdown/sheet that shows bookmarks and recent searches is rendered only when opened; then call a Livewire method to load and set `$this->bookmarks` / `$this->recentSearches` on first open. That way the initial full-page render does not need to run these queries.
- **Option C**: Keep current behaviour but ensure `wire:model.blur` / `.defer` (see §2) so that typing in the location field does not trigger a round-trip; then these queries only run on initial load and on search/button actions.

**Impact**: Fewer DB hits per user action; faster response for actions that don’t need fresh bookmarks/recent list (e.g. setMapBounds, or when location is synced less often).

**Files**: `app/Livewire/FloodWatchDashboard.php`, `resources/views/components/flood-watch/search/location-header.blade.php` / location-search component and any view that passes `$this->bookmarks` / `$this->recentSearches`.

---

## 6. Polygons controller: batch cache get (low impact, low effort)

**Current**: `FloodWatchPolygonsController` loops over up to 20 IDs and calls `$cache->get()` once per ID.

**Change**: If the cache driver supports multiple get (e.g. Redis `mget`), use a single batch get. Laravel’s Redis store supports `Cache::store($store)->many($keys)`. Build the key array and then `many()` to reduce round-trips.

**Impact**: One Redis/database round-trip instead of N for polygon fetch; small latency win when many polygons are requested.

**Files**: `app/Http/Controllers/FloodWatchPolygonsController.php`.

---

## 7. Concurrency driver for production (medium impact, config-only)

**Current**: Pre-fetch in `FloodWatchService::chat()` uses `Concurrency::run()` for forecast, weather, and river levels. Default driver is `sync` (in-process). Docs note that `process` driver can give better parallelism under load.

**Change**: In production, set `CONCURRENCY_DRIVER=process` (or the correct env key per your Laravel version) so the three prefetches run in separate processes. Keep `sync` in tests (as in phpunit.xml) for predictability.

**Impact**: First search (cold) may see lower latency when three HTTP calls run in parallel in separate processes.

**Reference**: `docs/architecture.md` – Scaling Notes.

---

## 8. Route check: optional second OSRM call (low–medium impact, config-only)

**Current**: When verdict is "blocked", the app fetches alternative routes via a second OSRM request (`fetchAlternativesWhenBlocked`). This doubles OSRM usage for blocked routes and adds latency.

**Change**: Make this config-driven (it already is: `flood-watch.route_check.fetch_alternatives_when_blocked`). Consider setting to `false` if OSRM rate limit (e.g. 1 req/s on demo server) is a concern, or when self-hosting with limited capacity. Document the trade-off (no alternative routes vs. lower load).

**Impact**: Fewer OSRM calls and faster response when route is blocked, at the cost of not showing alternatives.

**Files**: `config/flood-watch.php`, `app/Services/RouteCheckService.php` (already respects config).

---

## 9. Map: increase debounce for river-levels and setMapBounds (low impact, low effort)

**Current**: Map triggers river-levels fetch on `moveend` with 400 ms debounce, and `setMapBounds` with 1200 ms debounce. Rapid panning can still trigger many requests before debounce settles.

**Change**: Consider increasing river-levels debounce to 600–800 ms so quick pans don’t trigger as many `/flood-watch/river-levels` calls. After implementing §3 (river levels cache), this is less critical but still reduces server load.

**Files**: `resources/views/layouts/flood-watch.blade.php` (Alpine `floodMap` – `setTimeout(..., 400)` and `setTimeout(..., 1200)`).

---

## 10. Frontend and asset loading (low impact)

**Current**: Single Vite entry for CSS/JS; Leaflet loaded (e.g. dynamically) for the map.

**Checks**:
- Ensure `npm run build` is used in production so assets are minified and cached.
- If Leaflet is large, confirm it’s loaded only when the map is in view (lazy load) rather than on initial page load; the codebase already uses a dynamic load path (`window.__loadLeaflet`), which is good.
- Ensure `@vite` and layout don’t block first paint (e.g. critical CSS inlined if needed).

**Impact**: Slightly faster first load and fewer duplicate script runs; most gain is from backend and Livewire changes above.

---

## Summary table

| # | Optimisation                         | Impact  | Effort  | When to do        | Status   |
|---|--------------------------------------|---------|---------|-------------------|----------|
| 1 | Enable cache TTL                     | High    | Low     | Immediately       | Pending  |
| 2 | wire:model.defer for location        | High    | Low     | Immediately       | **Done** |
| 3 | Cache river levels (map API)         | High    | Medium  | Soon              | **Done** |
| 4 | Cache geocoding                      | Medium  | Medium  | Soon              | **Done** |
| 5 | Reduce bookmarks/recent DB queries   | Medium  | Low–Med | Soon              | Pending  |
| 6 | Polygons batch cache get             | Low     | Low     | When touching API | Pending  |
| 7 | Concurrency driver process           | Medium  | Config  | Production        | Pending  |
| 8 | Route check alternatives config     | Low–Med | Config  | If OSRM limited   | Pending  |
| 9 | Map debounce tuning                  | Low     | Low     | Optional          | Pending  |
|10 | Frontend/assets                     | Low     | Low     | Optional          | Pending  |

---

## Verification

- After §1: Run two identical searches; second should be fast (check logs for "FloodWatch cache hit" when `cache_ttl_minutes` > 0).
- After §2: Type in location field; network tab should show no Livewire requests until next server action (e.g. "Check status" click).
- After §3: Pan map; repeat pan to same area within TTL should not trigger new EA river-level requests (check cache keys or logs if added).
- After §4: Resolve same postcode/place twice; second resolve should not call postcodes.io/Nominatim (cache hit).
- After §5: Use browser devtools to count Livewire requests; opening "change location" without needing bookmarks/recent list should not run those queries on every other action.

---

## Related docs

- `docs/architecture.md` – Caches, Concurrency, scaling
- `docs/performance.md` – OSRM, LLM/cache, Nominatim
- `docs/plan.md` – Geographic cache keys, cache warming
