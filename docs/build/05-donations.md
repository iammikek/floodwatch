# Build: Donations

"Support Flood Watch" link in footer and profile. Links to external donation page (Ko-fi, Buy Me a Coffee, PayPal).

**Prerequisite**: [00-foundation.md](00-foundation.md) – `donation_url` config exists.

**Ref**: `docs/BRIEF.md` §8, `docs/PLAN.md` Donations

---

## Acceptance Criteria

- [ ] "Support Flood Watch" link in footer; links to `config('flood-watch.donation_url')` or placeholder
- [ ] Profile page has "Support Flood Watch" block with Donate link
- [ ] Link opens in new tab (`target="_blank" rel="noopener"`)
- [ ] When `FLOOD_WATCH_DONATION_URL` empty, link can point to placeholder or be hidden (document behaviour)
- [ ] `sail test` passes

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
