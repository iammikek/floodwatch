# Build: Prediction v1 — Historic EA Analogues (USP)

**Status:** Implemented in core repos; keep as design + rollout record  
**Ref:** `docs/ux-wireframes/prediction-contract.md`, `docs/plan.md`, `docs/DATA_LAKE_MIGRATION_PLAN.md`  
**Repos:** `flood-watch` (Laravel + cockpit), `flood-watch-data-lake` (engine + data)

---

## Why this exists

Predictions are the **USP**. Warnings, river levels, route check, and map layers are table stakes. The product promise is:

> Given mined EA hydrology for a corridor, say **what happens next**, **when**, and **how confident** we are — before formal flood warnings or road closures.

This document captured the gap between the original cockpit wording and the first live implementation. That gap has now been closed by the v1 rollout: the lake serves `historic_analogue_v1`, Laravel accepts `floodwatch.prediction.v1`, and cockpit mocks/docs have been aligned to the live contract.

**v1 goal:** ship a defensible **multi-gauge historic analogue matcher** on the A361 Muchelney corridor, with honest confidence, eval fixtures, and **prediction-first** cockpit layout.

---

## Current state

| Layer | What exists | Gap |
|-------|-------------|-----|
| **Data lake** | `GET /v1/predictions`, `predict_corridor()` in `api/services/predictions.py`, corridor registry in `api/config/corridors.py` | No true analogue search; needs 2–3 yr backfill per corridor gauge |
| **Laravel** | `CorridorPredictionService`, `GET /flood-watch/predictions`, session gate | Returns 503 when lake down; cockpit silently falls back to mock |
| **Cockpit** | `PredictionPanel.vue`, `fetchPrediction.js` | Prediction sits **below** risk/route blocks; mock fallback masks outages |
| **Contract** | `floodwatch.prediction.v1` | Keep mocks, Laravel proxy, and cockpit rendering pinned to the live v1 schema |

Primary measure (longest history): Gaw Bridge `52119-level-stage-i-15_min-mASD`.  
Corridor gauges: Gaw Bridge, Midelney, Westonzoyland PS, Langport Great Bow.

---

## v1 scope (in)

- **One corridor:** `a361-muchelney` (Somerset Levels / Parrett approach).
- **Engine:** `historic_analogue_v1` — multi-gauge window matching against mined EA hour series.
- **Schema:** `floodwatch.prediction.v1` (breaking bump; v0 kept for tests/migration window).
- **Data:** Minimum **24 months** hour aggregates per corridor gauge; target **36–60 months**.
- **UI:** Prediction panel **first** in cockpit main column; live-only in production (mock opt-in).
- **Eval:** Frozen golden windows (known high-water periods) with expected verdict band.

## v1 scope (out)

- Rainfall lag model, depth/LiDAR, PostGIS, RAG, fleet GPX, multi-corridor picker UI.
- Replacing Laravel LLM search flow (predictions remain operator-cockpit USP slice first).

---

## Algorithm: `historic_analogue_v1`

### Feature window (current state)

At prediction time `t0`, for each corridor gauge `g`:

1. Load hour-aggregated stage series `[t0 − W, t0]` where `W = 48h` (configurable).
2. Normalize each gauge to **percentile rank within its own history** (same window as v0, per measure, trailing 120d minimum).
3. Build feature vector per hour: `[pct_rank_g1, pct_rank_g2, …, pct_rank_gN]` (N = 4 gauges).
4. Add **aggregate slope** per gauge over last 6h (m/h).

Vector at `t0`: concatenation of last **12 hourly** multi-gauge percentile ranks + 4 slopes → fixed-length fingerprint.

### Analogue search (history)

1. Index all historical hours `t` in `[t0 − history_days, t0 − min_gap]` where `min_gap = 72h` (avoid trivial self-match).
2. For each candidate `t`, build the same fingerprint (requires complete data for all gauges; skip sparse windows).
3. Score similarity: **cosine similarity** on percentile-rank vectors (robust, scale-free). Optional tie-break: slope sign agreement.
4. Keep top **K = 20** analogues with similarity ≥ **0.85** (tune on eval set).

### Outcome labelling (what happened after each analogue)

For each analogue at time `t`, observe primary gauge (Gaw Bridge) over horizon **H = 24h**:

| Outcome | Rule (primary gauge) |
|---------|----------------------|
| `impact` | Level reaches ≥ p95 of that gauge's trailing history **or** rises ≥ 0.35 m from analogue-time level |
| `watch` | Reaches ≥ p90 or rises ≥ 0.20 m but not impact |
| `clear` | Neither |

Record **time-to-impact** = first hour outcome threshold crossed (null if clear).

### Verdict aggregation (now → forecast)

From top-K analogues weighted by similarity:

