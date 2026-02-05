# Situational Awareness Dashboard

**Created**: 2026-02-05  
**Status**: Brief for review  
**Related**: [PLAN_SPLIT_DATA_FASTER_MAP.md](./PLAN_SPLIT_DATA_FASTER_MAP.md)

## Vision

Evolve Flood Watch from a **Search-First** utility to an **Operational Dashboard** that can be left open, refreshes automatically, and highlights events that would cause inconvenience or danger to the user in their location.

**Key principle**: The dashboard should feel "Always On"—providing background intelligence without requiring the user to click "Check status" each time.

### Access: Registered Users Only

The Situational Awareness Dashboard should be built as an **enhanced, registered-user option**. It incurs higher costs:

- **AI tokens**: The "Current State" AI Advisory and any on-change summaries run periodically or on significant events. This consumes more tokens than the current search-on-demand model.
- **Background jobs**: The scheduled fetch runs every 15 minutes regardless of user activity.
- **Real-time updates**: Livewire polling or Echo subscriptions add server load.

**Implication**: Gate the enhanced dashboard behind authentication. Guests keep the existing search-first experience. Registered users can opt in to the full Situational Awareness Dashboard (or we could offer a "lite" version with less frequent AI summaries to control cost).

---

## Goals

1. **Leave it open**: Dashboard updates without page refreshes; no need to re-run searches.
2. **Fixed refresh interval**: Data refreshes every 15 minutes (or configurable).
3. **Location-aware alerts**: Highlight events that affect the user's location (flood warnings, road closures, elevated river levels).
4. **Executive summary**: At-a-glance risk gauge and status grid for staff/operational use.

---

## Core Features

### 1. Event-Driven Activity Feed

**Purpose**: A "Live Activity Log" that shows what is happening now—new flood warnings, road openings/closures, river level changes.

**Components**:
- **SystemActivity model**: Stores activity events (type, description, severity, timestamp, related entity IDs).
- **Livewire component**: `LiveActivityFeed` in the sidebar, updates in real-time.
- **Scheduled Job**: `FetchLatestInfrastructureData` runs every 15 minutes.
- **Delta comparison**: When fetching EA or National Highways data, compare new data against cached version (Redis). If status changed (e.g. new flood warning, road opened/closed), dispatch `InfrastructureStatusChanged` event.
- **Real-time updates**: Use Livewire `#[On]` attribute or Laravel Echo to push updates to the Activity Feed when events occur.

**Logic**:
- Cache previous state in Redis (keyed by data type: floods, incidents, river levels).
- On each fetch, diff new vs. cached. Detect: new warnings, removed warnings, road closures, road reopenings.
- Emit events for each change; Activity Feed subscribes and appends new entries.

---

### 2. Regional Risk Scoring Engine

**Purpose**: A weighted South West Risk Index (0–100) that answers "How dangerous is it overall?"

**Components**:
- **RiskService**: Calculates weighted score from:
  - **High weight**: Severe flood warnings (danger to life).
  - **Medium weight**: A-road closures, flood warnings.
  - **Low weight**: Precipitation trends, elevated river levels.
- **Regional Risk Gauge**: Displayed at top of dashboard (e.g. gauge or progress bar).
- **Standardized JSON:API output** for internal risk metrics.

**Formula (draft)**:
- Severe flood warning: +25 each (capped).
- Flood warning: +10 each.
- A-road closure: +5 each.
- Elevated river level: +2 each.
- Precipitation trend: +1.
- Normalise to 0–100.

---

### 3. UI Refactor (Blade / Livewire)

**Layout changes**:
- **Search bar**: Move to top-right (secondary action; dashboard is primary).
- **Hero area**: Status Grid instead of search-first layout:
  - **Hydrological Activity**: Sparkline of average river levels in the South West.
  - **Infrastructural Impact**: Number of active road closures vs. total monitored routes.
  - **AI Advisory**: Summary of "Current State" from the LLM based on latest data.

**Dashboard as primary view**:
- Map and status grid visible by default (no search required).
- Search refocused on "Check my location" for personalised alerts.

---

## Integration with Split Data Plan

The [Split Data Faster Map](./PLAN_SPLIT_DATA_FASTER_MAP.md) plan is complementary:

