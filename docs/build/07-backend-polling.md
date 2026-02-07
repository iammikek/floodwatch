# Build: Backend Polling

Scheduled job fetches from APIs and stores in cache. Required for geographic caching; reduces on-demand API load.

**Prerequisite**: [00-foundation.md](00-foundation.md) – `warm_cache_locations` config exists.

**Ref**: `docs/PLAN.md`, `docs/BRIEF.md` §5.1

---

## Acceptance Criteria

- [ ] `flood-watch:warm-cache` (or equivalent job) scheduled every 15 min in `routes/console.php`
- [ ] Scheduled job warms cache for all regions in `warm_cache_locations`
- [ ] Cache TTL > 0 required for warming to take effect; documented
- [ ] `php artisan schedule:list` shows the job
- [ ] Manual run: `sail artisan flood-watch:warm-cache` succeeds
- [ ] Feature/integration test: job runs without error (APIs mocked)
- [ ] `sail test` passes

---

## Schedule

Add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::job(new FetchFloodWatchData)->everyFifteenMinutes();
```

Or `Schedule::call([...])->everyFifteenMinutes()`.

---

## Job

```bash
sail artisan make:job FetchFloodWatchData
```

**Logic**:

1. Get locations from `config('flood-watch.warm_cache_locations')` (e.g. Langport, Bristol, Exeter, Truro)
2. For each: resolve via `LocationResolver`, call `FloodWatchService::chat()` with that location
3. Result is cached by existing cache layer (when TTL > 0)
4. Optionally: fetch raw API data (floods, incidents) and store in Redis/DB for geographic cache keys – depends on chosen cache architecture

---

## Warm Cache Integration

Existing `flood-watch:warm-cache` already does similar. Options:

- **A**: Extend warm-cache to run on schedule (Schedule::command)
- **B**: Create `FetchFloodWatchData` job that calls warm-cache logic internally
- **C**: Job fetches APIs directly and populates a different cache store (geo-keyed)

**Recommendation**: A – schedule `flood-watch:warm-cache` every 15 min. Ensure cache TTL is set (e.g. 15 min) so warmed data is used.

---

## Config

- `FLOOD_WATCH_CACHE_TTL_MINUTES=15` – must be > 0 for caching to work
- `flood-watch.warm_cache_locations` – add to config if not present (see PLAN Cache Warming)

---

## Scheduler

Railway/cron must run `php artisan schedule:run` every minute. Document in `docs/DEPLOYMENT.md`.

---

## Tests

- Job runs without error (mock external APIs)
- Cache populated after job (integration test with flood-watch-array store)
