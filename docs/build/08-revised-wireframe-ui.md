# Build: Revised Wireframe UI

Reorganise the Flood Watch dashboard to match the revised brief wireframe (`public/wireframes/revised-brief.html`).

**Ref**: `docs/WIREFRAMES.md`, `public/wireframes/revised-brief.html`

**Note**: Builds 03, 04, 05 include "Wireframe placement" – they add their UI in the revised wireframe position as you build. So by the time you do build 08, the location dropdown, Route Check section, and footer changes may already be in place. Build 08 focuses on layout shell, Risk/Action blocks, grid, and Danger to Life.

---

## Prerequisites

- [00-foundation](00-foundation.md)
- [01-search-history](01-search-history.md) ✓
- [02-use-my-location](02-use-my-location.md) ✓
- [03-bookmarks](03-bookmarks.md) ✓ – location dropdown (adds in wireframe position)
- [04-route-check](04-route-check.md) ✓ – Route Check section (adds in wireframe position)
- [05-donations](05-donations.md) ✓ – footer support link (adds in wireframe position)

---

## Gap Analysis: Current vs Revised

| Area | Current | Revised |
|------|---------|---------|
| **Header** | ✓ Compact header; location at top; [Change] [Refresh] for logged in | Compact header: Location at top, [Change] [Refresh] [Profile] |
| **Location** | ✓ `📍 {location} · {outcode}` when results; [Change] [Use my location] [Refresh]; bookmarks below | Location bar: `📍 Langport · TA10 9` + [Change] [Use my location]; bookmarks dropdown for registered |
| **Results flow** | Long scroll: badges → flood list → forecast → weather → road → map → summary | **Mobile**: Your Risk → Action Steps → Route Check (no map). **Desktop**: Risk + Route side by side; Map + Flood Warnings side by side |
| **Risk** | Embedded in AI summary | Dedicated "Your Risk" block: House + Roads statements |
| **Action Steps** | Embedded in AI summary | Dedicated "Action Steps" block; danger-to-life shows 🆘 Emergency block |
| **Route Check** | ✓ Section with From/To inputs, result panel | Section with From/To inputs, result panel |
| **Map** | After search | Same; **omitted on mobile** in revised (condensed) |

---

## Build Order (How to Implement)

### Phase A: Layout Shell (No data changes) ✓

1. **Header refactor** ✓
   - Move location into header bar (desktop: `[Location ▼] 📍 TA10 9 [Change] [Refresh] [👤]`)
   - Mobile: `Flood Watch [Langport ▼] [👤]` or `[Login] [Register]` for guests
   - Add "Change location" modal/slide that shows recent searches + input
   - Reduce or remove long intro paragraph; keep minimal

2. **Location bar component** ✓
   - Display: `📍 {location} · {outcode}` (e.g. Langport · TA10 9)
   - Buttons: [Change], [Use my location], [Refresh] (logged in)
   - Guest: [Change] [Use my location]
   - Registered: bookmarks below header when results; [Refresh] re-runs search
   - Change opens location-search (recent + bookmarks + input)

3. **Responsive breakpoints** ✓
   - Mobile (< sm): stacked, condensed
   - Desktop two-column grid: Phase C

### Phase B: Content Sections (Reorder + Extract) ✓

4. **Your Risk block** ✓
   - Deterministic from floods/incidents: house (at_risk/clear), roads (closed/delays/clear)
   - Display as dedicated section above action steps

5. **Action Steps block** ✓
   - Deterministic from floods/incidents: deploy defences, monitor updates, avoid routes, or none
   - Dedicated section with clear heading

6. **Danger to Life block** ✓
   - When `severityLevel === 1` in any flood: show 🆘 Emergency block
   - Content: 999, Floodline 0345 988 1188, evacuation instructions
   - Lang keys: `flood-watch.dashboard.emergency_*`

7. **Route Check section**
   - Built in [04-route-check](04-route-check.md) with wireframe placement
   - Build 08: ensure it slots correctly into desktop grid (Risk | Route side by side)

### Phase C: Desktop Grid + Map ✓

8. **Desktop two-column layout** ✓
   - Top row: Risk (left) | Route Check (right)
   - Bottom row: Map (left, larger) | Flood Warnings list (right)
   - Footer: Road Status · Forecast · River Levels · Attribution (section links when results)

9. **Mobile: omit map** ✓
   - Map hidden below `sm` (640px); `hidden sm:block` on map wrapper

### Phase D: Profile + Donations

10. **Profile default location**
    - Depends on [03-bookmarks](03-bookmarks.md)
    - Profile: default location input, "Set as default", bookmarks dropdown
    - Pre-load default on app open for registered users

11. **Footer**
    - "2 flood warnings · 1 road closed · Last updated 2:45 pm"
    - Support Flood Watch link (from [05-donations](05-donations.md))
    - Attribution: EA, National Highways, Open-Meteo

---

## Implementation Notes

- **Incremental**: Each phase can be a PR. Builds 03, 04, 05 add their pieces in wireframe position as you go – you see layout changes incrementally. Build 08 then refines the shell (header, grid, Risk/Action blocks).
- **Backward compatibility**: Keep existing `FloodWatchService` and `FloodWatchDashboard` data flow; only change Blade structure and section order.
- **Structured AI output**: Consider extending the AI response schema to return `house_risk`, `roads_risk`, `action_steps[]` for easier extraction. Otherwise use regex/markdown parsing.
- **Tailwind**: Use existing patterns (`max-w-2xl`, `rounded-lg`, `border-slate-200`). Match `public/wireframes/revised-brief.html` classes.

---

## File Checklist

| File | Changes |
|------|---------|
| `resources/views/livewire/flood-watch-dashboard.blade.php` | ✓ Layout refactor; location-header at top |
| `resources/views/components/flood-watch/search/location-header.blade.php` | ✓ Compact header; Change/Refresh; displayLocation/outcode |
| `resources/views/layouts/flood-watch.blade.php` | Header adjustments if needed |
| `app/Livewire/FloodWatchDashboard.php` | ✓ displayLocation, outcode, extractOutcode(); possibly: houseRisk, roadsRisk, actionSteps |
| `lang/en/flood-watch.php` | ✓ change, refresh, change_location; `emergency_*`, `your_risk`, `action_steps` (Phase B) |
| `app/Services/RouteCheckService.php` | From build 04 |
| Profile views | From build 03 |

---

## Acceptance Criteria

- [x] Header shows location at top; [Change] [Use my location] [Refresh] for logged in (Profile in layout header)
- [x] Mobile: Your Risk → Action Steps → Route Check (no map)
- [x] Desktop: Risk + Route side by side; Map + Flood Warnings side by side
- [x] Danger to Life block visible when severe flood warning
- [x] Route Check section present (from build 04)
- [x] Footer: section links (Road Status · Forecast · River Levels), support link
- [x] Responsive; matches wireframe structure (Phase A)
- [x] `sail test` passes
