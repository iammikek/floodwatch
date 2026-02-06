# Next Steps Development Plan

Consolidated development plan based on planning work (Feb 2026).

## 0. Instant Load Dashboard (Priority)

**Ref**: `docs/NEXT_STEPS_INSTANT_LOAD.md`

- Make Langport dashboard load instantly by using cached `getMapData()` in mount, pre-warming cache in scheduled job, and passing server data to status grid and map
- See `NEXT_STEPS_INSTANT_LOAD.md` for step-by-step implementation plan

## 1. Road Data Strategy

**Ref**: `docs/ROAD_DATA_STRATEGY.md`

- **Relevance filtering**: Show only flood-related + paired-road incidents when floods exist
- **Cascading impact**: Update system prompt so LLM considers diversion impact, emergency access, isolation
- **Predictive rules**: Expand `flood_area_road_pairs` and `predictive_rules` (Curry Moor, Salt Moor, Stan Moor, Thorney, Devon cut-off areas)
- **Sudden change detection**: Use trends/cache to flag new closures when correlated with flood (future)

## 2. National Rail Integration

**Ref**: `docs/DATA_SOURCES.md`

- **Primary focus**: Trackside flooding reports – surface disruption where cause is flooding or adverse weather affecting the line (e.g. Dawlish coastal, Stoke Canon, Cowley Bridge)
- **Sign-up**: Rail Data Marketplace (raildata.org.uk) for LDB/Darwin access
- **Service**: `NationalRailService` – fetch departures/delays for key South West stations
- **Filtering**: Prioritise or flag disruption reasons that mention flooding, trackside flooding, adverse weather, line closure
- **Tool**: `GetRailDisruption` – LLM can call when region has rail; include flood-related disruption in summary
- **UI**: Dedicated "Rail Status" section (pill + list), mirror Road Status pattern
- **Config**: `flood-watch.rail_stations` per region (Exeter, Dawlish, Plymouth, Bristol, Taunton)
- **Attribution**: National Rail in footer

## 3. Highways Data Enrichment (in progress)

**Ref**: Plan "Best Use of National Highways Data"

- **Done**: RoadIncident enriched with startTime, endTime, locationDescription, managementType, isFloodRelated
- **Pending**: sortIncidentsByPriority, location-aware filtering (proximity), expand correlation pairs

## 4. Existing Backlog

**Ref**: `docs/DEVELOPMENT.md`

- Queue-based async for high traffic
- Polygon limit tuning

## Reference

| Doc | Purpose |
|-----|---------|
| `docs/NEXT_STEPS_INSTANT_LOAD.md` | Instant load dashboard: cache, pre-warm, server data to grid/map |
| `docs/ACCEPTANCE_CRITERIA.md` | Success checklist: latency, correlation, graceful failure, attribution |
| `docs/ROAD_DATA_STRATEGY.md` | Road data relevance, cascading impact, predictive rules, cut-off areas |
| `docs/DATA_SOURCES.md` | National Rail, emergency services, surfacing options |
| `docs/DEVELOPMENT.md` | Backlog, milestones, tooling |
