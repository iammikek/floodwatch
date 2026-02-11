# Data Sources

Overview of current and planned APIs. Full research (National Rail, emergency services) in `docs/archive/DATA_SOURCES.md`.

---

## Current Sources

| Source | Data | Auth |
|--------|------|------|
| **Environment Agency** | Flood warnings, river/sea levels, polygons | None |
| **National Highways** | Road incidents (closures, lane closures) | API key |
| **Flood Forecast Centre** | 5-day flood risk outlook | None |
| **Open-Meteo** | 5-day weather (temp, precipitation) | None |

---

## National Rail (Planned)

**Ref**: `docs/archive/DATA_SOURCES.md` – full research, LDB/Darwin access, surfacing options.

**Implementation plan**:

- **Config**: `config/flood-watch.php` → `rail_stations` per region (e.g. Exeter, Dawlish, Plymouth, Bristol, Taunton)
- **Service**: `NationalRailService` – fetch departures/delays for key South West stations
- **Tool**: `GetRailDisruption` – LLM can call when region has rail
- **UI**: Dedicated "Rail Status" section (mirror Road Status pattern)

**Estimated effort**: ~2–3 days for rails v1 (agentic).

---

## Reference

| Doc | Purpose |
|-----|---------|
| `docs/archive/DATA_SOURCES.md` | Full research, National Rail, emergency services |
| `docs/plan.md` | Backlog, National Rail priority |
| `config/flood-watch.php` | Regions, correlation, future `rail_stations` structure |
