# Build: Location Bookmarks

Registered users can bookmark multiple locations (home, work, parents). One can be the default (pre-loaded on app open).

**Status**: ✅ Complete

**Prerequisite**: [00-foundation.md](00-foundation.md) – migration and model already exist.

**Schema**: `docs/SCHEMA.md` – `location_bookmarks` table

**Key files**: `LocationBookmarkController`, `StoreLocationBookmarkRequest`, `profile/partials/bookmarks-form.blade.php`, `config/flood-watch.php` (`bookmarks_max_per_user`)

---

## Acceptance Criteria

- [x] Registered users can add, delete bookmarks from profile
- [x] One bookmark per user can be "default"; setting default clears others
- [x] Location must resolve via LocationResolver and be in South West
- [x] Dashboard shows bookmark dropdown when logged in; selecting bookmark loads that location
- [x] Default bookmark pre-loads on app open (mount)
- [x] Max 10 bookmarks per user (configurable)
- [x] Feature tests: CRUD bookmarks; default uniqueness; dashboard loads default
- [x] `sail test` passes

---

## Profile UI

**Location**: `resources/views/profile/edit.blade.php`, `resources/views/profile/partials/bookmarks-form.blade.php`

- List bookmarks with Set as default / Delete
- Add bookmark: input label + location (reuse LocationResolver or postcode input)
- "Set as default" – one per user
- **Implemented**: `LocationBookmarkController` (store, setDefault, destroy), routes under auth

**Routes**: `POST /bookmarks`, `POST /bookmarks/{bookmark}/default`, `DELETE /bookmarks/{bookmark}`

---

## Dashboard Integration

- **Bookmark buttons**: When logged in, bookmarks shown as quick-select buttons above recent searches; selecting one loads location and runs search
- **Default on mount**: If user has default bookmark, pre-fill `$location` (no auto-search)
- **FloodWatchDashboard**: `getBookmarksProperty()`, `selectBookmark()`, default pre-load in `mount()`

---

## Wireframe Placement (incremental UI)

**Implemented**: Bookmark buttons above location input (same row as recent searches). Future: dropdown in header per wireframe.

- **Header location bar** (logged in): `[Langport ▼] 📍 TA10 9 [Change] [Use my location] [Profile]`
  - Dropdown lists bookmarks; selecting one loads location and runs search
  - "Change" opens location input/recent searches (existing flow)
- **Mobile**: Same structure, compact; bookmark dropdown in header
- Reuse existing location input area for "Change"; add dropdown *above* or *beside* when user has bookmarks

---

## Validations

- Location must resolve via `LocationResolver` and be in South West
- Label required, max 50 chars
- Max 10 bookmarks per user (configurable)

---

## Tests

- **LocationBookmarkControllerTest**: Guest rejection, create, first/second default, set default, delete, authorization (set-default, destroy), max limit
- **FloodWatchDashboardTest**: Default pre-loads on mount, bookmarks shown when logged in
- **LocationBookmarkTest** (model): Factory, casts, default uniqueness, DB constraint
