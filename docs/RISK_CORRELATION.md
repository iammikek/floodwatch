# Risk Correlation Service

`RiskCorrelationService` produces a deterministic assessment that correlates flood warnings, road incidents, and river levels. It powers the LLM's GetCorrelationSummary tool and can be used by other consumers (e.g. Route Check).

**Location**: `app/Services/RiskCorrelationService.php`  
**Output**: `App\DTOs\RiskAssessment`  
**Config**: `config/flood-watch.php` → `correlation`

---

## Purpose

The service links three data sources in a structured way:

1. **Flood–road cross-references** – When a flood area is linked to a road in config, it checks whether that road has an incident. Example: "North Moor flooded" ↔ "A361" → "A361 has incident" or "No incident yet".
2. **Predictive warnings** – Rules that trigger when river levels or flood patterns match conditions. Example: "River Parrett elevated" → "Muchelney may be cut off".
3. **Key routes** – Region-specific roads to monitor (A361, M5 J23–J25, etc.).

This logic is **deterministic** and **testable** – no LLM involved. The LLM receives the assessment as context and turns it into natural language.

---

## API

```php
public function correlate(
    array $floods,        // From EnvironmentAgencyFloodService
    array $incidents,     // From NationalHighwaysService
    array $riverLevels,   // From RiverLevelService (station, river, levelStatus)
    ?string $region = null  // 'somerset', 'bristol', 'devon', 'cornwall'
): RiskAssessment
```

---

## RiskAssessment Output

| Field | Description |
|-------|-------------|
| `severeFloods` | Floods with `severityLevel === 1` (Danger to Life) |
| `floodWarnings` | Floods with severity ≤ 2 (Alert or Warning) |
| `roadIncidents` | Incidents passed through (unchanged) |
| `crossReferences` | Flood area ↔ road pairs with `hasIncident` |
| `predictiveWarnings` | Messages from river/flood rules |
| `keyRoutes` | Roads to monitor for the region |

`RiskAssessment` has `toPromptContext()` to render this as markdown for the LLM.

---

## Cross-References (Flood–Road Pairs)

**Config**: `correlation.{region}.flood_area_road_pairs`  
**Format**: `[['Flood Area Name', 'Road'], ...]`

For each pair, the service checks:
1. Is there a flood matching the area (substring match on `description`)?
2. Does any incident mention that road?

**Example** (Somerset):
- `['North Moor', 'A361']` – If North Moor has a flood warning and A361 has an incident → cross-reference with "Road incident reported".
- `['Sedgemoor', 'A361']` – Same for King's Sedgemoor.

Used to surface: "North Moor flood ↔ A361 (incident reported)" or "No incident on road yet".

---

## Predictive Rules

**Config**: `correlation.{region}.predictive_rules`  
**Types**: River-based, flood-based

### River-based rules

When a river level matches a pattern and trigger level:

```php
[
    'river_pattern' => 'parrett',
    'trigger_level' => 'elevated',
    'warning' => 'Muchelney may be cut off when River Parrett is elevated. Check route before travelling.',
]
```

- `river_pattern` – Matched against `river` (case-insensitive).
- `trigger_level` – `elevated`, `expected`, or `low` from `RiverLevelService`.
- If any river level has `levelStatus === trigger_level` and river name contains pattern → add warning.

### Flood-based rules

When a flood warning matches a pattern and severity:

```php
[
    'flood_pattern' => 'langport',
    'trigger_severity_max' => 2,
    'warning' => 'Muchelney may be cut off when Langport has flood warnings. Check route before travelling.',
]
```

- `flood_pattern` – Matched against flood `description`.
- `trigger_severity_max` – Max severity (1–4) that triggers the rule.
- If any flood matches and `severityLevel <= trigger_severity_max` → add warning.

---

## Key Routes

**Config**: `correlation.{region}.key_routes`  
**Format**: `['A361', 'A372', 'M5 J23', ...]`

List of roads to highlight for the region. Passed to the LLM so it can focus on these in its summary.

---

## Integration

### FloodWatchService

When the LLM calls **GetCorrelationSummary**, `FloodWatchService` runs:

```php
$assessment = $this->correlationService->correlate(
    $floods, $incidents, $riverLevels, $region
);
```

The tool returns `$assessment->toPromptContext()` so the LLM can refer to cross-references and predictive warnings in its response.

### Route Check (future)

`RouteCheckService` does not call `RiskCorrelationService` today. Build 09 (Smarter Verdict) plans to call it when the route is in Somerset and passes near Muchelney, to add predictive warnings to the route summary.

---

## Adding a New Region

1. Add region to `config/flood-watch.regions`.
2. Add `correlation.{region}` with:
   - `flood_area_road_pairs` – Flood areas and their linked roads.
   - `predictive_rules` – River/flood rules (optional).
   - `key_routes` – Roads to monitor.

---

## Tests

`tests/Feature/Services/RiskCorrelationServiceTest.php` covers:
- Cross-references (flood + incident, flood without incident).
- River predictive rules (Parrett elevated → Muchelney).
- Flood predictive rules (Langport flood → Muchelney).
- Key routes per region.
- Empty inputs.

---

## Reference

- **Config**: `config/flood-watch.php` → `correlation`
- **LLM flow**: `docs/LLM_DATA_FLOW.md`
- **Build 09**: `docs/build/09-smarter-route-verdict.md` (Route Check integration)
