# Flood Watch – Considerations & Risks

Review findings and recommendations for production readiness.

---

## 1. Dependency on Third-Party APIs

**Risk**: Environment Agency and National Highways must remain available. No documented fallback if APIs go down.

**Current state**:
- Circuit breaker: opens after N failures; returns empty until cooldown
- Retry: configurable per service
- Graceful degradation: app returns partial summary when one API fails (see ACCEPTANCE_CRITERIA)

**Recommendation**: Document cached-data fallback explicitly. When circuit is open, serve last-known cached result for that area (if available) instead of empty. Add to deployment runbook.

---

## 2. Regional Scope Constraint

**Risk**: Hard-coded to South West (Somerset, Bristol, Devon, Cornwall). National Highways coordinates (A361, A372, M5) and `incident_road_coordinates` are region-specific.

**Current state**:
- `config/flood-watch.regions` – postcode areas → region
- `config/flood-watch.correlation` – flood_area_road_pairs, key_routes per region
- `config/flood-watch.incident_road_coordinates` – fallback map coordinates

**Recommendation**: Document expansion steps:
1. Add region to `flood-watch.regions` (areas, prompt)
2. Add correlation rules to `flood-watch.correlation.{region}`
3. Add key routes to `incident_allowed_roads` if new
4. Add road coordinates to `incident_road_coordinates` for map fallback

---

## 3. API Key Management

**Risk**: National Highways API key is optional in code. Returns empty incidents if missing; no deployment-time check.

**Current state**:
- `/health` shows National Highways as "skipped" when key not configured
- No pre-deploy verification

**Recommendation**:
- Add deployment checklist: verify `NATIONAL_HIGHWAYS_API_KEY` is set for production
- Consider failing health check in production if key is missing (or explicit "degraded" status)
- Document in DEPLOYMENT.md under "Optional" → clarify when road data is required

---

## 4. LLM Costs

**Risk**: Every cache miss calls OpenAI. 15-minute TTL helps, but many unique postcodes = many cache misses. Costs can add up.

**Current state**:
- Guest rate limit: 1 search per 15 min
- Cache TTL: configurable (`FLOOD_WATCH_CACHE_TTL_MINUTES`)
- Admin dashboard (planned): LLM cost tracking, budget alert

**Recommendation**:
- Monitor API costs via admin dashboard when implemented
- Consider: increase TTL for low-risk periods; geo-grid cache (same cell = same key)
- Rate limiting: guests already limited; consider per-user caps for registered

**Time estimates in LLM output**: If the app should surface specific time windows (e.g. "check again within 4 hours"), decide whether to bake this into the system prompt or leave it LLM-generated. Currently no explicit instruction; LLM may infer from data.

---

## 5. Postcode Granularity for Cache

**Opportunity**: Cache key currently uses full user input (e.g. TA10 0DP). Same sector = same flood/road data, so sector-level is usually enough and cheaper.

**Recommendation**: Use postcode sector for cache key instead of full postcode:
- Full postcode `TA10 0DP` → cache key `TA10 0` (outcode + first digit of incode)
- Full postcode `BS3 2AB` → cache key `BS3 2`
- Outcode-only `TA10` → use as-is
- Place names (Langport, Bristol) → use rounded lat/long grid cell (e.g. 2 decimal places ≈ 1 km)

**Benefit**: Fewer unique cache keys = more cache hits = fewer API and LLM calls = lower cost. Flood and road data are area-based; sector-level granularity is sufficient.

---

## 6. Test Coverage Visibility

**Risk**: TDD mentioned but critical-path coverage not visible. Unclear if tool calling, caching, correlation are well-tested.

**Current state**:
- Pest + PHPUnit; tests in `tests/Feature/`, `tests/Unit/`
- `FloodWatchServiceTest`, `FloodWatchPromptBuilderTest`, `RiskCorrelationServiceTest`, etc.
- CI runs on push/PR

**Recommendation**:
- Run `sail test --coverage` periodically; add coverage report to CI if feasible
- Ensure critical paths covered: `FloodWatchService` (tool calling, cache hit/miss), `RiskCorrelationService`, `LocationResolver`, circuit breaker behaviour
- Document test layout in ARCHITECTURE or agents.md
