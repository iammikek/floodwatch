# Environment Agency Real-Time Flood-Monitoring API

Reference: [environment.data.gov.uk/flood-monitoring/doc/reference](https://environment.data.gov.uk/flood-monitoring/doc/reference).  
Base URL: `https://environment.data.gov.uk/flood-monitoring` (config: `flood-watch.environment_agency.base_url`).

**Licence**: Open Government Licence v3. No registration required.  
**Attribution**: “This uses Environment Agency flood and river level data from the real-time data API (Beta).”

---

## What we're using

Flood Watch currently uses **five** EA data flows:

1. **Flood warnings and alerts** — `GET /id/floods?lat=&long=&dist=`  
   `EnvironmentAgencyFloodService::getFloods()` → severity, message, area ID, times (timeRaised, timeMessageChanged, timeSeverityChanged).

2. **Flood area centroids** — `GET /id/floodAreas?lat=&long=&dist=&_limit=200`  
   Same service, internal: lat/lng per area for mapping and correlating warnings to location.

3. **Flood area polygons** — `GET /id/floodAreas/{areaId}/polygon` (one request per area, capped)  
   Same service: GeoJSON for map display; cached per area (`flood-watch.environment_agency.polygon_cache_hours`).

4. **Stations (metadata)** — `GET /id/stations?lat=&long=&dist=&_view=full`  
   `RiverLevelService::fetchStations()` → label, riverName, town, lat/lng, stationType (river gauge, pumping station, barrier, drain, coastal, groundwater), typicalRangeLow/High.

5. **Latest readings per station** — `GET /id/stations/{notation}/readings?latest&_sorted` (one per station, up to 15)  
   `RiverLevelService::fetchReadings()` → value, dateTime, unit; merged with stations and returned by `RiverLevelService::getLevels()` / `FloodWatchRiverLevelsController`.

Severity levels we use: 1 = Severe, 2 = Flood Warning, 3 = Flood Alert, 4 = Warning no longer in force.

---

## Full API summary (other data available)

The sections below describe the full EA API. **We only use the five flows listed above**; the rest are available for future use.

### Flood warnings

| What | API | Parameters |
|------|-----|------------|
| All flood warnings/alerts | `GET /id/floods` | `min-severity`, `county`, `lat`, `long`, `dist` |

- **county**: e.g. `?county=Somerset` or `?county=Somerset,Devon` (areas whose county name contains the string).
- **min-severity**: 1–4; e.g. `3` = only alerts and warnings in force.
- Per-warning fields: `description`, `eaAreaName`, `floodAreaID`, `isTidal`, `message`, `severity`, `severityLevel`, `timeMessageChanged`, `timeRaised`, `timeSeverityChanged`, nested `floodArea` (county, notation, polygon URI, riverOrSea).

### Flood areas

| What | API | Parameters |
|------|-----|------------|
| Single area | `GET /id/floodAreas/{area-code}` | — |
| List areas | `GET /id/floodAreas` | `lat`, `long`, `dist`, `_limit` (default 500) |
| Area polygon (GeoJSON) | `GET /id/floodAreas/{area-code}/polygon` | — |

Extra area fields (when fetching full area): `description`, `label`, `fwdCode`, `quickDialNumber` (Floodline quick-dial), `floodWatchArea`, `type` (FloodAlertArea / FloodWarningArea).

### Stations

| What | API | Filters |
|------|-----|---------|
| All stations | `GET /id/stations` | Many (see below) |
| One station | `GET /id/stations/{id}` | — |
| Measures at a station | `GET /id/stations/{id}/measures` | — |

**Stations list filters**: `parameter`, `parameterName`, `qualifier`, `label`, `town`, `catchmentName`, `riverName`, `stationReference`, `RLOIid`, `search` (text in label), `lat`, `long`, `dist`, `type` (e.g. SingleLevel, Coastal, Groundwater, Meteorological), `status` (Active, Closed, Suspended).

**Extra station fields** (with `_view=full`): `stageScale` (typicalRangeLow/High), `downstageScale`, `dateOpened`, `datumOffset`, and (from v0.7) `status`, `statusReason`, `statusDate`.

### Measures

| What | API | Filters |
|------|-----|---------|
| All measures | `GET /id/measures` | `parameter`, `parameterName`, `qualifier`, `stationReference`, `station` |
| One measure | `GET /id/measures/{id}` | — |

Useful to know which measures exist (e.g. level vs flow) before requesting readings.

### Readings (real-time and historic)

| What | API | Filters |
|------|-----|---------|
| All latest readings (bulk) | `GET /data/readings?latest` | `parameter`, `parameterName`, `qualifier`, `stationReference`, `station`, `_view=full`, `_sorted` |
| Readings by date range | `GET /data/readings` | `today`, `date=d`, `startdate` & `enddate` |
| Readings for one measure | `GET /id/measures/{id}/readings` | `latest`, `today`, `date`, `startdate`, `enddate`, `since=dt`, `_view=full`, `_sorted` |
| Readings for one station | `GET /id/stations/{id}/readings` | Same as above |

- **Bulk latest**: One call to `/data/readings?latest` returns the latest value for every measure (recommended every 15 mins instead of per-station calls).
- **Historic**: Use `since`, `date`, or `startdate`/`enddate` for time series (e.g. “rising level” or trends).

### Five-day flood risk forecast (EA)

| What | API | Notes |
|------|-----|--------|
| 3-day forecast (documented) | `GET /id/3dayforecast` | Cached by EA; common pattern for CDN |
| 3-day forecast image (per day) | `GET /id/3dayforecast/image/{day}` | — |

Flood Watch currently uses the **Flood Forecast Centre** 5-day outlook (separate config) for narrative forecast; the EA 3-day forecast is an alternative or supplement.

### Rainfall (API v0.8)

**Version 0.8** added access to **Rainfall** data. The reference doc mentions it but detailed endpoint paths are in the full reference (rainfall may be under a different path or dataset on the same platform). Worth checking the reference for `/rainfall` or similar.

---

## Possible Enhancements for Flood Watch

| Data / feature | Endpoint / approach | Use case |
|----------------|---------------------|----------|
| **County-filtered floods** | `GET /id/floods?county=Somerset,Devon` | Reduce payload when we know region. |
| **Flood area quickDialNumber** | From `GET /id/floodAreas/{id}` | Show “Floodline quick-dial for this area” in UI. |
| **Station status** | `status`, `statusReason`, `statusDate` on stations (v0.7) | Show “Station temporarily unavailable” or “Closed”. |
| **Flow (not just level)** | Stations/measures with `parameter=flow` | Flow rate for key rivers. |
| **Bulk latest readings** | `GET /data/readings?latest` | Single call for all stations; filter client-side or by stationReference. |
| **Historic readings** | `GET /id/stations/{id}/readings?startdate=&enddate=` or `since=` | “Level rising” / trend; sparklines. |
| **EA 3-day forecast** | `GET /id/3dayforecast` | Alternative or supplement to Flood Forecast Centre narrative. |
| **Rainfall** | Rainfall API (v0.8) | Local rainfall context. |
| **RLOIid** | Station field / filter | Align with “River Levels on the Internet” if we ever cross-reference. |

---

## General API Notes

- **Update frequency**: Warnings updated ~every 15 minutes. Level/flow data typically every 15 minutes; transfer to EA can be 1–2× daily and increases during high flood risk.
- **Caching**: EA may cache common patterns (e.g. `/id/floods`, `/id/3dayforecast`, `/data/readings?latest`). Using standard filters improves cache hit chances.
- **Redirects**: Clients should follow HTTP redirects.
- **Formats**: JSON default; many endpoints support `.csv`, `.ttl`, `.rdf`, `.html` via suffix or Accept header.
- **List modifiers**: `_limit`, `_offset`, `_view=full`, `_sorted` where documented.
- **No SLA**: Open data APIs are not guaranteed for safety-critical use; do not replace official channels (e.g. [gov.uk/check-if-youre-at-risk-of-flooding](https://www.gov.uk/check-if-youre-at-risk-of-flooding)).
