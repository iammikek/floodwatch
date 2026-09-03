# Prediction contract (prototype) — `floodwatch.prediction.v1`

Mock payloads: [`prediction-risk.json`](app/src/data/prediction-risk.json), [`prediction-stable.json`](app/src/data/prediction-stable.json). Live UI: Laravel home `/` (sources under `resources/js/cockpit/`; `/cockpit` redirects here).

**Product intent:** predictions are driven by **mined EA historical hydrology** (plus rainfall when available), not by live-warning popups alone.

## Lake surface (v1 implemented)

`GET /v1/predictions?corridor=a361-muchelney&history_days=120`  
`GET /v1/predictions/corridors`

Laravel same-origin proxy for the cockpit:

`GET /flood-watch/predictions?corridor=a361-muchelney`  
`GET /flood-watch/predictions/corridors`

v1 method: `historic_analogue_v1` — multi-gauge analogue matching over mined EA readings under `data/raw/ea/readings/`. Primary measure for A361 Muchelney slice: Gaw Bridge `52119-level-stage-i-15_min-mASD` (longest local history). Midelney stands in for Muchelney (no EA gauge with that name).

**v1 build spec (USP):** [`docs/build/11-prediction-v1-analogues.md`](../build/11-prediction-v1-analogues.md) — multi-gauge historic analogue matching, `floodwatch.prediction.v1`, prediction-first cockpit.

## Shape

| Field | Role |
|-------|------|
| `schema` | Version pin (`floodwatch.prediction.v1`) |
| `prediction.verdict` | `clear` \| `watch` \| `at_risk` \| `likely_impassable` |
| `prediction.timeToImpactHours` | Hours until corridor impact (null if clear) |
| `prediction.confidence` | 0–1 from analogue / model agreement |
| `drivers[]` | Why: gauge trajectories, historic analogues |
| `affectedAreas[]` | Areas/segments predicted at risk |
| `dispatch` | Operator implication + `safeToPass` |
| `method` | Model name + inputs (auditability) |
| `observables` | Supporting series for sparklines (not the prediction itself) |

## Honest labels

Current live contract is v1, but confidence is still an **analogue-agreement heuristic**, not a validated forecast skill score. Keep method notes visible in operator-facing UI.
