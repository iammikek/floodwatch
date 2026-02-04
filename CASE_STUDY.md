# Flood Watch: Case Study

A technical case study explaining how Flood Watch works, what data sources it polls, and what results it presents to users.

---

## Overview

Flood Watch is a **Single Source of Truth** for flood and road viability in the Somerset Levels (Sedgemoor and South Somerset). It correlates Environment Agency flood warnings with National Highways road status, then uses an LLM (OpenAI) to synthesize a human-readable summary. The result is a dashboard that helps residents and emergency coordinators understand both flood risk and road accessibility in one place.

---

## How It Works

### Architecture

```
User Request → Livewire Dashboard → Flood Watch Service → OpenAI (with tools)
                                        ↓
                              Cache (Redis) check
                                        ↓
                              [Cache miss] → LLM decides to call tools
                                        ↓
                              GetFloodData + GetHighwaysIncidents (parallel)
                                        ↓
                              LLM synthesizes response → Cache → Return to user
```

### User Flow

1. **User enters postcode** (optional) and clicks **Check status**
2. **Flood Watch Service** receives the request (e.g. "Check flood and road status for postcode TA10 0 in the South West")
3. **Cache check**: If the same query was made within the last 15 minutes, cached results are returned immediately (no API calls)
4. **Cache miss**: The LLM (OpenAI GPT) is invoked with two tools:
   - **GetFloodData** – fetches flood warnings from the Environment Agency
   - **GetHighwaysIncidents** – fetches road incidents from National Highways
5. **LLM decides** which tools to call (typically both for a status check)
6. **Tools execute** – HTTP requests are made to the external APIs
7. **LLM synthesizes** a response using the tool results, following the Somerset Emergency Assistant system prompt
8. **Result is cached** in Redis for 15 minutes
9. **Dashboard displays** the response in three sections: Flood warnings, Road status, and Summary

### Key Components

| Component | Purpose |
|-----------|---------|
| `FloodWatchDashboard` (Livewire) | UI: postcode input, Check status button, loading spinner, results display |
| `FloodWatchService` | Orchestrates LLM chat with tool calling; caches results and returns floods, incidents, and summary |
| `EnvironmentAgencyFloodService` | Fetches flood warnings from the EA API |
| `NationalHighwaysService` | Fetches road and lane closures from the NH API |

---

## What We Poll

### 1. Environment Agency Flood Monitoring API

**Endpoint**: `https://environment.data.gov.uk/flood-monitoring/id/floods`

**Method**: GET (no API key required)

**Parameters**:
- `lat` – Latitude (default: 51.0358 for Langport)
- `long` – Longitude (default: -2.8318 for Langport)
- `dist` – Search radius in km (default: 15)

**Response**: JSON with an `items` array containing flood warnings. Each item includes:
- `description` – e.g. "River Parrett at Langport"
- `severity` – e.g. "Flood Warning", "Flood Alert"
- `severityLevel` – 1–4 (1 = most severe)
- `message` – Detailed advice
- `floodAreaID` – Identifier

**Coverage**: River Parrett, River Tone, Langport, Muchelney, Burrowbridge, and surrounding areas within the radius.

**Polling behaviour**: On-demand (when the user clicks Check status). Not background polling or scheduled.

---

### 2. National Highways API (DATEX II)

**Endpoint**: `https://api.data.nationalhighways.co.uk/road-lane-closures/v2/planned`

**Method**: GET (requires API key)

**Headers**:
- `Ocp-Apim-Subscription-Key` – API key from the National Highways Developer Portal

**Response**: DATEX II–style JSON with closure and incident data. We parse:
- `road` / `roadName` / `location`
- `status` / `closureStatus`
- `incidentType` / `type`
- `delayTime` / `delay`

**Coverage**: Somerset Levels routes – A361, A372, M5 J23–J25.

**Polling behaviour**: On-demand (when the LLM calls the GetHighwaysIncidents tool). Not background polling.

---

## What Results We Show

### 1. Flood Warnings

| Field | Meaning |
|-------|---------|
| Description | Flood area or affected location |
| Severity | Flood Alert, Flood Warning, Severe Flood Warning |
| Message | EA advice and guidance |

Displayed when there are active flood warnings in the area.

### 2. Road Status

| Field | Meaning |
|-------|---------|
| Road | Road identifier (e.g. A361, A372) |
| Status | Closure or incident status |
| Incident Type | e.g. flooding, accident |
| Delay | Estimated delay time |

Displayed when there are road incidents or closures from National Highways.

### 3. Summary

An LLM-generated summary that:
- Correlates flood and road data (e.g. “Flood Warning for River Parrett. A361 closed due to flooding.”)
- Includes a **Current Status** section
- Includes **Action Steps** as bullet points
- Only reports data present in the API responses (no hallucinations)

### 4. Last Checked

Timestamp shown when the data was last fetched (e.g. "Last checked: 4 Feb 2025, 2:30 pm").

---

## Caching

| Aspect | Detail |
|--------|--------|
| Store | Redis (`flood-watch` store) |
| TTL | 15 minutes (configurable via `FLOOD_WATCH_CACHE_TTL_MINUTES`) |
| Key | Hash of postcode or user message |
| Effect | Same query within 15 minutes returns cached data without calling the EA, NH, or OpenAI APIs |

---

## System Prompt (Somerset Emergency Assistant)

The LLM is instructed to:

1. **Correlate data** – If flood data shows North Moor or King's Sedgemoor, cross-reference with A361 at East Lyng
2. **Be context-aware** – Muchelney is prone to being cut off; if River Parrett is rising, warn about Muchelney access even if the Highways API has not updated
3. **Prioritise** – Danger to Life → road closures → general flood alerts
4. **Format output** – Current Status and Action Steps
5. **Avoid hallucination** – Only report data present in the tool results

---

## API Keys Required

| Environment Variable | Purpose |
|---------------------|---------|
| `OPENAI_API_KEY` | Required for the Flood Watch LLM |
| `NATIONAL_HIGHWAYS_API_KEY` | Required for road incident data (optional; no key returns empty incidents) |

---

## Data Attribution

- **Environment Agency** – Flood monitoring data
- **National Highways** – Road and lane closure data (DATEX II)

---

## Summary

Flood Watch is an **on-demand** system: it does not poll APIs in the background. When a user clicks "Check status", the Flood Watch Service uses LLM tool calling to fetch flood and road data from the Environment Agency and National Highways APIs, then synthesises a correlated summary. Results are cached in Redis for 15 minutes to reduce API load and improve response times for repeat queries.
