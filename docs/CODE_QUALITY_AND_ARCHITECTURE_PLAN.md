# Code Quality and Architecture Plan

Plan to improve code quality, readability, modularity, and test coverage. **Scope**: App codebase and tests. This document is a plan only; implementation is done in separate work.

**Goals**: Clean code and consistent conventions; modular, testable LLM-tool architecture; stronger unit and integration tests, especially for LLM + data combination and error paths.

---

## 1. Clean code and readability

### 1.1 Naming conventions

**Goal**: Consistent naming for variables, functions, and config keys so newcomers quickly understand roles.

| Area | Convention | Current state / action |
|------|------------|------------------------|
| **Config keys** | `flood-watch.<domain>.<purpose>` (e.g. `flood-watch.llm_max_floods`, `flood-watch.circuit_breaker.enabled`). Use snake_case. | Already consistent under `config/flood-watch.php`. Document in `docs/architecture.md` or contributing: "All Flood Watch config lives under `flood-watch.*`." |
| **Environment variables** | `FLOOD_WATCH_*` prefix using SCREAMING_SNAKE_CASE | `FLOOD_WATCH_CACHE_TTL_MINUTES`, `FLOOD_WATCH_LLM_MAX_INCIDENTS` |
| **PHP variables** | camelCase. Descriptive names (e.g. `$centerLat`, `$maxFloods`, `$toolDefinitions`). | Audit `FloodWatchService`, `FloodWatchPromptBuilder`, and tool-execution paths for abbreviations or unclear names; rename where it helps. |
| **PHP methods** | camelCase. Verb-noun for actions (e.g. `getFloods`, `prepareToolResultForLlm`, `executeTool`). | Largely consistent. Ensure public methods have clear names; private helpers can be shorter if scope is tiny. |
| **Service classes** | Noun + Service (e.g. `EnvironmentAgencyFloodService`, `NationalHighwaysService`). One service per external data source or tool. | Already used. Keep and document the pattern: "One service per LLM tool / data source." |
| **Tool names** | PascalCase with clear, concise descriptions (e.g. `GetFloodData`, `GetRiverLevels`, `GetCorrelationSummary`). | Consider a single source of truth: enum or const array to reduce typos and ease adding tools. |

**Architecture Patterns**:

- **One service per LLM tool/data source**: Each tool in the LLM system maps to a dedicated service class
- **Domain separation**: Keep flood-related services in `app/Flood/Services/`, road-related in `app/Roads/Services/`
- **Configuration centralization**: All Flood Watch config lives under the `flood-watch.*` namespace
- **Tool consistency**: Tool names should be used consistently across `executeTool()`, `prepareToolResultForLlm()`, and prompt definitions

**Code Quality Rules**:

- **DRY principle**: Extract common patterns (especially in tool result preparation and config access)
- **Error handling**: All external API calls must include timeout handling and graceful degradation
- **Token efficiency**: Always consider OpenAI token usage when modifying tool outputs
- **Cache appropriately**: Use TTL values that balance freshness with API rate limits

**Implementation checklist**

- [ ] Document naming conventions and architecture patterns in `docs/contributing.md` with dedicated "Code Conventions" section.
- [ ] Add a short "Config keys" subsection in `docs/architecture.md` or installation: all app-specific config under `flood-watch.*`, env vars `FLOOD_WATCH_*`.
- [ ] Optional: Light pass over `FloodWatchService`, `FloodWatchPromptBuilder`, and call sites to rename unclear variables (no behaviour change).

### 1.2 PSR-12 and Laravel style

**Goal**: PHP follows PSR-12 and Laravel ecosystem norms so the codebase feels familiar.

- **PSR-12**: Indentation, braces, line length, use statements, visibility. Enforced by **Laravel Pint** (project already uses Pint).
- **Laravel**: Prefer Eloquent and contracts; type-hint parameters and return types; use constructor promotion where it helps readability.
- **Strict typing**: Use `declare(strict_types=1);` at the top of all PHP files.
- **Type hints**: All parameters and return types must be type-hinted.
- **PHPDoc**: Add for complex methods explaining parameters, return values, and thrown exceptions.
- **Method focus**: Keep methods focused on single responsibility; prefer early returns over nested conditionals.

**Action**

- Keep running `vendor/bin/pint` (or `sail pint`) before commits; CI can enforce Pint (add to workflow if not already).
- In contributing: state that PHP must pass `sail pint` with no changes (or document any excluded paths).

