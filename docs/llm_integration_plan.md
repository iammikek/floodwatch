# LLM Integration — Best Practices Plan

> NOTE: This plan has been consolidated into the single canonical page: **[Agents & LLM](agents-and-llm.md)**. Please update your bookmarks and open PRs against `docs/agents-and-llm.md`. The content below is retained for historical context and deeper background until fully merged.

Plan to improve how the project uses LLM tool calls (OpenAI) to synthesise data from multiple sources (flood warnings, river levels, road status) and produce correlated output. **Scope**: Prompts, context design, versioning, validation, and optional RAG. This document is a plan only; implementation is done in separate work.

**Reference**: Canonical LLM doc — `docs/agents-and-llm.md`. Deep dives archived in `docs/archive/` (LLM_INTEGRATION_GUIDE.md, LLM_DATA_FLOW.md).

---

## 1. Better prompt and context design

### 1.1 Separate prompts from code

**Goal**: Prompts live in a dedicated folder so they can be edited, reviewed, and versioned without touching application code.

**Current state**

- Prompts are already **separate**: `resources/prompts/{version}/system.txt` (e.g. `resources/prompts/v1/system.txt`).
- `FloodWatchPromptBuilder` loads the base prompt from file and appends region-specific blocks from config (`flood-watch.regions.{region}.prompt`).
- Prompt version is config-driven: `config('flood-watch.prompt_version', 'v1')` and `FLOOD_WATCH_PROMPT_VERSION` env.

**Recommendations**

- **Keep** the current layout. Document it clearly in `docs/agents-and-llm.md`: "System prompts live in `resources/prompts/<version>/system.txt`. Region snippets come from `config/flood-watch.regions`."
- **Optional**: If region prompts grow long, move them to files (e.g. `resources/prompts/v1/regions/somerset.txt`) and load via config or a small helper so all prompt text stays out of PHP.
- **Naming**: Use a clear convention (e.g. `system.txt` for main assistant instructions; optional `tools_context.txt` or per-tool snippets if you add more files).

**Implementation checklist**

- [ ] Document in `docs/agents-and-llm.md`: prompt file layout (`resources/prompts/`), how version and region are applied, and where to add new prompt content.
- [ ] Optional: Move long region prompts from config to files under `resources/prompts/<version>/regions/`.

### 1.2 Document prompt format and expected LLM behaviour

**Goal**: Clear documentation of prompt structure and input/output so maintainers and contributors know what the LLM is supposed to do and how to change it safely.

**Recommended content (in docs)**

- **Input structure**: What the LLM receives — system prompt (base + region), user message (with coordinates and optional postcode), and tool definitions. Reference the "user message" format built in `FloodWatchService` (e.g. lat/lng, region, optional postcode).
- **Output structure**: Expected shape of the final `chat()` result — `response` (string: "Current Status" + "Action Steps"), plus structured data (`floods`, `incidents`, `forecast`, `weather`, `riverLevels`) that come from **tool results**, not from parsing LLM text. Clarify that the narrative is free-form but the app expects a coherent status + bullet list.
- **Expected behaviour**: (1) LLM calls tools (GetFloodData, GetHighwaysIncidents, etc.) as needed; (2) uses GetCorrelationSummary for cross-referencing; (3) returns a final assistant message that summarises status and actions; (4) does not invent flood IDs, road names, or numbers — it should rely on tool output. Document any rules (e.g. prioritisation: Danger to Life → road closures → general alerts).
- **Prompt format**: If the system prompt has sections (e.g. role, rules, output format), describe them in the doc or in a short comment at the top of `system.txt`.

**Implementation checklist**

- [ ] Add a "Prompt format and expected behaviour" section to `docs/agents-and-llm.md` (or to `docs/LLM_INTEGRATION_GUIDE.md`): input structure, output structure, expected behaviour, and where prompt text lives.
- [ ] Optional: Add a short header comment in `resources/prompts/v1/system.txt` describing sections (e.g. "## Role", "## Output format") for quick scanning.

---

## 2. RAG (Retrieval Augmented Generation)

**Goal**: If context grows too large or too noisy, use retrieval so the LLM gets accurate, relevant context instead of raw long dumps. Improves reliability; LLMs struggle with long, inconsistent documentation unless structured.

**Current state**

- Context is built from: system prompt + user message + tool definitions + (during the loop) tool results. Tool results are already **trimmed** for the LLM (`prepareToolResultForLlm`: max floods, max incidents, max chars for forecast, etc.) to stay within token limits.
- No vector store or retrieval today; the LLM sees a single conversation with inline tool outputs.

