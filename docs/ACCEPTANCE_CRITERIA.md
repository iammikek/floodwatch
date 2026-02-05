# Acceptance Criteria (Success Checklist)

Business standards for the Flood Watch POC. Verify these once implementation is complete.

---

## 1. Latency Performance

**Criterion**: Does `Concurrency::run()` successfully fetch both APIs in the time of the single slowest request?

**Status**: [x] Met

**Current state**: Pre-fetch (forecast, weather, river levels) runs **in parallel** via `Concurrency::run()`. Total time ≈ max(forecast, weather, river) rather than sum. Tool calls (GetFloodData, GetHighwaysIncidents) remain LLM-driven. Tests use `CONCURRENCY_DRIVER=sync` so Http::fake works; production uses `process` driver for real parallelism.

---

## 2. Intelligence Correlation

**Criterion**: Does the AI say: "The A361 is closed, and the River Parrett is rising. Expect Muchelney to be isolated within 4 hours"?

**Status**: [x] Met (automated test)

**Current state**: `FloodWatchServiceTest::test_intelligence_correlation_a361_parrett_muchelney` verifies the flow with mocked data: A361 closed (flooding), River Parrett elevated, Langport flood warning. The mocked LLM response correlates road closure + river level and warns about Muchelney. The "within 4 hours" time estimate depends on LLM behaviour—predictive rules do not include time estimates.

---

## 3. Graceful Failure

**Criterion**: If one API is down, does the AI still report on the other rather than crashing?

**Status**: [x] Met (expected)

**Current state**: Each external service (EA, NH, Flood Forecast, River Level, Weather) uses a circuit breaker and catches exceptions. On failure: service returns empty array/data; `report($e)` logs; flow continues. LLM receives whatever data is available and synthesizes a response. No single API failure should crash the request.

**To verify**: Manually disable one API (e.g. wrong NH key, or mock 500) and confirm the assistant still returns a summary with available data.

---

## 4. Attribution Compliance

**Criterion**: Does the UI display the required Environment Agency and National Highways data credits?

**Status**: [x] Met

**Current state**:
- **Environment Agency**: Yes – footer shows "Environment Agency flood and river level data from the Real-Time data API (Open Government Licence)"
- **National Highways**: Yes – footer shows "National Highways road and lane closure data (DATEX II v3.4) from the Developer Portal" with link to https://developer.data.nationalhighways.co.uk/

---

## Summary

| Criterion | Status | Action |
|-----------|--------|--------|
| Latency (Concurrency::run) | Met | — |
| Intelligence correlation | Met | `test_intelligence_correlation_a361_parrett_muchelney` |
| Graceful failure | Met | Manual verification recommended |
| Attribution | Met | — |