**Implementation checklist**

- [ ] Confirm Pint is run in CI (e.g. `.github/workflows/tests.yml` or a dedicated lint job).
- [ ] In `docs/contributing.md`: Document code conventions including strict types, type hints, PHPDoc, and method focus principles.

### 1.3 DRY (reduce duplication)

**Goal**: Reduce duplication so behaviour lives in one place and is easier to test and change.

**Candidate areas**

| Location | Duplication | Suggested approach |
|----------|-------------|---------------------|
| **FloodWatchService::prepareToolResultForLlm()** | Repeated pattern: get config max → `array_slice`/truncate → return. Similar for GetFloodData, GetHighwaysIncidents, GetRiverLevels, GetFloodForecast. | Extract a small helper (e.g. `limitForLlm(array $items, string $configKey, ?int $default)`) or a per-tool trimmer class. Keep tool-specific rules (e.g. flood message char limit) in one place. |
| **Tool name strings** | `'GetFloodData'`, `'GetRiverLevels'`, etc. repeated in `executeTool`, `prepareToolResultForLlm`, progress messages, and prompt builder. | Consider a single source of truth: an enum (e.g. `ToolName::GetFloodData`) or a const array in one class, and use it everywhere. Reduces typos and eases adding tools. |
| **Config key strings** | `config('flood-watch.llm_max_floods', 25)` etc. scattered. | Optional: define config key constants in a dedicated class or in the service that uses them, so renames happen in one place. |

**Implementation checklist**

- [ ] **prepareToolResultForLlm**: Refactor to shared "limit/truncate for LLM" logic; keep tool-specific behaviour explicit but avoid copy-paste.
- [ ] **Tool names**: Introduce a single source of truth (enum or const list) and use it in FloodWatchService, FloodWatchPromptBuilder, and any progress/UI copy.
- [ ] **Config keys**: Optional constants for `flood-watch.*` keys used in services; document in architecture.

---

## 2. Modular architecture (LLM tools and services)

**Goal**: Each LLM tool is backed by a clear, testable service. Logic is reusable and not buried in the orchestrator.

### 2.1 Current state

- **GetFloodData** → `EnvironmentAgencyFloodService::getFloods()` (in `app/Flood/Services/`).
- **GetRiverLevels** → `RiverLevelService::getLevels()` (in `app/Flood/Services/`).
- **GetFloodForecast** → `FloodForecastService::getForecast()` (in `app/Flood/Services/`).
- **GetHighwaysIncidents** → `NationalHighwaysService` + filtering in `FloodWatchService` (merged, filtered by region/proximity).
- **GetCorrelationSummary** → `RiskCorrelationService::correlate()` (in `app/Services/`).

So most tools already map to a service. The main orchestration and tool-specific "prepare for LLM" logic live in `FloodWatchService`.

### 2.2 Recommendations

- **Keep the pattern**: One service per data source / tool. Document it in `docs/architecture.md`: "Each LLM tool is implemented by a dedicated service (e.g. GetFloodData → EnvironmentAgencyFloodService)."
- **Reduce orchestration clutter**: Move tool execution and "prepare for LLM" out of `FloodWatchService` only if the class grows too large (e.g. a dedicated `ToolExecutor` or per-tool handler classes). For now, a clear `match` in `executeTool` and a well-factored `prepareToolResultForLlm` (see DRY above) may be enough.
- **Highways incidents**: Filtering (region, proximity, motorways, priority) is in `FloodWatchService`. Consider moving to a dedicated helper (e.g. `HighwaysIncidentFilter` or methods on `NationalHighwaysService`) so orchestration stays thin and filtering is unit-testable in isolation.
- **New tools**: When adding a tool, add a new service (or extend an existing one) and register it in the tool definitions and in `executeTool`; document in agents-and-llm.

**Implementation checklist**

- [ ] Document in `docs/architecture.md`: tool → service mapping table; "one service per tool/data source."
- [ ] Optional: Extract highways filtering into a testable class/module; call it from `FloodWatchService`.
- [ ] Optional: If `FloodWatchService` grows further, consider extracting `executeTool` + `prepareToolResultForLlm` into a `FloodWatchToolExecutor` (or similar) that receives the service instances and config.

---

## 3. Testing surface

