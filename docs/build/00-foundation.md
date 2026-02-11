# Foundation Work (Do First)

Do this **before** building any Phase 1 features. Creates shared schema, config, gates, and utilities that features depend on.

**Est. time**: ~45–60 min with Cursor agentic

---

## 1. Migrations

Create and run both migrations so all Phase 1 features have their tables.

```bash
sail artisan make:migration create_user_searches_table
sail artisan make:migration create_location_bookmarks_table
```

**user_searches** (see `docs/schema.md`):
- `user_id` (nullable FK), `session_id` (nullable), `location`, `lat`, `lng`, `region` (nullable), `searched_at`
- Indexes: `user_id`, `session_id`, `searched_at`

**location_bookmarks**:
- `user_id` (FK), `label`, `location`, `lat`, `lng`, `region` (nullable), `is_default` (default false)
- Indexes: `user_id`
- Unique: only one `is_default = true` per user (use migration or model observer)

```bash
sail artisan migrate
```

---

## 2. Models & Factories

```bash
sail artisan make:model UserSearch -f
sail artisan make:model LocationBookmark -f
```

**UserSearch**: `belongsTo(User::class)`. Fillable: `user_id`, `session_id`, `location`, `lat`, `lng`, `region`, `searched_at`. Cast `searched_at` as datetime.

**LocationBookmark**: `belongsTo(User::class)`. Fillable: `user_id`, `label`, `location`, `lat`, `lng`, `region`, `is_default`. When `is_default = true`, clear other defaults for user (use `saving` observer or in service).

**User**: Add `hasMany(UserSearch::class)`, `hasMany(LocationBookmark::class)`.

**Factories**: UserSearch, LocationBookmark – minimal fields for tests.

**Enums for mappers**: Use `Region::warmCacheLocation()`, `IncidentType::icon()`, `IncidentStatus::label()` instead of config arrays. See `agents.md` conventions.

---

## 3. Config

Add to `config/flood-watch.php`:

```php
'donation_url' => env('FLOOD_WATCH_DONATION_URL', ''),
'warm_cache_locations' => [
    'somerset' => 'Langport',
    'bristol' => 'Bristol',
    'devon' => 'Exeter',
    'cornwall' => 'Truro',
],
```

---

## 4. Admin Gate

In `App\Providers\AppServiceProvider::boot()` or create `AuthServiceProvider`:

```php
Gate::define('accessAdmin', fn (User $user) => $user->isAdmin());
```

Route middleware or controller: `$this->authorize('accessAdmin')` for admin routes.

---

## 5. LocationResolver – Reverse Geocode

Add method for Use my location (GPS → location string):

```php
public function reverseFromCoords(float $lat, float $long): array
```

- Call Nominatim: `GET https://nominatim.openstreetmap.org/reverse?lat=X&lon=Y&format=json`
- Return `['valid' => bool, 'in_area' => bool, 'location' => string, 'region' => ?string, 'error' => ?string]`
- Validate against South West bounding box; derive region from address

**Tests**: Unit test with mocked HTTP response.

---

## 6. Lang Keys

Add to `lang/en/flood-watch.php`:

```php
'dashboard' => [
    // ... existing
    'use_my_location' => 'Use my location',
    'recent_searches' => 'Recent searches',
    'gps_error' => 'Could not get location. Try entering a postcode.',
],
```

---

## Acceptance Criteria

- [x] `user_searches` and `location_bookmarks` tables exist; migrations run cleanly
- [x] `UserSearch` and `LocationBookmark` models exist with factories; `User` has `userSearches()` and `locationBookmarks()` relationships
- [x] `config('flood-watch.donation_url')` and `config('flood-watch.warm_cache_locations')` return expected values
- [x] `Gate::forUser($admin)->allows('accessAdmin')` is true; `Gate::forUser($user)->allows('accessAdmin')` is false
- [x] `LocationResolver::reverseFromCoords(51.04, -2.83)` returns valid South West location (or mocked in test)
- [x] Lang keys `flood-watch.dashboard.use_my_location`, `recent_searches`, `gps_error` exist
- [x] `User::factory()->admin()->create()` produces admin user (add state if needed)
- [x] Enums used for mappers: `Region::warmCacheLocation()`, `IncidentType::icon()`, `IncidentStatus::label()`
- [x] Schema uses `lat`, `lng` (not `long`) for longitude
- [x] `sail test` passes

---

## 7. Verification

After foundation:

```bash
sail artisan migrate
sail test
```

- All migrations run
- UserSearch and LocationBookmark models exist with relationships
- Config keys `donation_url`, `warm_cache_locations` exist
- Gate `accessAdmin` defined
- `LocationResolver::reverseFromCoords()` exists and tested
- User factory can create admin: `User::factory()->admin()->create()` (add `admin` state if not present)

---

## Dependency Graph

```
Migrations → Models → User relationships
Config → Donations, Warm cache
Gate → Admin dashboard
Reverse geocode → Use my location
Lang → All UI features
```

---

## Next

Once foundation is complete, proceed in order: **Admin dashboard** (06) → Search history (01) → Use my location (02) → Bookmarks (03) → Route check (04) → Donations (05) → Backend polling (07). See `docs/build/README.md`.
