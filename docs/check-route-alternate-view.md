# Check route alternate view (deferred)

Status: **deferred** — place monitoring is the only cockpit surface for now.

## Intent

A later alternate view for operators who need From→To corridor dispatch:

- Route check form, route geometry on map
- Corridor prediction tied to path / dispatch implication
- Recent routes panel

## Hook in code

Flood Watch cockpit keeps route code behind `SHOW_ROUTE_VIEW` in
`resources/js/cockpit/lib/cockpitFlags.js` (default `false`).

Enable that flag and restore route chrome when this alternate view is prioritised.
Do not reintroduce route UI into the default place surface.