**Goal**: More unit and integration tests, especially where LLM responses are combined with data sources; and solid coverage of error paths (timeouts, API failures, fallbacks).

### 3.1 Current coverage (reference)

- **FloodWatchServiceTest**: Chat flow, caching, tool calls, OpenAI fakes, partial responses, rate limit handling.
- **CircuitBreakerIntegrationTest**: Circuit breaker open/disabled; Environment Agency returning empty when circuit open.
- **FloodWatchDashboardTest**: Includes timeout error message (e.g. `test_search_displays_friendly_message_for_timeout_error`).
- Other feature/unit tests for services, Livewire, and APIs.

### 3.2 Gaps to address

| Area | What to add | Priority |
|------|--------------|----------|
| **LLM + data combination** | Integration-style tests: given real (or faked) tool responses (floods, incidents, river levels), assert that the combined response or correlation summary is shaped correctly and that the LLM receives truncated/limited data as configured. | High |
| **Error paths** | Explicit tests for: (1) **Timeouts**: external API timeout → user sees friendly message; (2) **API failures**: e.g. EA or National Highways returns 5xx → circuit breaker or fallback; (3) **Fallback behaviour**: one tool fails → rest of response still returned; correlation still runs on available data. | High |
| **prepareToolResultForLlm** | Unit tests: for each tool, given a large payload, assert output is limited/truncated per config (max items, max chars). Protects DRY refactor and config changes. | Medium |
| **Tool name / config consistency** | If you introduce a ToolName enum or config constants, add a test that all tools in the enum are handled in `executeTool` and in `prepareToolResultForLlm` (no missing branch). | Low |

### 3.3 Concrete test ideas

- **Timeout**: Mock an HTTP client or service to throw `ConnectionException` or timeout; call `FloodWatchService::chat()` or the dashboard search; assert response contains a timeout/friendly error message and no exception.
- **API failure**: Mock EA or National Highways to return 500; assert circuit breaker records failure (or service returns empty); assert chat still returns a response (e.g. "Flood data temporarily unavailable") and other tools’ data still appear.
- **Fallback**: Simulate GetFloodData returning empty and GetHighwaysIncidents returning data; assert correlation and summary still run and include highways info.
- **LLM + data**: Use OpenAI fake with a fixed completion; with faked tool results (e.g. a few floods, a few incidents), assert final `chat()` result structure (e.g. `response`, `floods`, `incidents`) and that truncation limits were applied (e.g. at most `llm_max_floods` in the payload sent to the model).

**Implementation checklist**

- [ ] **Error paths**: Add or extend tests for timeout, API failure (5xx), and fallback (one tool fails, others succeed). Prefer feature tests that go through `FloodWatchService::chat()` or the dashboard.
- [ ] **LLM + data**: Add tests that fake tool responses and assert combined result shape and truncation (config-driven limits).
- [ ] **prepareToolResultForLlm**: Unit tests per tool for "large input → limited output" with current config keys.
- [ ] **Optional**: Consistency test for tool names (enum vs executeTool/prepareToolResultForLlm branches) when that refactor is done.
- [ ] Document in `docs/tests.md`: where to add tests for new tools, how to fake OpenAI and external APIs, and how to test error paths.

---

## 4. Order of work

1. **Document** conventions (naming, PSR-12/Pint, config) in contributing and architecture.
2. **DRY** refactors: tool names single source of truth; prepareToolResultForLlm helpers; optional config constants.
3. **Modular** doc and optional extraction: document tool → service; optionally extract highways filtering or ToolExecutor.
4. **Tests**: Error-path tests (timeout, API failure, fallback); LLM + data combination tests; prepareToolResultForLlm unit tests.
5. **CI**: Ensure Pint (and any other lint) runs on PRs.

---

## 5. Cross-references

- **Architecture**: `docs/architecture.md` (or `docs/ARCHITECTURE.md`) — tool → service mapping, config layout.
- **Contributing**: `docs/contributing.md` — code conventions, testing expectations.
- **Tests**: `docs/tests.md` — how to run tests, how to fake APIs and LLM, where to add tests for new tools and error paths.
- **Documentation plan**: `docs/DOCUMENTATION_STRUCTURE_PLAN.md` — docs structure only; this plan is for code quality and architecture.
- **LLM integration**: `docs/LLM_INTEGRATION_PLAN.md` — prompt design, versioning, RAG, guarding against hallucinations; aligns with testing and tool→service architecture here.
