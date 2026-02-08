# Build: Search History (DB)

Store searched locations in the database. Feeds "Recent searches" UI and admin metrics.

**Prerequisite**: [00-foundation.md](00-foundation.md) – migration and model already exist.

**Schema**: `docs/SCHEMA.md` – `user_searches` table

---

## Acceptance Criteria

- [x] Each successful search creates a `UserSearch` record (guest: `user_id` null, `session_id` set; registered: `user_id` set)
- [x] Recent searches (last 5) appear when user changes location or in quick-pick UI
- [x] Guest recent searches keyed by session; registered by user
- [x] Admin dashboard User Metrics can show `UserSearch::count()` and top regions (enhances build 1)
- [x] Feature test: search creates UserSearch; recent searches returns correct items
- [x] `sail test` passes

---

## Service / Integration

**Create** `App\Services\UserSearchService` or add to existing flow:

- `record(string $location, ?float $lat, ?float $long, ?string $region, ?int $userId, ?string $sessionId): void`
- Call after successful search in `FloodWatchDashboard::search()`
- Guests: `user_id = null`, `session_id = session()->getId()`
- Registered: `user_id = auth()->id()`, `session_id = null` (or keep for consistency)

**Hook point**: After `$trendService->record()` in `FloodWatchDashboard.php` line ~147, add `$userSearchService->record(...)`.

---

## Recent Searches UI

- **Location**: `resources/views/livewire/flood-watch-dashboard.blade.php` – near the location input
- **When**: Show when user clicks "Change location" or in a dropdown/quick-pick
- **Data**: `UserSearch::query()->where('user_id', auth()->id())->orWhere('session_id', session()->getId())->latest('searched_at')->limit(5)->get()`
- **Display**: Clickable pills that set `$location` and trigger search

---

## Tests

- `tests/Feature/UserSearchTest.php`: Record on search, guests use session_id, registered use user_id
- `tests/Unit/UserSearchTest.php`: Model relationships, fillable

---

## Retention

Add scheduled job (or document): Prune rows older than 90 days. Can defer to later. See `docs/DATA_RETENTION.md` for future task.