| Condition | Verdict | `timeToImpactHours` | Confidence base |
|-----------|---------|---------------------|-----------------|
| ≥ 60% analogues → `impact` | `at_risk` | weighted median TTI | `0.5 + 0.4 × (impact_rate)` |
| ≥ 40% → `impact` OR ≥ 50% → `watch` | `watch` | weighted median TTI if any impact/watch | `0.35 + 0.3 × (watch_or_impact_rate)` |
| else | `clear` | null | `0.5 + 0.2 × (clear_rate)` |

Cap confidence at 0.92 until eval skill is documented.  
**Summary text** template-driven from verdict + top analogue labels (e.g. “Similar to Jan 2014 rising Parrett pattern — 7 of 10 analogues reached disruptive levels within 12h.”).

### Drivers (honest)

Emit in `drivers[]`:

```json
{ "type": "historic_analogue", "ref": "2014-01-24T12:00:00Z", "label": "Jan 2014 Parrett response", "similarity": 0.89, "outcome": "impact", "timeToImpactHours": 8 }
{ "type": "gauge_trajectory", "ref": "52119-level-stage-i-15_min-mASD", "label": "Gaw Bridge · River Parrett", "signal": "rising_toward_high", "pct_rank": 88.2 }
{ "type": "analogue_consensus", "ref": "k20", "label": "20 matched windows", "impactRate": 0.7, "watchRate": 0.15 }
```

### Method block

```json
"method": {
  "name": "historic_analogue_v1",
  "inputs": ["ea_stage_history_hour", "corridor_gauge_set"],
  "parameters": { "windowHours": 48, "historyDays": 730, "topK": 20, "minSimilarity": 0.85 },
  "notes": "Matches current multi-gauge shape to past EA windows. Not rainfall-lag or depth. Confidence = analogue agreement, not ML calibration."
}
```

---

## Data requirements

### Measures (must have hour series)

| measure_id | Label | Role |
|------------|-------|------|
| `52119-level-stage-i-15_min-mASD` | Gaw Bridge | Primary + outcome |
| `52153-level-stage-i-15_min-mASD` | Midelney | Analogue fingerprint |
| `52245-level-stage-i-15_min-m` | Westonzoyland PS | Analogue fingerprint |
| `52230-level-stage-i-15_min-m` | Langport Great Bow | Analogue fingerprint |

### Backfill command (lake-worker)

```bash
FROM=2022-01 TO=2026-03 ./scripts/run-corridor-backfill.sh
# or: FROM=2022-01 TO=2026-03 make corridor-backfill
```

Lake repo docs: `flood-watch-data-lake/docs/prediction-corridor-backfill.md`

**Acceptance:** each measure has ≥ 24 monthly `data/raw/ea/readings/{measure_id}/{YYYY}-{MM}.ndjson.gz` files; prediction endpoint returns non-`no_data` for all four gauges.

### Curated eval fixtures (lake repo, no raw EA in public GitHub)

`tests/fixtures/prediction_eval/` — synthetic hour series reproducing shape of known events (Jan 2014, Feb 2020, etc.) for unit tests only.  
Operational validation: checklist doc with dates operators can replay against EA charts.

---

## API changes

### Data lake

| Endpoint | Change |
|----------|--------|
| `GET /v1/predictions?corridor=&history_days=` | Return `schema: floodwatch.prediction.v1` when `Accept` or `?schema=v1` (default v1 after cutover) |
| `GET /v1/predictions/corridors` | Unchanged |
| `docs/openapi-data-lake.yaml` | Document v1 response + method parameters |

Keep v0 code path behind `?schema=v0` for one release, then remove.

### Laravel

| Endpoint | Change |
|----------|--------|
| `GET /flood-watch/predictions` | Pass through v1; log schema mismatch |
| Config | `flood-watch.predictions.schema` default `v1` |

No DTO transformation — lake document is canonical.

### Cockpit

| File | Change |
|------|--------|
| `fetchPrediction.js` | Accept v0 **or** v1; **no silent mock** when `import.meta.env.PROD` (show error state) |
| `App.vue` | Move `<PredictionPanel>` above grid-2 risk/route; headline corridor copy derives from prediction when live |
| `PredictionPanel.vue` | Render `historic_analogue` drivers; show eval disclaimer from `method.notes` |
| Demo fixtures | Toggle only via “Demo fixtures” toolbar (already exists) |

---

## UI: prediction-first layout

**Before (today):**

```
Your risk | Route check
Corridor risk
Prediction panel
Map
River response
```

**After (v1):**

```
┌─ Prediction (USP) ─────────────────────────────┐
│ Verdict · TTI · confidence · top analogues     │
│ Sparklines · drivers · dispatch implication    │
└──────────────────────────────────────────────┘
Your risk | Route check          (supporting)
Corridor risk                    (live warnings — context)
Map + inspector
River response
```

