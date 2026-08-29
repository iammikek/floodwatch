# Prediction contract (prototype) — `floodwatch.prediction.v0`

Mock payloads: [`prediction-risk.json`](app/src/data/prediction-risk.json), [`prediction-stable.json`](app/src/data/prediction-stable.json).

**Product intent:** predictions are driven by **mined EA historical hydrology** (plus rainfall when available), not by live-warning popups alone.

## Future lake surface (proposed)

`GET /v1/predictions?corridor=&region=&as_of=`  
or a `prediction` object inside `GET /v1/retrieve-context`.

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
