# API Reference

Flood Watch provides several public HTTP endpoints for health monitoring and front‑end data (polygons, river levels). All endpoints return JSON.

---

## Public Endpoints

### Health Check

- **URL**: `GET /health`
- **Purpose**: Monitor the status of external API dependencies and local cache.
- **Authentication**: None.
- **Response**:
  ```json
  {
    "status": "healthy",
    "checks": {
      "environment_agency": { "status": "ok", "message": null },
      "flood_forecast": { "status": "ok", "message": null },
      "weather": { "status": "ok", "message": null },
      "national_highways": { "status": "ok", "message": null },
      "cache": { "status": "ok", "message": null }
    }
  }
  ```
- **Status Codes**: 200 (Healthy), 503 (Degraded/Unhealthy).

### Flood Area Polygons

- **URL**: `GET /flood-watch/polygons`
- **Params**: `ids` (comma-separated string of flood area IDs, max 20).
- **Purpose**: Fetch GeoJSON geometry for flood areas to render on the map.
- **Protection**: Session-locked (requires loading the dashboard first) and rate-limited.
- **Response**:
  ```json
  {
    "areaId1": { "type": "FeatureCollection", "features": [...] },
    "areaId2": { ... }
  }
  ```

### River Level Stations

- **URL**: `GET /flood-watch/river-levels`
- **Params**: `lat`, `lng` (required), `radius` (optional, default 15, max 50).
- **Purpose**: Fetch real-time river level monitoring stations for a given area.
- **Protection**: Session-locked and rate-limited.
- **Response**: Array of station objects including `name`, `river`, `town`, `level`, `unit`, `levelStatus`, and `dateTime`.

---

## Security & Protection

- **Session Lock**: The `/flood-watch/*` endpoints are protected by `EnsureFloodWatchSession` middleware. They only respond to same‑origin requests from a browser session that has already loaded the dashboard.
- **Rate Limiting**:
  - `throttle:flood-watch-api`: 30 requests per minute per IP (configured in `AppServiceProvider`).
  - `ThrottleFloodWatch` (Global): 60 requests per minute per IP.
- **CORS**: Restricted to same‑origin by default.

---

## See also
- [Architecture](architecture.md) — System design and data flow.
- [Usage](usage.md) — User-facing application guide.
- [Installation](installation.md) — Setup and environment variables.
