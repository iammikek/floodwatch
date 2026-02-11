---
title: "Somerset incidents filtered out due to missing coordinates"
labels: bug, priority:medium
---

## Description

`RoadIncidentOrchestrator::filterIncidentsByProximity()` drops any incident that lacks coordinates (`return false` when lat/lng are null). Somerset Council incidents currently don't include lat/lng, so they are silently excluded from `GetHighwaysIncidents` whenever proximity filtering is enabled (default 80km).

## Location

- **File**: `app/Roads/Services/RoadIncidentOrchestrator.php`
- **Method**: `filterIncidentsByProximity()`

## Current Behavior

```php
if ($lat === null || $lng === null) {
    return false;  // Drops incident
}
```

## Expected Behavior

Incidents without coordinates should be kept (proximity filter only applied when coords exist):

```php
if ($lat === null || $lng === null) {
    return true;  // Keep incident, can't filter by proximity
}
```

## Impact

- Somerset Council roadworks/incidents silently excluded from dashboard
- Users don't see important local road incidents
- Defeats purpose of Somerset Council data integration

## Origin

Originally tracked in PR #8 review comments.
