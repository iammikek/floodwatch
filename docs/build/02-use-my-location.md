# Build: Use My Location (GPS)

Button that uses browser Geolocation API to get user's position and run a search.

**Prerequisite**: [00-foundation.md](00-foundation.md) – `LocationResolver::reverseFromCoords()` and lang keys exist.

**Ref**: `docs/BRIEF.md` §3.1, `docs/WIREFRAMES.md`

---

## Acceptance Criteria

- [ ] "Use my location" button visible next to location input (mobile + desktop)
- [ ] Clicking button triggers browser geolocation prompt
- [ ] On success: lat/long sent to backend; reverse-geocoded; search runs with resolved location
- [ ] On deny/error: user sees "Could not get location. Try entering a postcode."
- [ ] Location outside South West shows existing "outside area" error
- [ ] Feature test: `searchFromGps` with valid coords runs search; invalid coords show error
- [ ] `sail test` passes

---

## Browser API

```javascript
navigator.geolocation.getCurrentPosition(
  (pos) => { /* success: pos.coords.latitude, pos.coords.longitude */ },
  (err) => { /* error: err.message, err.code */ },
  { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
);
```

**Requires**: HTTPS (Railway provides). Works on mobile.

---

## Flow

1. User clicks "Use my location"
2. Browser prompts for permission
3. On success: get `lat`, `long` → send to backend (or reverse-geocode Client side)
4. Backend: `LocationResolver` can accept lat/long or we add a reverse-geocode step (Nominatim: `reverse?lat=X&lon=Y`) to get display name
5. Run search with resolved location

**Option A**: Send lat/long to Livewire; backend reverse-geocodes, validates region, runs search.  
**Option B**: Client reverse-geocodes (e.g. Nominatim), sends location string; backend resolves as usual.

**Recommendation**: Option A – keep logic server-side. Add `LocationResolver::reverseFromCoords(float $lat, float $long)` or use Nominatim `reverse` endpoint.

---

## UI

**Location**: Next to location input in `flood-watch-dashboard.blade.php`

```html
<button type="button" wire:click="useMyLocation"
        class="...">
  📍 {{ __('flood-watch.dashboard.use_my_location') }}
</button>
```

**Livewire**: `FloodWatchDashboard::useMyLocation()` – cannot get GPS from PHP. Use Alpine.js:

```html
<div x-data="useMyLocation()">
  <button @click="getLocation()" :disabled="loading">📍 Use my location</button>
</div>
```

Emit Livewire event with lat/long when got; `FloodWatchDashboard` listens and runs search.

---

## Implementation

1. **Alpine component** in dashboard: `useMyLocation()` – calls `getCurrentPosition`, dispatches `livewire:dispatch` with `location-from-gps` and payload `{lat, long}`
2. **Livewire**: `#[On('location-from-gps')] public function searchFromGps(float $lat, float $long)` – reverse-geocode (or validate), set `$this->location`, call `search()`
3. **Reverse geocode**: Use `LocationResolver::reverseFromCoords($lat, $long)` (from foundation)
4. **Region check**: `reverseFromCoords` returns `in_area`; use existing validation

---

## Fallback

If denied or error: Show message "Could not get location. Try entering a postcode." (add to `lang/en/flood-watch.php`).

---

## Tests

- Feature test: Mock Geolocation or test the `searchFromGps` path with fake lat/long
- Ensure reverse-geocode returns valid South West location; otherwise show outside_area error