**When to consider RAG**

- **Now**: Not required. Context is bounded by design (limits on items and chars per tool). If you add many more tools or very long reference docs, token limits and noise may become an issue.
- **Later**: Consider RAG if (1) you inject long reference documentation (e.g. flood-area descriptions, road networks), or (2) you have a large set of past incidents/warnings and want the LLM to "retrieve" only relevant ones instead of receiving a single big dump.
- **Approach**: Use a vector store (e.g. Laravel Vector, or external) to index chunks of reference data; at request time, retrieve top-k chunks by relevance to the user query/location; pass only those chunks (and tool results) to the LLM. Keep tool results (current floods, incidents) as-is; use RAG for supplementary context, not as a replacement for live API data.

**Implementation checklist**

- [ ] Document in `docs/agents-and-llm.md`: "Context is currently bounded by per-tool limits; no RAG. If we add long reference docs or large historical data, consider RAG (vector store + retrieval) so the LLM receives only relevant chunks."
- [ ] No code change until product needs it; treat RAG as a future option.

---

## 3. Version your tool calls

**Goal**: Explicitly log which model version and prompt version produced each result so outputs remain reproducible as tools and prompts evolve. Add tests for expected response shapes.

### 3.1 Current state

- **Model**: Logged in `LlmRequest` — `model` is set from `$response->model` (OpenAI’s model identifier). Request-side model is `config('openai.model', 'gpt-4o-mini')` but not stored on the record.
- **Prompt version**: Config has `flood-watch.prompt_version` (env: `FLOOD_WATCH_PROMPT_VERSION`). It is **not** stored on `LlmRequest`, so you cannot later know which prompt version produced a given response.
- **Tool definitions**: Live in code (`FloodWatchPromptBuilder::getToolDefinitions()`); no version tag.

### 3.2 Recommendations

- **Store prompt_version on LlmRequest**: When recording an LLM request, add a column `prompt_version` (string, nullable) and set it from `config('flood-watch.prompt_version')`. Enables reproducibility and debugging ("this response used v1 prompt").
- **Optional: Store requested model**: If the app ever allows per-request model override, store the model that was actually requested (e.g. `requested_model`) in addition to `response->model`; otherwise, logging `response->model` is enough.
- **Tool definitions**: Optionally add a `tool_definitions_version` or a hash of the tool definitions in config/logs so you can detect when behaviour changed due to tool changes. Lower priority than prompt_version.
- **Reproducibility**: Document in runbook or architecture: "To reproduce an old response, set FLOOD_WATCH_PROMPT_VERSION and OPENAI_MODEL to the values at the time; re-run with same user message and coordinates." Snapshot tests (e.g. `FloodWatchPromptBuilderTest`) already guard prompt structure; add or extend tests that assert **response shape** (e.g. keys `response`, `floods`, `incidents`, `forecast`, `weather`, `riverLevels`) and optionally a minimal structure for `floods`/`incidents` (e.g. expected keys per item).

**Implementation checklist**

- [ ] **Migration**: Add `prompt_version` to `llm_requests` (string, nullable). Backfill optional.
- [ ] **Record**: In `FloodWatchService::dispatchRecordLlmRequest`, include `prompt_version` => `config('flood-watch.prompt_version')` in the payload; add to `LlmRequest` fillable and `RecordLlmRequestJob` payload.
- [ ] **Docs**: In `docs/agents-and-llm.md` or architecture: "We log model and prompt_version per request for reproducibility."
- [ ] **Tests**: Add or extend tests that assert the shape of the object returned by `FloodWatchService::chat()` (e.g. required keys, type of `floods`/`incidents` as arrays of expected shape). Optional: snapshot test for a fixed prompt + faked tool responses to lock in expected structure.
- [ ] Optional: `tool_definitions_version` or hash in config/logs; document when to bump.

---

## 4. Guard against hallucinations

**Goal**: LLMs can make plausible but incorrect claims. Validate critical data from LLM responses against authoritative APIs before displaying to users.

### 4.1 Current state

- **Structured data** (flood list, road incidents, river levels, forecast, weather) are **not** parsed from the LLM. They come from **tool execution** — i.e. from Environment Agency, National Highways, and other APIs. The app stores these in the chat result and the dashboard renders them from `result['floods']`, `result['incidents']`, etc. So numbers, IDs, and statuses shown in lists and on the map are **authoritative**.
- **LLM output** is used for the **narrative only**: the "Current Status" and "Action Steps" text (the `response` string). The UI does not use the LLM to decide "how many floods" or "which roads are closed"; that comes from the tool results.

