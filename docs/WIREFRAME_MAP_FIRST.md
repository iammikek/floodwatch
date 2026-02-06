# Dashboard – Map First Wireframe

**Created**: 2026-02-05  
**File**: `public/wireframes/dashboard-map-first.html`

## Purpose

Alternative layout that keeps the map visible without scrolling. Addresses the issue where flood warnings and 5-day forecast push the map to the bottom of the screen.

## Layout

```
Header (search, Check my location)
Risk Gauge
┌─────────────────────────────────────┬──────────────┐
│  Status Grid (4 cards)              │ Live Activity │
│  • Hydrological (floods + rivers)   │              │
│  • Infrastructure                   │              │
│  • Weather                          │              │
│  • AI Advisory                      │              │
└─────────────────────────────────────┴──────────────┘
┌─────────────────────────────────────────────────────┐
│  MAP (40vh min, directly below)                     │
└─────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────┐
│  ▶ 5-day forecast & full summary (collapsible)      │
└─────────────────────────────────────────────────────┘
```

## Key Differences from Original Wireframe

| Aspect | Original (situational-awareness) | Map First |
|--------|----------------------------------|-----------|
| Map position | Below status grid | Same |
| Post-search content | Not shown | Collapsible `<details>` |
| Flood warnings | In Hydrological (sparkline) | In Hydrological (list) |
| 5-day forecast | Not in wireframe | In collapsible section |
| Full AI summary | In AI Advisory (truncated) | In collapsible section |

## Design Decisions

1. **No duplicate sections** – Flood warnings live only in Hydrological Activity. Road status only in Infrastructural Impact.

2. **Map always visible** – Map sits directly below status grid. No scroll required on typical viewport.

3. **Collapsible details** – 5-day forecast and full AI summary in a `<details>` element. User expands when needed. Default collapsed so map stays in view.

4. **Guest header** – Shows search input, Check my location, Login, Register (matches current guest experience).

## How to View

Open `public/wireframes/dashboard-map-first.html` in a browser. Uses Tailwind CDN; no build required.
