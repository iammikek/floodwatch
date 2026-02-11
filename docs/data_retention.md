# Data Retention

Overview of data that grows over time, retention policies, and pruning. See `docs/schema.md` for table definitions.

---

## Implemented

| Data | Storage | Retention | Pruning |
|------|---------|-----------|---------|
| **LLM requests** | `llm_requests` table | 90 days | `flood-watch:prune-llm-requests` daily |
| **Flood watch trends** | Redis `flood-watch:trends` | 30 days | `trimOldEntries()` on each write |

**Config**: `config/flood-watch.php`
- `llm_requests_retention_days` (default 90)
- `trends_retention_days` (default 30)

---

## Future Tasks

### user_searches

| Item | Details |
|------|---------|
| **Table** | `user_searches` |
| **Purpose** | Search history; recent searches UI; admin top regions |
| **Growth** | One row per successful search |
| **Recommended retention** | 90 days |
| **Task** | Add `PruneUserSearchesCommand` or scheduled job. Delete rows where `searched_at < now()->subDays(90)`. Register in `bootstrap/app.php` schedule. |
| **Ref** | `docs/build/01-search-history.md` Retention section |

### failed_jobs

| Item | Details |
|------|---------|
| **Table** | `failed_jobs` |
| **Purpose** | Laravel failed queue jobs |
| **Growth** | One row per failed job |
| **Recommended retention** | 7–30 days |
| **Task** | Add `schedule->command('queue:prune-failed')->daily()` if not present. Or use `queue:flush` for manual cleanup. |

### Laravel Pulse

| Item | Details |
|------|---------|
| **Tables** | `pulse_values`, `pulse_entries`, `pulse_aggregates` |
| **Purpose** | Laravel Pulse metrics and monitoring |
| **Growth** | Constant; time-series data |
| **Retention** | Pulse has built-in trimming. Verify `config/pulse.php` retention settings. |
| **Task** | Document Pulse retention config; ensure `pulse:trim` or equivalent runs if needed. |

### Sessions

| Item | Details |
|------|---------|
| **Table** | `sessions` |
| **Purpose** | Laravel session storage |
| **Growth** | One row per active session |
| **Retention** | Laravel `session.lifetime` (default 120 min); inactive sessions expire. |
| **Task** | `session:table` / database driver: sessions are cleaned by `session:gc` or similar. Verify `SESSION_LIFETIME` and driver cleanup. |

---

## Low Priority / Optional

| Data | Notes |
|------|-------|
| **Cache** | TTL-based; entries expire automatically. |
| **Jobs** | `jobs` table: processed jobs removed by queue worker. |
| **Analytics events** (planned) | If `analytics_events` table is added, plan retention (e.g. 30–90 days). |
| **API snapshots** (planned) | If `api_snapshots` for trend charts is added, plan retention. |

---

## Reference

| Doc | Purpose |
|-----|---------|
| `docs/schema.md` | Table definitions, indexes |
| `docs/build/01-search-history.md` | user_searches retention note |
| `config/flood-watch.php` | Retention config keys |
| `bootstrap/app.php` | Scheduled commands |
