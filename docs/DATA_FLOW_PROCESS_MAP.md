# Data Flow Process Map

**Created**: 2026-02-05  
**Related**: [SITUATIONAL_AWARENESS_DASHBOARD.md](./SITUATIONAL_AWARENESS_DASHBOARD.md), [PLAN_SPLIT_DATA_FASTER_MAP.md](./PLAN_SPLIT_DATA_FASTER_MAP.md)

## Principle: API-First

**All frontend data retrieval goes through our own API.** The frontend never calls backend services directly. The API is the single contract and follows JSON:API 1.1.

---

## Current vs Target Data Flow

### Before (Tightly Coupled)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  FRONTEND (Livewire)                                                         │
│  FloodWatchDashboard → search() → FloodWatchService::chat()                  │
└─────────────────────────────────────────────────────────────────────────────┘
    │
    │  Direct PHP calls (no HTTP)
    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  BACKEND (PHP Services)                                                      │
│  FloodWatchService → EnvironmentAgencyFloodService, NationalHighwaysService,│
│  RiverLevelService, FloodForecastService, WeatherService                    │
└─────────────────────────────────────────────────────────────────────────────┘
    │
    │  HTTP (server-to-server)
    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  EXTERNAL APIS                                                               │
│  EA Flood API, National Highways DATEX II, Open-Meteo, FGS Forecast         │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Problem**: No API contract; no JSON:API; no clear separation for future SPA or mobile clients.

---

### After (API-First)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  FRONTEND (Livewire / future SPA)                                            │
│  - Situational Awareness Dashboard                                            │
│  - Search-first (guest) flow                                                  │
│  - Polling / Livewire wire:poll / Echo                                        │
└─────────────────────────────────────────────────────────────────────────────┘
    │
    │  HTTP GET/POST (fetch, axios, or Livewire Http::)
    │  Accept: application/vnd.api+json
    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  FLOOD WATCH API (JSON:API)                                                  │
│  /api/v1/floods, /api/v1/incidents, /api/v1/river-levels,                   │
│  /api/v1/forecast, /api/v1/weather, /api/v1/risk, /api/v1/activities,       │
│  /api/v1/map-data (compound), /api/v1/chat (AI summary)                      │
└─────────────────────────────────────────────────────────────────────────────┘
    │
    │  Internal calls (no HTTP)
    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  BACKEND SERVICES                                                           │
│  FloodWatchService, EnvironmentAgencyFloodService, NationalHighwaysService,│
│  RiverLevelService, FloodForecastService, WeatherService, RiskService        │
└─────────────────────────────────────────────────────────────────────────────┘
    │
    │  HTTP (server-to-server)
    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  EXTERNAL APIS                                                               │
│  EA, National Highways, Open-Meteo, FGS                                      │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Process Map: Search-First (Guest) Flow

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  SEARCH-FIRST DASHBOARD (Guest / Registered)                                  │
└──────────────────────────────────────────────────────────────────────────────┘

  [User clicks "Check status"]
       │
       ├─ Phase 1: GET /api/v1/map-data?lat=51.03&long=-2.83 (or ?location=TA10)
       │     → floods, incidents, river-levels, forecast, weather
       │     → Render map immediately
       │
       └─ Phase 2: POST /api/v1/chat
             Body: { "location": "...", "preFetchedMapData": {...} }
             → AI summary
             → Update assistant response
```

---

## Process Map: Situational Awareness Dashboard (Registered User)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  SITUATIONAL AWARENESS DASHBOARD (Registered User)                            │
└──────────────────────────────────────────────────────────────────────────────┘

  [Page Load]
       │
       ├─ GET /api/v1/risk
       │     → Risk index (0–100), label, summary
       │
       ├─ GET /api/v1/map-data?bounds=... (or ?lat=...&long=...)
       │     → floods, incidents, river-levels, forecast, weather
       │
       ├─ GET /api/v1/activities?page[size]=20
       │     → Recent SystemActivity entries
       │
       └─ (Optional) GET /api/v1/weather?lat=...&long=...
             → If not included in map-data

  [Every 15 min – Polling or Scheduled]
       │
       ├─ Backend: FetchLatestInfrastructureData job runs
       │     → Fetches from services → Compares to Redis → Dispatches events
       │     → Writes SystemActivity
       │
       └─ Frontend: wire:poll.15m or fetch
             ├─ GET /api/v1/risk
             ├─ GET /api/v1/map-data?bounds=...
             └─ GET /api/v1/activities?page[size]=20

  [User: "Check my location"]
       │
       ├─ POST /api/v1/chat { "location": "TA10 9PU", "preFetchedMapData": {...} }
       │     → AI summary
       │
       └─ Update map, status grid, AI advisory from response
```

---

## API Endpoints

| Endpoint | Method | Purpose | JSON:API Resource(s) |
|----------|--------|---------|----------------------|
| `/api/v1/map-data` | GET | Map data (floods, incidents, river-levels, forecast, weather) for bounds or location | `floods`, `incidents`, `river-levels`, `forecast`, `weather` |
| `/api/v1/floods` | GET | Flood warnings (filter by bounds) | `floods` |
| `/api/v1/incidents` | GET | Road incidents (filter by region) | `incidents` |
| `/api/v1/river-levels` | GET | River stations + readings | `river-levels` |
| `/api/v1/forecast` | GET | 5-day flood forecast | `forecast` |
| `/api/v1/weather` | GET | Weather (lat, long) | `weather` |
| `/api/v1/risk` | GET | Regional risk index | `risk` |
| `/api/v1/activities` | GET | Live activity feed (paginated) | `activities` |
| `/api/v1/chat` | POST | AI summary (location + optional pre-fetched data) | `chat` |

---

## JSON:API Conventions

- `Content-Type: application/vnd.api+json`
- `Accept: application/vnd.api+json`
- Top-level: `data`, `included`, `meta`, `links`
- Resources: `type`, `id`, `attributes`, `relationships`
- Sparse fieldsets: `?fields[floods]=description,severityLevel,lat,long`
- Filter: `?filter[bounds]=51.0,-2.9,51.1,-2.8`
- Pagination: `?page[number]=1&page[size]=20`

---

## Auth for API

- **Guest**: Rate-limited; `map-data`, `chat` (with limits).
- **Registered**: Higher limits; `activities`, `risk`, full `map-data` with bounds.
- Session-based auth (same as web).
