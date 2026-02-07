# Build: Admin Dashboard

API health, LLM cost, user metrics, budget alerts. Restricted to admin users. **Build early** – first feature after foundation.

**Prerequisite**: [00-foundation.md](00-foundation.md) – `accessAdmin` gate exists.

**Ref**: `docs/PLAN.md` Admin Dashboard, `docs/WIREFRAMES.md`

---

## Acceptance Criteria

- [x] Route `/admin` (or `/admin-dashboard`) exists and requires auth
- [x] Non-admin users receive 403 when visiting `/admin`
- [x] Admin users can access `/admin` and see dashboard
- [x] API Health section displays: EA, Flood Forecast, Weather, National Highways, Cache status (from `/health` or equivalent)
- [x] User Metrics section displays: total users; active (7d) if derivable; placeholder for searches until UserSearch built
- [x] LLM Cost section displays: requests today/month or placeholder; budget alert at 80% if configured
- [x] Layout matches wireframe (`docs/WIREFRAMES.md`); Tailwind styling
- [x] Feature test: guest and user get 403; admin gets 200

---

## Access Control

- **Route**: `/admin` or `/admin-dashboard`
- **Gate**: `$this->authorize('accessAdmin')` – defined in foundation

---

## API Health

Reuse `/health` endpoint response. `HealthController` returns EA, NH, Cache, etc. Admin dashboard can:

- Fetch `/health` via HTTP or inject `HealthController` logic
- Display status: ✓ ok / ✗ failed / skipped

---

## LLM Cost

- **Source**: OpenAI usage API or infer from request count
- **Storage**: Add `llm_requests` table or use existing analytics; or call OpenAI Usage API (requires Org key)
- **Simpler**: Count `FloodWatchService::chat()` calls from logs/Telescope; multiply by avg cost (e.g. $0.02/request for gpt-4o-mini)
- **Config**: `FLOOD_WATCH_LLM_BUDGET_MONTHLY` (e.g. 20) – alert at 80%

---

## User Metrics

- Total users: `User::count()`
- Active (7d): users with recent sessions (e.g. `Session::where('last_activity', '>=', now()->subDays(7)->timestamp)->distinct('user_id')->count('user_id')`) or placeholder
- Searches: `UserSearch::count()` when available (build 2); else show 0 or "—"
- Top regions/postcodes: from `UserSearch` when available; else empty or placeholder

---

## Views

- `resources/views/admin/dashboard.blade.php` – layout with sections: API Health, LLM Cost, User Metrics
- Use Tailwind; match wireframe layout

---

## Tests

- Guest/user cannot access admin route
- Admin can access
- Metrics render (mock data)
