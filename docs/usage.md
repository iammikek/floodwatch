# Usage

How to use Flood Watch to monitor flood and road status in the South West.

---

## Postcode Search

Enter a South West postcode (e.g., BS1, TA10) to get a localized summary. 
The app restricts searches to the South West (Bristol, Somerset, Devon, Cornwall).

## Dashboard Overview

The dashboard provides a correlated view of:
- **AI Summary**: A concise narrative of the current status and recommended actions.
- **Flood Warnings**: Active alerts and warnings from the Environment Agency.
- **Road Status**: Planned and unplanned incidents from National Highways.
- **River Levels**: Real-time readings from nearby monitoring stations.
- **Forecast & Weather**: 5-day flood risk and weather outlook.

## Example Flow

1. **Enter Postcode**: User enters "TA10" (Langport area).
2. **Geocode**: App resolves TA10 to coordinates and identifies the region as Somerset.
3. **Correlate**: The LLM calls tools to fetch floods, road incidents, and river levels.
4. **Summary**: The assistant identifies if high river levels are likely to impact key routes like the A361.
5. **Dashboard**: User sees the summary plus a map of flood areas and a list of road closures.

---

## See also

- [Agents & LLM](agents-and-llm.md)
- [Architecture](ARCHITECTURE.md)
