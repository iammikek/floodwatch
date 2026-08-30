# Prediction contract (prototype) — `floodwatch.prediction.v0`

Mock payloads: [`prediction-risk.json`](app/src/data/prediction-risk.json), [`prediction-stable.json`](app/src/data/prediction-stable.json). Live UI: Laravel route `/cockpit` (sources under `resources/js/cockpit/`).

**Product intent:** predictions are driven by **mined EA historical hydrology** (plus rainfall when available), not by live-warning popups alone.

## Lake surface (v0 implemented)

`GET /v1/predictions?corridor=a361-muchelney&history_days=120`  
`GET /v1/predictions/corridors`

Laravel same-origin proxy for the cockpit:

`GET /flood-watch/predictions?corridor=a361-muchelney`  
`GET /flood-watch/predictions/corridors`

v0 method: `historic_stage_trajectory_v0` — station-relative percentiles + recent slope from mined EA readings under `data/raw/ea/readings/`. Primary measure for A361 Muchelney slice: Gaw Bridge `52119-level-stage-i-15_min-mASD` (longest local history). Midelney stands in for Muchelney (no EA gauge with that name).

## Shape

| Field | Role |
|-------|------|
| `schema` | Version pin (`floodwatch.prediction.v0`) |
| `prediction.verdict` | `clear` \| `watch` \| `at_risk` \| `likely_impassable` |
| `prediction.timeToImpactHours` | Hours until corridor impact (null if clear) |
| `prediction.confidence` | 0–1 from analogue / model agreement |
| `drivers[]` | Why: gauge trajectories, historic analogues |
| `affectedAreas[]` | Areas/segments predicted at risk |
| `dispatch` | Operator implication + `safeToPass` |
| `method` | Model name + inputs (auditability) |
| `observables` | Supporting series for sparklines (not the prediction itself) |

## Honest labels

Until a real analogue/lag model is wired to lake history, UI must show method notes as **mock / illustrative**. Do not present confidence as validated skill.
