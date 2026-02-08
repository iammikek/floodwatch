# Build: Admin Dashboard

API health, LLM cost, user metrics, budget alerts, recent LLM requests. Restricted to admin users. **Build early** – first feature after foundation.

**Prerequisite**: [00-foundation.md](00-foundation.md) – `accessAdmin` gate exists.

**Ref**: `docs/PLAN.md` Admin Dashboard, `docs/WIREFRAMES.md`

---

## Acceptance Criteria

- [x] Route `/admin` (or `/admin-dashboard`) exists and requires auth
- [x] Non-admin users receive 403 when visiting `/admin`
- [x] Admin users can access `/admin` and see dashboard
- [x] API Health section displays: EA, Flood Forecast, Weather, National Highways, Cache status (from `/health` or equivalent)
- [x] User Metrics section displays: total users; total searches (from `UserSearch`)
- [x] LLM Cost section displays: requests today/month; input/output tokens; est. cost; budget alert at 80% if configured
- [x] Recent LLM Requests section displays: last 10 requests (time, model, tokens, region, user)
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

- **Source**: OpenAI Usage API via `OpenAiUsageService` (requires Org Admin key); cached 5 min
- **Storage**: `llm_requests` table records each `FloodWatchService::chat()` completion: `input_tokens`, `output_tokens`, `model`, `user_id`, `region`, `openai_id`
- **Config**: `FLOOD_WATCH_LLM_BUDGET_MONTHLY` (e.g. 20) – alert at 80%; `FLOOD_WATCH_LLM_BUDGET_INITIAL` for remaining (est.) when OpenAI credit balance not exposed

---

## Recent LLM Requests

- **Source**: `LlmRequest::query()->with('user')->latest()->limit(10)->get()`
- **Columns**: Time, Model, Input tokens, Output tokens, Region, User (email or "Guest")
- **Table**: `llm_requests` – populated by `FloodWatchService::recordLlmRequest()` after each OpenAI chat completion

---

## User Metrics

- Total users: `User::count()`
- Total searches: `UserSearch::count()`

---

## Views

- `resources/views/admin/dashboard.blade.php` – layout with sections: API Health, User Metrics, LLM Cost, Recent LLM Requests
- Use Tailwind; match wireframe layout

---

## Tests

- Guest/user cannot access admin route
- Admin can access
- Metrics render (mock data)