So the architecture already **separates** authoritative data (tool results) from synthesised narrative (LLM). That is the right pattern to avoid hallucination on critical facts.

### 4.2 Recommendations

- **Keep the separation**: Never parse the LLM’s free text to extract flood counts, road names, or incident IDs for display. Always display from the structured tool results (`floods`, `incidents`, etc.).
- **Document the rule**: In `docs/agents-and-llm.md` and in code comments where the result is consumed: "Critical data (floods, incidents, levels) are taken from tool results only. The LLM response is narrative only; do not use it to derive facts for display."
- **If you add features that use LLM output as data**: For example, if the LLM ever returns structured JSON (e.g. "top 3 risks") or you parse its text for numbers/dates, then (1) validate or cross-check against the same authoritative APIs before showing to users, or (2) prefer to compute those values in code from tool results and only use the LLM to phrase them. Do not display LLM-originated numbers or statuses without validation.
- **Prompt guidance**: In the system prompt, reinforce that the assistant must base its summary on the tool data provided and must not invent flood IDs, road numbers, or statistics. Already partly covered by "use tool output"; can be made explicit in prompt docs.

**Implementation checklist**

- [ ] Document in `docs/agents-and-llm.md`: "Critical data is from tool results only; LLM output is narrative. Do not parse LLM text for facts to display."
- [ ] If any future feature uses LLM output as structured data, add validation or derivation from APIs before display; document the rule in this plan and in the feature’s doc.
- [ ] Optional: Add a one-line comment in `FloodWatchService` or the Livewire component where the result is used: "Display floods/incidents from result arrays, not from LLM text."

---

## 5. Order of work

1. **Dedicated LLM doc** (§6): Create or update `docs/agents-and-llm.md` with every tool, API calls, expected outputs, limitations, fallbacks, and example request/response pairs. This is the main “documentation around LLM use” deliverable.
2. **Document** prompt layout, format, and expected behaviour (§1); document RAG as future option (§2).
3. **Versioning**: Add `prompt_version` to LlmRequest and record it; document reproducibility (§3).
4. **Tests**: Add or extend tests for response shape (§3); document hallucination guard (§4).
5. **Optional**: Move long region prompts to files; add prompt_version to admin UI for debugging.
6. **RAG**: No implementation until needed; keep as a documented option.

---

## 6. Dedicated LLM docs page (agents-and-llm.md)

**Goal**: A single, authoritative doc that describes every agent/tool, the APIs they use, expected outputs, limitations, fallbacks, and example request/response pairs. This saves time for maintainers and helps new devs understand what happens under the hood.

**Canonical file**: `docs/agents-and-llm.md` (same as the topic doc in the documentation structure plan). If the topic split creates this file by merging existing LLM content, it **must** include the structure below.

### 6.1 Required content for agents-and-llm.md

The page should clearly describe the following. Use clear headers, tables, and code blocks so the doc is scannable (see `docs/DOCS_STYLE.md`).

| Section | Content |
|--------|---------|
| **Overview** | One short paragraph: what the LLM does (orchestrates tools, synthesises narrative), which model, where prompts live. Link to prompt format and expected behaviour (§1.2). |
| **Each agent/tool registered** | A subsection (or table) for **every** tool the LLM can call: GetFloodData, GetHighwaysIncidents, GetFloodForecast, GetRiverLevels, GetCorrelationSummary. For each: name, purpose in one sentence, and link to the detailed row below. |
| **Per-tool: API calls** | For each tool: **What API calls it makes** — e.g. GetFloodData → Environment Agency flood-monitoring API `GET .../floods?lat=&long=&dist=`. GetHighwaysIncidents → National Highways v2.0 closures API. GetRiverLevels → EA readings endpoint. GetFloodForecast → FGS API. GetCorrelationSummary → no external API (uses RiskCorrelationService). Include HTTP method, base URL pattern, and key query/body params. |
| **Per-tool: Expected outputs** | For each tool: **Expected output shape** — e.g. GetFloodData returns an array of objects with `id`, `severity`, `message`, `area`, `timeRaised`, etc. GetHighwaysIncidents returns array of incidents with `id`, `road`, `status`, `delayTime`, etc. Use a short code block (JSON or PHP array shape) or a table of keys and types. Mention any trimming applied before sending to the LLM (e.g. `llm_max_floods`, `llm_max_incidents`). |
| **Limitations** | **Latency**: Typical round-trip for a full chat (with tool calls); note that multiple tool calls add round-trips. **Costs**: Token usage (input/output), approximate cost per request or per 1k requests; reference `openai.model` and admin LLM cost dashboard. **Rate limits**: OpenAI rate limits; any app-side throttling (e.g. guest rate limit). **Context limits**: Max context length, and how we trim tool results to stay under it. |
| **Fallback behaviours** | **If an API fails**: Per external API (EA, National Highways, FGS, etc.): what happens when the request times out or returns 5xx (e.g. circuit breaker opens, tool returns `[]` or `['error' => '...']`, LLM still receives the result and can say "Flood data temporarily unavailable"). **If the LLM fails**: Empty or error response; what the user sees (e.g. friendly message, partial data). Link to circuit breaker config and graceful degradation in architecture. |
| **Example request/response pairs** | At least one **example request** (e.g. user message + coordinates + region) and the **result shape** (e.g. `response` string snippet, `floods` array length and one sample item, `incidents` sample). Optionally: a minimal example of a **single tool call** (e.g. GetFloodData input args and a short sample JSON output). This helps devs debug and write tests. |

