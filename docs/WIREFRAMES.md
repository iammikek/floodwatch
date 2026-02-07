# Flood Watch – Wireframes

**Ref**: `docs/BRIEF.md`, `docs/PLAN.md`

---

## Part 1: MVP (Search-First)

### User Journey

```mermaid
flowchart LR
    Open[Open app] --> Location[Enter location]
    Location --> Risk[View risk]
    Risk --> Route[Check route]
    Route --> Advice[Read advice]
    Open --> Register[Register]
    Register --> Login[Login]
```

### Mobile

**Guest**: `[Login] [Register]` · **Logged in**: `[Location ▼] [👤]`

- Location at top; bookmarks dropdown for registered
- Risk → Action steps → Route check
- Danger to life: 999, Floodline 0345 988 1188, evacuation instructions

### Desktop

- Risk + Route check side by side; map; flood/road lists
- Profile: default location, bookmarks
- Admin: API health, LLM cost, user metrics

### Danger to Life

| Element | Content |
|---------|---------|
| Emergency numbers | 999 · Floodline 0345 988 1188 |
| Instructions | Evacuate if advised · Move to higher ground · Do not enter floodwater |

### Admin Dashboard

- **API health**: EA, Flood Forecast, Weather, National Highways, Cache
- **LLM cost**: Requests today/month, est. spend, budget alert
- **User metrics**: Total users, active (7d), searches, default locations, top regions/postcodes

---

## Part 2: Situational Awareness (Phase 2)

**Registered users only**. Auto-refresh every 15 min.

### Layout

- **Header**: Location dropdown, Check my location, Profile
- **Risk gauge**: 0–100, colour bands
- **Status grid**: Hydrological, Infrastructure, Weather, AI Advisory
- **Activity feed**: Live events (new warning, road opened/closed)
- **Map**: Full-width; same Leaflet as MVP

### Guest vs Registered

| Area | Guest | Registered |
|------|-------|------------|
| Hero | Search + Check status | Risk gauge + Status grid |
| Map | After search | Always visible, auto-refresh |
| Activity | None | Live feed |
| Admin | — | `/admin-dashboard` |

---

## HTML Wireframes

- `public/wireframes/revised-brief.html` – MVP
- `public/wireframes/situational-awareness.html` – Phase 2