Rules:

- Prediction **source dot green** only when `schema === v1` and lake response OK.
- If prediction unavailable: dedicated error panel — **do not** show mock in live mode.
- “Your risk” may echo prediction verdict one-liner when live.

---

## Eval & acceptance

### Golden scenarios (manual + automated)

| ID | Period | Expected verdict band | Notes |
|----|--------|----------------------|-------|
| `eval-2014-01` | Jan 2014 Parrett rise | `at_risk` or `watch` | Somerset Levels widely documented |
| `eval-2020-02` | Storm Dennis window | `at_risk` | Multi-gauge elevated |
| `eval-stable-summer` | Aug 2018 low flow | `clear` | No rising fingerprint |

Automated: inject series via `series_loader` in tests; assert verdict ∈ band and `drivers` contains ≥ 1 `historic_analogue`.

### Release checklist

- [ ] All four measures backfilled ≥ 24 months
- [ ] `GET /v1/predictions?corridor=a361-muchelney` returns v1 with ≥ 3 analogue drivers on rising fixture
- [ ] Confidence monotonic with impact rate (synthetic test)
- [ ] Cockpit shows prediction first; prod never silent-mocks
- [ ] OpenAPI updated; Laravel proxy test uses v1 fixture
- [ ] `method.notes` visible in UI (auditability)

---

## PR sequence

Build in order. Each PR should be reviewable alone.

| # | Repo | Branch suggestion | Deliverable |
|---|------|-------------------|-------------|
| **P1** | data-lake | `feature/prediction-v1-backfill-somerset` | Backfill script/docs for 4 corridor measures; CI smoke that files exist (private Bitbucket) |
| **P2** | data-lake | `feature/prediction-v1-analogue-engine` | `historic_analogue_v1` in `api/services/predictions.py`, unit tests, eval fixtures |
| **P3** | data-lake | `feature/prediction-v1-api-schema` | v1 schema field, OpenAPI, route tests in `tests/test_api_predictions.py` |
| **P4** | flood-watch | `feature/prediction-v1-proxy` | Accept v1 in `CorridorPredictionService` + fixture test; optional `schema` query param |
| **P5** | flood-watch | `feature/cockpit-prediction-first` | Layout reorder, prod no-mock policy, v1 driver rendering |
| **P6** | flood-watch | `feature/prediction-v1-eval-doc` | Operator replay checklist + update `prediction-contract.md` |

**Parallelism:** P1 can start immediately. P2 depends on P1 for real-data smoke (not for unit tests). P4–P6 can stub against lake fixture JSON until P3 merges.

---

## Implementation notes (lake)

Suggested module split:

```
api/services/predictions/
  __init__.py          # predict_corridor() dispatch by method version
  v0_trajectory.py     # move existing percentile/slope logic
  v1_analogues.py      # fingerprint, search, aggregate
  series.py            # shared load + hour aggregate helpers
```

Performance: precompute monthly percentile caches or rolling rank cache if search > 500ms. Target p95 < 2s on M1 laptop with 730d × 4 gauges.

---

## Implementation notes (cockpit)

```javascript
// fetchPrediction.js — prod behaviour sketch
if (!res.ok) {
  if (import.meta.env.PROD) {
    return { source: 'error', doc: null, error: `Prediction unavailable (${res.status})` };
  }
  return { source: 'mock', doc: mockPrediction(scenarioId), error: ... };
}
```

`App.vue`: render prediction error box when `predictionSource === 'error'`.

---

## Risks

| Risk | Mitigation |
|------|------------|
| Sparse EA history on Midelney / Langport | Require min 80% gauge coverage in window; degrade to 3-gauge fingerprint with note in `method.notes` |
| Analogue false confidence | Cap confidence; show `analogue_consensus` driver with counts; eval before marketing “skill” |
| Lake down in prod | Error panel + status notice; warnings/route still work |
| Schema drift mock vs live | Keep fixture, README, and cockpit docs pinned to `floodwatch.prediction.v1` / `historic_analogue_v1` |

---

## Success = USP credible

Operators can open the cockpit, see **live** prediction at the top, read **which past events** the current hydrograph resembles, and get a **time-bounded** corridor implication — backed by mined EA data, not LLM narrative or static demo JSON.

---

## Quick references

- Lake engine today: `flood-watch-data-lake/api/services/predictions.py`
- Corridor config: `flood-watch-data-lake/api/config/corridors.py`
- Cockpit panel: `flood-watch/resources/js/cockpit/components/PredictionPanel.vue`
- Contract doc: `flood-watch/docs/ux-wireframes/prediction-contract.md`
- Collector: `flood-watch-data-lake/scripts/run-collector.sh`, `AGENTS.md`