| Concern | Split Data Plan | Situational Awareness |
|---------|-----------------|------------------------|
| Map data source | Pre-fetch floods, incidents, river levels | Same data; `FetchLatestInfrastructureData` job uses same services |
| Refresh model | User-triggered search | Scheduled job every 15 min + Livewire polling |
| Caching | Cache for map + AI | Redis for previous state (delta comparison) |
| AI summary | On-demand for search | On-demand for "Current State" in Status Grid |

**Unified approach**:
- `FloodWatchService::getMapData()` (from Split Data plan) can be reused by `FetchLatestInfrastructureData`.
- Job fetches data → compares to Redis → dispatches events on changes → updates dashboard state.
- Dashboard Livewire component polls or subscribes to events; map and status grid update without full page reload.

---

## Technical Architecture

### Data Flow (Always-On Dashboard)

```
┌─────────────────────────────────────────────────────────────────┐
│  Scheduled Job: FetchLatestInfrastructureData (every 15 min)     │
└─────────────────────────────────────────────────────────────────┘
    │
    ├─ Fetch floods, incidents, river levels (reuse getMapData logic)
    ├─ Compare to Redis "previous state"
    ├─ If changed → dispatch InfrastructureStatusChanged
    │     └─ Event creates SystemActivity record
    │     └─ Event broadcast (Echo) or Livewire #[On]
    ├─ Update Redis cache with new state
    └─ (Optional) Trigger AI "Current State" summary if significant change
```

### State Storage

| Location | Purpose |
|----------|---------|
| Redis | Previous state for delta comparison (floods, incidents, river levels, hashes or JSON) |
| Database | SystemActivity records (persistent log) |
| Cache (existing) | FloodWatchService chat/map cache (TTL) |

### Components

| Component | Type | Responsibility |
|-----------|------|-----------------|
| SystemActivity | Eloquent model | Activity log entries |
| LiveActivityFeed | Livewire | Sidebar activity feed, real-time updates |
| RiskService | Service class | Regional risk index calculation |
| FetchLatestInfrastructureData | Scheduled job | Periodic fetch + delta + events |
| InfrastructureStatusChanged | Laravel Event | Fired when status changes |
| Dashboard (refactored) | Livewire | Status grid, map, risk gauge, search |

---

## Technical Constraints

- **Registered users only**: Gate the Situational Awareness Dashboard behind `auth()`; guests use the existing search-first flow.
- Use **Redis** for previous state (delta comparisons).
- All new models have **Factories** and **Tests** (Pest/PHPUnit) to maintain TDD.
- Follow **Standardized JSON:API** output for internal risk metrics.
- Use **Laravel Events** and **Livewire** for real-time updates (no full page refresh).
- Consider **AI token caps** per user to control cost (e.g. max summaries per day).

---

## Location-Aware Alerts

To highlight events that affect the user's location:

1. **User location**: Stored when user enters postcode or uses "Check my location".
2. **Relevance scoring**: For each activity event, compute distance/impact from user location:
   - Flood warning in same catchment or within X km.
   - Road closure on a route the user might use (e.g. A361 for Muchelney).
3. **UI emphasis**: In the Activity Feed, visually distinguish "affects you" vs. "regional" events.
4. **Optional push**: Browser notifications for high-severity, location-relevant events (future).

---

## Implementation Order (Suggested)

1. **Access control**: Ensure routes/components for the enhanced dashboard require `auth()`; redirect guests to existing dashboard.
2. **Foundation**: SystemActivity model, migration, factory, basic tests.
3. **Delta comparison**: Redis previous state + compare logic in fetch services.
4. **InfrastructureStatusChanged event**: Dispatch when deltas detected.
5. **FetchLatestInfrastructureData job**: Schedule every 15 min; wire up to services.
6. **LiveActivityFeed component**: Display activities; subscribe to events.
7. **RiskService + Risk Gauge**: Calculate and display regional risk.
8. **UI refactor**: Status grid, move search, integrate map.
9. **Split Data integration**: Reuse `getMapData()` in job; align caching.

---

## Open Questions

- Exact risk formula weights (to be tuned with domain input).
- Whether AI "Current State" runs on every job cycle or only on significant changes.
- Echo vs. Livewire polling for real-time updates (Echo requires Pusher/Redis broadcast setup).
- Scope of "monitored routes" for Infrastructural Impact (A361, A372, M5 J23–J25, etc.).
- AI token budget per registered user (e.g. cap summaries per day) to control cost.
