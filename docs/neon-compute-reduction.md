# Neon Compute Reduction Plan

Neon compute (CU-hours) is driven by database activity: queue jobs, session reads/writes, LLM request recording, and any cache/store using the DB. This doc outlines changes to reduce frequency and volume of DB usage.

## Current DB usage drivers

1. **Warm-cache command** (scheduled)
   - Runs every 15 minutes when `flood-watch.warm_cache_locations` is set.
   - For each location (e.g. Langport, Bristol, Exeter, Truro, Dorchester) calls `FloodWatchService::chat()`.
   - Each chat that results in an LLM call dispatches `RecordLlmRequestJob` → writes to `llm_requests` and uses the queue (DB if `QUEUE_CONNECTION=database`).

2. **Queue**
   - Default `QUEUE_CONNECTION=database`: every job (e.g. `FetchNationalHighwaysIncidentsJob`, `ScrapeSomersetCouncilRoadworksJob`, `RecordLlmRequestJob`) touches the DB for dispatch and processing.

3. **Session**
   - If `SESSION_DRIVER=database`, every request reads/writes the `sessions` table.

4. **Other**
   - `flood-watch:prune-llm-requests` runs daily; admin dashboard and user flows that hit Eloquent.

## Changes in this branch

### 1. Configurable warm-cache schedule (default: every 4 hours)

- **Config**: `warm_cache_cron` (default `0 */4 * * *` = every 4 hours).
- **Env**: `FLOOD_WATCH_WARM_CACHE_CRON`.
- **Effect**: Fewer scheduler runs and fewer cold-cache chat() runs, reducing both LLM calls and DB writes from warm-cache.

### 2. Skip LLM request recording for warm-cache

- **`FloodWatchService::chat()`**: New parameter `$recordUsage = true`; when `false`, we do not dispatch `RecordLlmRequestJob`.
- **`FloodWatchWarmCacheCommand`**: Calls `chat(..., recordUsage: false)` so warm-cache runs do not create `llm_requests` rows or queue jobs for analytics.
- **Effect**: Warm-cache no longer contributes to `RecordLlmRequestJob` or `llm_requests` table growth.

### 3. Optional follow-ups (not in this branch)

- **Queue**: Use Redis (or another non-DB driver) for queues to move job dispatch/processing off Neon.
- **Session**: Use `file` or Redis for sessions if DB session usage is significant.
- **Cache**: Keep flood-watch cache on Redis (not DB) so warm-cache and route checks don’t hit Neon for cache I/O.

## Verification

- Warm-cache still runs and populates cache; first-request latency for configured locations remains improved.
- User-initiated chat in the UI still records usage (unchanged).
- Schedule: confirm in app that `flood-watch:warm-cache` runs at the configured cron (e.g. every 4 hours).
- After deployment, monitor Neon compute (CU-hrs) and `llm_requests` growth to confirm reduction.