### 6.2 Optional but recommended

- **Diagram**: A single diagram (e.g. Mermaid) of the flow: user message → LLM → tool calls → APIs → tool results → LLM → final response. Can reuse or adapt from `docs/LLM_DATA_FLOW.md`.
- **Prompt versioning**: Short note that we log model and prompt_version per request; link to §3.
- **Hallucination guard**: One sentence that critical data is from tool results only; narrative from LLM. Link to §4.
- **Links**: To `LLM_INTEGRATION_GUIDE.md` (optimization, caching, errors), `LLM_DATA_FLOW.md` (data flow), and `architecture.md` (circuit breaker, config).

### 6.3 Implementation checklist

- [ ] **Create or update** `docs/agents-and-llm.md` so it includes:
  - [ ] Overview (model, prompts location, one-sentence role).
  - [ ] **List of every registered tool** (GetFloodData, GetHighwaysIncidents, GetFloodForecast, GetRiverLevels, GetCorrelationSummary).
  - [ ] **Per-tool**: What API calls it makes (HTTP, URL pattern, params).
  - [ ] **Per-tool**: Expected output shape (table or code block).
  - [ ] **Limitations**: Latency, costs, rate limits, context limits.
  - [ ] **Fallback behaviours**: What happens when each API fails; what happens when the LLM fails.
  - [ ] **Example request/response**: At least one full request (message + coords + region) and result shape; optionally one tool call example.
- [ ] **Style**: Clear headers, bullet points, short sentences; example blocks are concise and realistic.
- [ ] **Cross-links**: From README (Documentation table) to `docs/agents-and-llm.md`; from this doc to LLM_INTEGRATION_GUIDE, LLM_DATA_FLOW, ARCHITECTURE.

### 6.4 Source of truth for “what each tool does”

When filling the per-tool sections, use:

- **Tool definitions**: `FloodWatchPromptBuilder::getToolDefinitions()` for names and descriptions.
- **API calls**: `EnvironmentAgencyFloodService`, `NationalHighwaysService`, `FloodForecastService`, `RiverLevelService` (and their HTTP client calls); `RiskCorrelationService` for GetCorrelationSummary.
- **Output shapes**: DTOs and `prepareToolResultForLlm` (trimmed shapes); EA and National Highways API docs for raw shapes.
- **Fallbacks**: Circuit breaker behaviour in `app/Support/CircuitBreaker.php` and in services; `FloodWatchService` handling of tool `['error' => ...]` and empty arrays.

---

## 7. Cross-references

- **Agents & LLM**: `docs/agents-and-llm.md` — main place for prompt design, versioning, validation rules, **and** the dedicated LLM docs content above (§6).
- **Existing**: `docs/LLM_INTEGRATION_GUIDE.md`, `docs/LLM_DATA_FLOW.md` — keep; link from agents-and-llm or merge summaries into agents-and-llm and keep these as deep dives.
- **Code quality**: `docs/CODE_QUALITY_AND_ARCHITECTURE_plan.md` — testing and modularity; LLM tests and tool→service mapping align with that plan.
- **Schema**: `docs/schema.md` — update when adding `prompt_version` to `llm_requests`.
- **Documentation plan**: Completed — see the README Documentation section for canonical links; ensure required content in `agents-and-llm.md` matches §6 above.
