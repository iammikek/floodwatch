# Flood Watch – Cursor Build Specifications

Implementation specs for Cursor agentic development. Build in order; each spec is self-contained.

**Ref**: `docs/plan.md`, `docs/brief.md`, `docs/schema.md`, `agents.md`

---

## Build Order

### Foundation (Do First)

| # | Spec | Est. | Purpose |
|---|------|------|---------|
| 0 | **[Foundation](00-foundation.md)** | ~45–60 min | Migrations, models, config, gate, reverse-geocode, lang |

### Phase 1 Features

| # | Spec | Est. | Key files |
|---|------|------|-----------|
| 1 | [Admin dashboard](06-admin-dashboard.md) | ~1–2 h | AdminController, admin gate, views |
| 2 | [Search history (DB)](01-search-history.md) | ~30 min | UserSearchService, FloodWatchDashboard |
| 3 | [Use my location (GPS)](02-use-my-location.md) | ~30 min | FloodWatchDashboard, Alpine/JS |
| 4 | [Location bookmarks](03-bookmarks.md) ✓ | ~1–2 h | LocationBookmarkController, Profile UI |
| 5 | [Route check (From/To)](04-route-check.md) ✓ | ~1–2 h | RouteCheckService, OSRM, Livewire |
| 6 | [Donations](05-donations.md) ✓ | ~15 min | Layout, profile view |
| 7 | [Backend polling](07-backend-polling.md) | ~1 h | Scheduled job, routes/console.php |
| 8 | [Revised wireframe UI](08-revised-wireframe-ui.md) | ~3–4 h | Layout refactor, Risk/Route blocks, responsive |

### Future (Phase 2)

| # | Spec | Est. | Purpose |
|---|------|------|---------|
| 9 | [Smarter route verdict](09-smarter-route-verdict.md) | ~8–10 h | Rivers on route, wet areas, Muchelney rule |
| 10 | [Analytics layer](10-analytics-layer.md) | ~2–4 h | Operational metrics, trends, dashboard, alerts |

**Note**: Admin dashboard comes first so we can monitor API health, users, and LLM cost from the start. Search history enhances admin metrics when built.

**Incremental wireframe**: Builds 03, 04, 05 include "Wireframe placement" – add their UI in the revised wireframe position as you build, so you see layout changes as you go. Build 08 then refines the shell (header, grid, Risk/Action blocks).

---

## Conventions

- **Acceptance criteria**: Each spec has a checklist; verify before moving to next
- **TDD**: Write failing test first (`sail artisan make:test --pest`)
- **Models**: `sail artisan make:model X -mf` (model, migration, factory)
- **Config**: Use `config/flood-watch.php` for feature flags and env
- **Lang**: Add keys to `lang/en/flood-watch.php`
- **Existing patterns**: Check `FloodWatchDashboard`, `FloodWatchTrendService`, `LocationResolver`

---

## Quick Start

```bash
sail up -d
sail artisan migrate
sail test
```
