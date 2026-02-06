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

## Out of Scope (Part 2+)

- Live Activity Feed sidebar
- SystemActivity / FetchLatestInfrastructureData
- Real sparkline data (7-day trend; requires persistence)
- API-first Livewire refactor (still uses services directly for now)
