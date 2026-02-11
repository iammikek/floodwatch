# Issue: Somerset incidents filtered out due to missing coordinates

## Source
PR #8 Review Comment: https://github.com/iammikek/floodwatch/pull/8#discussion_r2790180053

## Status
Unresolved

## Description
`filterIncidentsByProximity()` drops any incident that lacks coordinates (`return false` when lat/lng are null). SomersetCouncilRoadworksService incidents currently don't include lat/lng, so Somerset incidents will be silently excluded from GetHighwaysIncidents whenever proximity filtering is enabled (default 80km), undermining the Somerset merge.

## Location
- **File** (in PR #8): `app/Services/FloodWatchService.php`
- **Line** (in PR #8 diff): around 622 — see [PR #8](https://github.com/iammikek/floodwatch/pull/8) for the exact definition of `filterIncidentsByProximity()`

## Suggested Fix
Consider keeping incidents with missing coords (apply proximity only when coords exist), or ensure scraped incidents include coordinates before filtering.

```php
// If we don't have coordinates, keep the incident rather than dropping it,
// so sources without lat/lng (e.g. some council feeds) are not silently excluded.
return true;
```

## Impact
- Somerset Council roadworks/incidents are being silently excluded from the dashboard
- Users won't see important local road incidents
- Defeats the purpose of integrating Somerset Council data

## Labels
- bug
- priority: high
- component: road-incidents
