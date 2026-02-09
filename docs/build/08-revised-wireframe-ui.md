# Build: Revised Wireframe UI

Reorganise the Flood Watch dashboard to match the revised brief wireframe (`docs/archive/WIREFRAME_REVISED_BRIEF.md`, `public/wireframes/revised-brief.html`).

**Ref**: `docs/WIREFRAMES.md`, `docs/archive/WIREFRAME_REVISED_BRIEF.md`

**Note**: Builds 03, 04, 05 include "Wireframe placement" – they add their UI in the revised wireframe position as you build. So by the time you do build 08, the location dropdown, Route Check section, and footer changes may already be in place. Build 08 focuses on layout shell, Risk/Action blocks, grid, and Danger to Life.

---

## Prerequisites

- [00-foundation](00-foundation.md)
- [01-search-history](01-search-history.md) ✓
- [02-use-my-location](02-use-my-location.md) ✓
- [03-bookmarks](03-bookmarks.md) ✓ – location dropdown (adds in wireframe position)
- [04-route-check](04-route-check.md) – Route Check section (adds in wireframe position)
- [05-donations](05-donations.md) ✓ – footer support link (adds in wireframe position)

---

## Gap Analysis: Current vs Revised

| Area | Current | Revised |
|------|---------|---------|
| **Header** | Title + intro paragraph; location below | Compact header: Location at top, [Change] [Refresh] [Profile] |
| **Location** | Input + "Use my location" + "Check status" | Location bar: `📍 Langport · TA10 9` + [Change] [Use my location]; bookmarks dropdown for registered |
| **Results flow** | Long scroll: badges → flood list → forecast → weather → road → map → summary | **Mobile**: Your Risk → Action Steps → Route Check (no map). **Desktop**: Risk + Route side by side; Map + Flood Warnings side by side |
| **Risk** | Embedded in AI summary | Dedicated "Your Risk" block: House + Roads statements |
| **Action Steps** | Embedded in AI summary | Dedicated "Action Steps" block; danger-to-life shows 🆘 Emergency block |
| **Route Check** | Not built | Section with From/To inputs, result panel |
| **Map** | After search | Same; **omitted on mobile** in revised (condensed) |

---

## Build Order (How to Implement)

### Phase A: Layout Shell (No data changes)

1. **Header refactor**
   - Move location into header bar (desktop: `[Location ▼] 📍 TA10 9 [Change] [Refresh] [👤]`)
   - Mobile: `Flood Watch [Langport ▼] [👤]` or `[Login] [Register]` for guests
   - Add "Change location" modal/slide that shows recent searches + input
   - Reduce or remove long intro paragraph; keep minimal

2. **Location bar component**
   - Display: `📍 {location} · {outcode}` (e.g. Langport · TA10 9)
   - Buttons: [Change], [Use my location], [Profile] (logged in)
   - Guest: [Change location] only
   - Registered: location dropdown (bookmarks) when 03-bookmarks built

3. **Responsive breakpoints**
   - Mobile (< sm): stacked, condensed, no map
   - Desktop (lg+): two-column grid (Risk + Route | Map + Floods)

### Phase B: Content Sections (Reorder + Extract)

4. **Your Risk block**
   - Extract "House" and "Roads" statements from AI response
   - Options: (a) LLM returns structured `{house_risk, roads_risk}` in tool/response; (b) parse from markdown; (c) deterministic from floods/incidents
   - Display as dedicated section above action steps

5. **Action Steps block**
   - Extract bullet list from AI response or generate from floods/incidents
   - Dedicated section with clear heading

6. **Danger to Life block**
   - When `severityLevel === 1` in any flood: show 🆘 Emergency block
   - Content: 999, Floodline 0345 988 1188, evacuation instructions
   - Lang keys: `flood-watch.dashboard.emergency_*`

7. **Route Check section**
   - Built in [04-route-check](04-route-check.md) with wireframe placement
   - Build 08: ensure it slots correctly into desktop grid (Risk | Route side by side)

### Phase C: Desktop Grid + Map

8. **Desktop two-column layout**
   - Top row: Risk (left) | Route Check (right)
   - Bottom row: Map (left, larger) | Flood Warnings list (right)
   - Footer: Road Status · Forecast · River Levels · Attribution

9. **Mobile: omit map**
   - Config or breakpoint: `@media (max-width: 640px)` hide map
   - Or: collapsible "Show map" to reduce payload on small screens

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
| `resources/views/livewire/flood-watch-dashboard.blade.php` | Layout refactor, section order, new blocks |
| `resources/views/layouts/flood-watch.blade.php` | Header adjustments if needed |
| `app/Livewire/FloodWatchDashboard.php` | Possibly: computed `houseRisk`, `roadsRisk`, `actionSteps` if extracted |
| `lang/en/flood-watch.php` | `emergency_*`, `your_risk`, `action_steps`, `route_check` |
| `app/Services/RouteCheckService.php` | From build 04 |
| Profile views | From build 03 |

---

## Acceptance Criteria

- [ ] Header shows location at top; [Change] [Use my location] [Profile] for logged in
- [ ] Mobile: Your Risk → Action Steps → Route Check (no map)
- [ ] Desktop: Risk + Route side by side; Map + Flood Warnings side by side
- [ ] Danger to Life block visible when severe flood warning
- [ ] Route Check section present (stubbed if build 04 not done)
- [ ] Footer: summary counts, last updated, support link
- [ ] Responsive; matches wireframe structure
- [ ] `sail test` passes
