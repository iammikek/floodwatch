# Next Steps: Instant Load Dashboard at Langport

**Created**: 2026-02-05  
**Status**: Planning  
**Related**: `docs/PLAN_SPLIT_DATA_FASTER_MAP.md`, `docs/SITUATIONAL_AWARENESS_DASHBOARD.md`

## Goal

Improve first-load UX so the dashboard renders instantly when centering at Langport (default). No blocking on external API calls.

---

## Implementation Order

| Step | Task | Files | Status |
|------|------|-------|--------|
| 1 | Use getMapData in mount; set forecast, weather | `FloodWatchDashboard.php` | Pending |
| 2 | Enable cache (FLOOD_WATCH_CACHE_TTL_MINUTES=15) | `config/flood-watch.php`, `.env.example` | Pending |
| 3 | Pre-warm in FetchLatestInfrastructureData | `FetchLatestInfrastructureData.php` | Pending |
| 4 | Pass server data to status grid | `status-grid.blade.php`, `flood-watch-dashboard.blade.php` | Pending |
| 5 | Pass server data to map | `map-section.blade.php`, `flood-watch.blade.php`, `flood-watch-dashboard.blade.php` | Pending |
| 6 | (Optional) DB snapshot fallback | New migration, model, job update | Deferred |

---

## Step 1: Unify Cache in Mount

**File**: `app/Livewire/FloodWatchDashboard.php`

- Replace `Cache::remember(..., getMapDataUncached)` with `$floodWatchService->getMapData($defaultLat, $defaultLong, null)`
- getMapData returns floods, incidents, riverLevels, forecast, weather, lastChecked
- Set `$this->forecast` and `$this->weather` from mapData (currently missing on first load)
- Remove the separate Cache::remember wrapper; getMapData uses its own cache when TTL > 0

---

## Step 2: Enable Caching

**File**: `config/flood-watch.php`

- Default `cache_ttl_minutes` to 15 (or keep 0 for dev, document in .env.example)

**File**: `.env.example`

- Add `FLOOD_WATCH_CACHE_TTL_MINUTES=15` with comment
- Ensure `FLOOD_WATCH_CACHE_STORE` documented for Redis in production

---

## Step 3: Pre-Warm in Scheduled Job

**File**: `app/Jobs/FetchLatestInfrastructureData.php`

- Before delta comparison, call `$floodWatchService->getMapData($lat, $long, null)` to populate cache
- Keep `getMapDataUncached` for delta comparison (raw fetch, no cache read)
- Job runs every 15 min; cache will be warm for first user after each run

---

## Step 4: Pass Server Data to Status Grid

**File**: `resources/views/components/flood-watch/status-grid.blade.php`

- Accept `floods` and `riverLevels` as props (default `[]`)
- In x-data: initialise `floods` and `riverLevels` from props
- In init(): if `floods.length > 0 || riverLevels.length > 0`, set `loading = false` and skip fetch; else fetch as today

**File**: `resources/views/livewire/flood-watch-dashboard.blade.php`

- Pass `:floods="$floods"` and `:riverLevels="$riverLevels"` to status-grid

---

## Step 5: Pass Server Data to Map

**File**: `resources/views/components/flood-watch/map-section.blade.php`

- Accept `initialFloods`, `initialIncidents`, `initialRiverLevels` props (default `[]`)
- Pass to floodMap Alpine config: `floods: @js($initialFloods ?? [])`, `incidents: @js($initialIncidents ?? [])`, `stations: @js($initialRiverLevels ?? [])`

**File**: `resources/views/layouts/flood-watch.blade.php` (floodMap Alpine)

- In init(), if `this.floods.length > 0 || this.stations.length > 0 || this.incidents.length > 0`, skip `loadMapData()` fetch; use existing data and go straight to `addMarkers`

**File**: `resources/views/livewire/flood-watch-dashboard.blade.php`

- Pass `:initialFloods="$floods"`, `:initialIncidents="$incidents"`, `:initialRiverLevels="$riverLevels"` to map-section
- Use full floods (with polygons) from `$mapData['floods']` for map-section; keep stripped `$this->floods` for results-section and status grid

---

## Step 6 (Deferred): DB Snapshot Fallback

If cold-start latency remains an issue after Redis + pre-warm:

- **Migration**: `dashboard_snapshots` table – `location_key` (string), `data` (JSON), `last_checked_at` (timestamp)
- **Model**: `DashboardSnapshot` – read/write by location_key
- **Flow**: mount() checks cache; on miss, read from DB; use if fresh enough; job writes to both cache and DB

---

## Verification

- [ ] First load (cold cache): run `php artisan cache:clear`, load dashboard – should block 2-5s (expected until pre-warm)
- [ ] After FetchLatestInfrastructureData runs: load dashboard – should be instant (cache hit)
- [ ] Manual pre-warm: `php artisan flood-watch:fetch-infrastructure` then load – instant
- [ ] Status grid shows floods/river levels without client fetch when server data present
- [ ] Map shows markers without client fetch when server data present

---

## Dependencies

- `FloodWatchService::getMapData()` – exists
- `FetchLatestInfrastructureData` – runs every 15 min
- Redis or configured cache store for production
