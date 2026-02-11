# Flood Watch – Performance & Scaling

Notes on performance bottlenecks, limits, and scaling options for production.

---

## 1. OSRM (Route Check)

**Current**: Public demo server `router.project-osrm.org`.

| Item | Limit |
|------|-------|
| Rate limit | 1 request per second |
| Use policy | Reasonable, non-commercial |
| Uptime | No guarantees |

**When to scale**: If route check traffic exceeds ~1 req/sec or usage is commercial, self-host OSRM.

### Self-hosting OSRM (Docker)

OSRM can be self-hosted in Docker. Pipeline: download OSM extract → pre-process → run routing server.

**1. Download OSM data** (Geofabrik):

```
# South West only (Somerset, Devon, Cornwall, Bristol)
wget http://download.geofabrik.de/europe/great-britain/england/somerset-latest.osm.pbf
# Or combine regions; England ~1.2 GB covers all
```

**2. Pre-process** (one-time or scheduled):

```bash
docker run -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend \
  osrm-extract -p /opt/car.lua /data/somerset-latest.osm.pbf

docker run -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend \
  osrm-partition /data/somerset-latest.osrm

docker run -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend \
  osrm-customize /data/somerset-latest.osrm
```

**3. Run routing server**:

```bash
docker run -t -i -p 5000:5000 -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend \
  osrm-routed --algorithm mld /data/somerset-latest.osrm
```

**4. Configure app**: Set `FLOOD_WATCH_OSRM_URL=http://osrm:5000` (or host) in `.env`.

**Data choice**: For South West, Somerset + Devon + Cornwall + Bristol (~135 MB combined) or England (1.2 GB) for full coverage. Pre-processing: ~15–60 min depending on region size.

**Updates**: OSM data changes; re-download and re-process periodically (e.g. weekly).

**Ref**: [OSRM Docker quick start](https://github.com/Project-OSRM/osrm-backend#quick-start), [Demo server policy](https://github.com/Project-OSRM/osrm-backend/wiki/Demo-server).

---

## 2. LLM & Cache

See `docs/CONSIDERATIONS.md` §4 (LLM costs) and §5 (postcode granularity for cache). Cache TTL, geographic caching, and sector-level cache keys reduce API and LLM calls.

---

## 3. Other Services

| Service | Notes |
|--------|-------|
| Environment Agency | No stated rate limit; circuit breaker in place |
| National Highways | API key required; circuit breaker in place |
| Nominatim | Rate limit: 1 req/sec for heavy use; consider caching |
| postcodes.io | Free; no strict limit documented |

---

## Related

- `docs/CONSIDERATIONS.md` – Risks, API dependency, LLM cost, cache tuning
- `docs/deployment.md` – Load testing, warm cache, pre-deploy checklist
- `docs/build/04-route-check.md` – OSRM config (`FLOOD_WATCH_OSRM_URL`)
