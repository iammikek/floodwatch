# Cockpit use cases

Primary product modes are switched in the **top bar**: **Live | History | Transport**.
Map View presets (Place / Dispatch / Hydrology / Minimal) are not the way to choose dashboard type.

Composition lives in [`resources/js/cockpit/lib/cockpitUseCases.js`](../resources/js/cockpit/lib/cockpitUseCases.js).

## Modes

### Live — place monitor

**Job:** Watch this place now — gauges, warnings, corridor prediction for the live window.

**In:** Place focus / bookmarks, live map (gauges + warnings + planning FZ), Prediction (live, including Dispatch), Your risk / Place outlook, Corridor risk + Flood exposure, River response, Inspector.

**Out:** Storm catalogue as primary chrome, event inundation outline, LiDAR volume, Current route (route view is Transport).

### History — storm replay

**Job:** Analyse a past event at this place — hindcast prediction, event spatial context, volume.

**In:** Place focus, place-history picker, Return to live (via Live), map with event outline (+ optional planning FZ as reference), Prediction **as-of** (no Dispatch block), **Event volume** panel, Corridor risk (replay framing), Inspector on event layers.

**Out:** Flood exposure, Current route, River response, Your risk / Place outlook from live signals, live warning/gauge markers, Dispatch implication / safe-to-pass.

### Transport — check route

**Job:** From → To risk and geometry.

**In:** Route form, route geometry, route-scoped incidents / Current route, prediction with Dispatch where useful, Flood exposure.

**Out:** Place-history storm picker as primary; event LiDAR volume.

## Panel matrix

| Panel / layer | Live | History | Transport |
|---------------|------|---------|-----------|
| Place focus / bookmarks | ✓ | ✓ | ✓ |
| Place history / storms | | ✓ | |
| Prediction (live + Dispatch) | ✓ | | ✓ |
| Prediction (as_of, no Dispatch) | | ✓ | |
| Event volume (LiDAR bathtub) | | ✓ | |
| Event volume compare table | | ✓ | |
| Your risk / Place outlook | ✓ | | Your risk |
| Corridor risk | ✓ | ✓ | ✓ |
| Flood exposure | ✓ | | ✓ |
| Current route | | | ✓ |
| River response | ✓ | | |
| Map: gauges / warnings | ✓ | | warnings |
| Map: incidents + route | | | ✓ |
| Map: planning FZ | ✓ | clipped / reference | ✓ |
| Map: event outline | | ✓ | |
| Map: A361 depth strip | | ✓ | |
| Route check chrome | | | ✓ |

## Place binding

Prediction, storm catalogue, event extents, and volume are bound to the **active place** (currently `a361-muchelney`). Map pan is exploration only and does not change corridor prediction.

## Accuracy (History only)

Honesty labels → curated extents → LiDAR DTM → volume v0. Details in the data lake repo: `docs/accuracy-ladder.md` and `docs/place-lidar-volume.md`.
