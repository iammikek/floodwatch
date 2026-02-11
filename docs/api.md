# API

Flood Watch provides several HTTP endpoints for health monitoring and data retrieval.

---

## Health Check

- **Endpoint**: `GET /health`
- **Description**: Returns the status of the application and its external service integrations (OpenAI, Environment Agency, National Highways, etc.).

## Map Data

These endpoints are protected by session-based middleware and rate limiting. See [Architecture](architecture.md) for security details.

### Flood Polygons
- **Endpoint**: `GET /flood-watch/polygons`
- **Params**: `ids` (comma-separated flood area IDs)
- **Description**: Returns GeoJSON polygons for the specified flood areas.

### River Levels
- **Endpoint**: `GET /flood-watch/river-levels`
- **Params**: `lat`, `lng`, `radius_km`
- **Description**: Returns real-time river level data for stations near the specified coordinates.

---

## See also

- [Architecture](architecture.md)
- [Installation](installation.md)
