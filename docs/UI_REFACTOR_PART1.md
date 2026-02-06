# UI Refactor Part 1

**Created**: 2026-02-05  
**Related**: [SITUATIONAL_AWARENESS_DASHBOARD.md](./SITUATIONAL_AWARENESS_DASHBOARD.md), [WIREFRAME_SITUATIONAL_AWARENESS.md](./WIREFRAME_SITUATIONAL_AWARENESS.md)

## Scope (Part 1)

1. **Search to top-right** – Move location input and "Check status" to header (secondary action).
2. **Status Grid hero** – Replace search-first hero with 4-column status grid:
   - Hydrological Activity (placeholder/sparkline stub)
   - Infrastructural Impact (closures count)
   - Weather Outlook (today + next 2 days)
   - AI Advisory (summary or prompt)
3. **Map visible by default** – Map loads on page load with default centre (Langport); no search required.
4. **"Check my location"** – Primary CTA in header; search refocused on personalised alerts.
5. **Regional Risk Gauge** – South West Risk Index (0–100) with colour bands, label, and summary. Cached 15 min.

## TDD

All changes must have a failing test first. Tests in `tests/Feature/` for:
- Layout structure (header, status grid, map)
- Livewire component behaviour

## Status Grid Refinements (Part 1.1)

- **Hydrological**: "X stations elevated" when any station has elevated level; else station count
- **Infrastructural**: "X / Y closures" (active vs monitored routes from config)
- **Weather**: Precipitation "💧 X mm next 48h" when > 0 (sum of first 2 days)
- **AI Advisory**: Italic styling for quote appearance

## Side-by-Side Layout (Part 1.2)

- **Desktop (lg+)**: Status grid (left, flex-1) + Live Activity Feed (right, w-72) in `flex flex-col lg:flex-row`
- **Mobile**: Stacked (activity feed below status grid)
- Activity feed placeholder: "Live Activity" heading, empty state, "View all" link

## Live Activity Feed (Part 1.3)

- **SystemActivity** model: type, description, severity, occurred_at, metadata
- **InfrastructureDeltaService**: compares previous vs current state, creates activities for flood/incident/river changes
- **FetchLatestInfrastructureData** job: scheduled every 15 min, fetches uncached data, runs delta, stores state in Redis
- **InfrastructureStatusChanged** event: dispatched per new activity
- Dashboard displays recent activities in sidebar; ActivitiesController returns JSON:API

## Out of Scope (Part 2+)

- "Affects you" badge (location-aware)
- Real-time push (Echo) for activity updates
- SystemActivity / FetchLatestInfrastructureData
- Real sparkline data (7-day trend; requires persistence)
- API-first Livewire refactor (still uses services directly for now)
