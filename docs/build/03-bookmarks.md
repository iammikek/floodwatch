# Build: Location Bookmarks

Registered users can bookmark multiple locations (home, work, parents). One can be the default (pre-loaded on app open).

**Prerequisite**: [00-foundation.md](00-foundation.md) – migration and model already exist.

**Schema**: `docs/SCHEMA.md` – `location_bookmarks` table

---

## Acceptance Criteria

- [ ] Registered users can add, edit, delete bookmarks from profile
- [ ] One bookmark per user can be "default"; setting default clears others
- [ ] Location must resolve via LocationResolver and be in South West
- [ ] Dashboard shows bookmark dropdown when logged in; selecting bookmark loads that location
- [ ] Default bookmark pre-loads on app open (mount)
- [ ] Max 10 bookmarks per user (configurable)
- [ ] Feature tests: CRUD bookmarks; default uniqueness; dashboard loads default
- [ ] `sail test` passes

---

## Profile UI

**Location**: `resources/views/profile/edit.blade.php` (or create profile section for Flood Watch)

- List bookmarks with Edit/Delete
- Add bookmark: input label + location (reuse LocationResolver or postcode input)
- "Set as default" – one per user
- Wire to `ProfileController` or `LocationBookmarkController`

**Routes**: `Route::resource('bookmarks', LocationBookmarkController::class)` under auth, or add methods to ProfileController.

---

## Dashboard Integration

- **Location dropdown**: When logged in, show bookmarks in header/dropdown (see wireframes)
- **Default on mount**: If user has default bookmark, pre-fill `$location` and optionally auto-search
- **FloodWatchDashboard**: Add `$bookmarks` property; load in `mount()` when auth; pass to view

---

## Validations

- Location must resolve via `LocationResolver` and be in South West
- Label required, max 50 chars
- Max 10 bookmarks per user (configurable)

---

## Tests

- Create, update, delete bookmark
- Set default clears other defaults
- Dashboard shows bookmarks when logged in
- Default pre-loads on mount
