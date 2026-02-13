# Build: Donations

"Support Flood Watch" link in footer and profile. Links to external donation page (Ko-fi, Buy Me a Coffee, PayPal).

**Status**: ✅ Complete

**Prerequisite**: [00-foundation.md](00-foundation.md) – `donation_url` config exists.

**Ref**: `docs/brief.md` §8, `docs/plan.md` Donations

---

## Acceptance Criteria

- [x] "Support Flood Watch" link in footer; links to `config('flood-watch.donation_url')` or placeholder
- [x] Profile page has "Support Flood Watch" block with Donate link
- [x] Link opens in new tab (`target="_blank" rel="noopener"`)
- [x] When `FLOOD_WATCH_DONATION_URL` empty, link can point to placeholder or be hidden (document behaviour)
- [x] `sail test` passes

---

## Config

Use `config('flood-watch.donation_url')` – already in config from foundation.

---

## UI

**Footer**: `resources/views/layouts/flood-watch.blade.php` (or equivalent layout used by dashboard)

```html
<a href="{{ config('flood-watch.donation_url', 'https://ko-fi.com/...') }}" target="_blank" rel="noopener">
  Support Flood Watch
</a>
```

---

## Wireframe Placement (incremental UI)

Place in revised wireframe position so changes are visible:

- **Dashboard footer** (after results): Add summary line + support link: `"2 flood warnings · 1 road closed · Last updated 2:45 pm"` + `Support Flood Watch` link. Reuse existing footer area in `flood-watch-dashboard.blade.php`; extend with dynamic counts when `$floods` / `$incidents` exist.
- **Profile**: Support block as specified above.

**Profile**: Add block in `resources/views/profile/edit.blade.php`:

```html
<div class="mt-6 pt-6 border-t">
  <p class="text-sm font-medium">Support Flood Watch</p>
  <p class="text-xs text-slate-500 mt-1">Help cover API and hosting costs. The app stays free.</p>
  <a href="..." class="text-blue-600 mt-2 inline-block">Donate →</a>
</div>
```

---

## Env

```
FLOOD_WATCH_DONATION_URL=https://ko-fi.com/yourpage
```

---

## Tests

- Optional: Assert link present in footer (feature test). Low priority.
