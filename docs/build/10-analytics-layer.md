# Build: Analytics Layer

Lightweight analytics to track operational health, user behavior, cache effectiveness, and cost. Privacy‑preserving and focused on actionable insights.

---

## Acceptance Criteria

- [ ] Emit analytics events for searches, route checks, scheduler runs, upstream API calls, and LLM requests
- [ ] Store events (Redis trends + database table) with 90-day retention
- [ ] Admin dashboard shows search volume, route verdict breakdown, cache hit ratio, upstream health, and LLM budget
- [ ] Alerts configured for cache ratio drop, upstream error spikes, LLM budget ≥ 80%, missed scheduler run
- [ ] Tests cover event emitters and daily aggregation job

---

## Goals

- Operational visibility: latency and error rates per upstream, scheduler reliability
- User behavior: searches per hour/day, route verdict distribution, region mix
- Cost tracking: LLM tokens and budget status, iterations/tool calls
- Cache efficacy: hit ratio, warmed cache utilization

---

## Metrics

- User activity: searches/day, guest vs registered; region distribution; route verdicts
- Cache/jobs: hit ratio per store; warmed cache usage; scheduler last run/duration
- API health: latency and error rate per provider (EA, NH, Forecast, Weather)
- LLM: tokens in/out, iterations, tool calls; error_key distribution; budget remaining
- Correlation: predictive warnings triggered; key routes per region

---

## Instrumentation

- Event emitters at key points:
  - Search completed: location/region, counts
  - Route check completed: verdict, distance/time, flood/incident counts
  - Warm‑cache run: location, outcome
  - Upstream API call: provider, latency, status, error
  - LLM request: tokens, iterations, tool calls, error_key
- HTTP timing: client middleware logs duration/status for upstream requests
- Cache counters: increment hit/miss around cacheGet/cachePut

---

## Storage & Aggregation

- Realtime: Redis sorted sets for recent trends (existing FloodWatchTrendService)
- Historical: `analytics_events` table (id, type, user_id?, session_id?, region?, properties JSON, occurred_at)
- Aggregation: nightly job to roll up daily summaries (search volume, verdicts, cache hit rate, API errors)
- Retention: prune events after 90 days (configurable)

---

## Dashboard & Alerts

- Admin dashboard cards:
  - Searches today; route verdict breakdown
  - Cache hit ratio; warmed cache usage
  - Upstream latency/availability; error counts
  - LLM tokens today/month; budget bar with threshold coloring
- Alerts:
  - Cache hit ratio below threshold
  - EA/NH error rate spikes
  - LLM budget ≥ 80%
  - Scheduler missed last run

---

## Privacy

- Pseudonymize analytics location (postcode sector or region only)
- No IP storage; session_id allowed for guests
- Do not store raw user text; record intent type or classified category
- Enforce retention and pruning

---

## Implementation Order

1. Event emitters (search, route check, upstream, LLM, warm‑cache)
2. HTTP timing middleware and cache hit/miss counters
3. `analytics_events` table + nightly aggregation job
4. Admin dashboard panels (charts and tables)
5. Alerts (email or log-based thresholds)

---

## Tests

- Unit: event emitter payload shape, HTTP timing middleware, cache counters
- Feature: aggregation job produces daily summaries
- Feature: admin dashboard shows metrics from seeds/fakes

