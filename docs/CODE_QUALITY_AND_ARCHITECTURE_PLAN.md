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

- [x] Document naming conventions and architecture patterns in `docs/contributing.md` with dedicated "Code Conventions" section.
- [x] Add a short "Config keys" subsection in `docs/architecture.md` or installation: all app-specific config under `flood-watch.*`, env vars `FLOOD_WATCH_*`.
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

- [x] Confirm Pint is run in CI (e.g. `.github/workflows/style.yml` enforces it on code paths).
- [x] In `CONTRIBUTING.md`: Document code conventions including strict types, type hints, PHPDoc, and method focus principles.

### 1.3 DRY (reduce duplication)

**Goal**: Reduce duplication so behaviour lives in one place and is easier to test and change.

**Candidate areas**

| Location | Duplication | Suggested approach |
|----------|-------------|---------------------|
| **FloodWatchService::prepareToolResultForLlm()** | Repeated pattern: get config max → `array_slice`/truncate → return. Similar for GetFloodData, GetHighwaysIncidents, GetRiverLevels, GetFloodForecast. | Extract a small helper (e.g. `limitForLlm(array $items, string $configKey, ?int $default)`) or a per-tool trimmer class. Keep tool-specific rules (e.g. flood message char limit) in one place. |
| **Tool name strings** | `'GetFloodData'`, `'GetRiverLevels'`, etc. repeated in `executeTool`, `prepareToolResultForLlm`, progress messages, and prompt builder. | Consider a single source of truth: an enum (e.g. `ToolName::GetFloodData`) or a const array in one class, and use it everywhere. Reduces typos and eases adding tools. |
| **Config key strings** | `config('flood-watch.llm_max_floods', 25)` etc. scattered. | Optional: define config key constants in a dedicated class or in the service that uses them, so renames happen in one place. |

**Implementation checklist**

- [x] **prepareToolResultForLlm**: Refactor to shared "limit/truncate for LLM" logic (extracted to `App\Support\LlmTrim`).
- [x] **Tool names**: Introduced `App\Enums\ToolName` as a single source of truth used in FloodWatchService and FloodWatchPromptBuilder.
- [x] **Config keys**: Introduced `App\Support\ConfigKey` constants for frequently used keys.

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

- Keep the pattern: One service per data source / tool. Document it in `docs/architecture.md`: "Each LLM tool is implemented by a dedicated service (e.g. GetFloodData → EnvironmentAgencyFloodService) and orchestrated by `FloodWatchService`"
- Move any tool-specific shaping from the orchestrator into the owning service when possible (e.g. small DTO transforms). Keep LLM-focused truncation in one place (see DRY plan 1.3).
- Prefer DTOs over raw arrays at service boundaries where practical (already used for `FloodWarning`). Add lightweight DTOs if we expand tools.
- Keep domain separation: Flood → `app/Flood`, Roads → `app/Roads`, Shared/Correlations → `app/Services`.

### 2.3 Centralize tool names (single source of truth)

- Introduce a single source of truth for tool names used across:
  - `FloodWatchPromptBuilder::getToolDefinitions()`
  - `FloodWatchService::executeTool()` and `prepareToolResultForLlm()`
  - Logging/progress messages and any UI copy
- Options:
  - PHP enum `ToolName` with string values matching the OpenAI function names
  - Or a constants class `ToolNames` if we want to avoid enums
- Migration approach (no behaviour change):
  1. Add enum/constants.
  2. Type tool switches against the central list.
  3. Update prompt builder to reference the central list to avoid drift.

Implementation checklist

- [x] Create `App\Enums\ToolName` with: `GetFloodData`, `GetHighwaysIncidents`, `GetFloodForecast`, `GetRiverLevels`, `GetCorrelationSummary`.
- [x] Use central tool names in `executeTool()` and `prepareToolResultForLlm()`.
- [x] Reference central names in `FloodWatchPromptBuilder` to ensure exact matches.

### 2.4 Configuration keys and constants

- Keep all app settings under `flood-watch.*` with environment variables `FLOOD_WATCH_*`.
- For frequently-used keys in code paths (limits/timeouts), consider central config constants for discoverability.
- Document core keys in `docs/architecture.md`.

Implementation checklist

- [x] Document key config entries (timeouts, limits, defaults, cache TTLs) in Architecture docs.
- [x] Introduce a `ConfigKey` constants class where duplication is high.

---

## 3. Testing strategy

Goal: Improve confidence via unit tests for services, integration tests for orchestrator, and edge-case coverage (timeouts, empty data, truncation, token budgeting).

3.1 Unit tests (services)

- EnvironmentAgencyFloodService: happy path mapping, polygon/centroid handling, failure (HTTP 500), timeout/CB open returns [].
- RiverLevelService: parsing/limits around station lists.
- FloodForecastService: narrative parsing, character-limit truncation behaviour.
- NationalHighwaysService + merging/filtering rules (region, proximity, motorway filter).
- RiskCorrelationService: deterministic outputs with fixed inputs.

3.2 Orchestrator tests (`FloodWatchService`)

- Tool dispatch mapping: each tool called with expected args.
- `prepareToolResultForLlm()` shared-limit behaviour for each tool, including message truncation and overall correlation char budget.
- Token budget trimming: retains system + last exchange, then truncates tool contents if still over budget.

3.3 Livewire/UI smoke (optional if present)

- Dashboard component minimal render with seeded data (no network) to ensure no regressions.

Implementation checklist

- [x] Add/expand Pest tests for each service with mocked HTTP (covered happy and failure paths).
- [ ] Add orchestrator boundary tests for tool result trimming logic and token budgeting.
- [x] Provide fixtures for EA/Highways/Forecast sample payloads.

---

## 4. Error handling, observability, and resilience

- External calls: enforce timeouts and retries (already present in services like EA via `retry`).
- Circuit breaker: keep for flakier providers; ensure metrics/logs surface open/close.
- Logging: prefer structured logs. Mask sensitive content with `LogMasker` (already used) for large tool payloads.
- Graceful degradation: when a provider fails, return empty arrays and inform the LLM in correlation summaries.
- Rate limits: catch OpenAI rate-limit and transporter exceptions in orchestrator (already handled) and convert to user-friendly messages.

Implementation checklist

- [x] Ensure each external service has explicit timeouts and retries configurable via `flood-watch.*`.
- [ ] Add log context keys consistently: `tool`, `provider`, `region`, `lat`, `lng`.
- [x] Tests for failure paths (timeouts, 500s, CB open) returning safe empty structures.

---

## 5. Token and prompt management

- Keep tool result trimming in one place with per-tool rules. Avoid duplication.
- Provide conservative defaults for max items and char limits; expose via config.
- Maintain a versioned system prompt. Cache loaded prompt between requests (already cached in builder).

Implementation checklist

- [x] Extract `App\Support\LlmTrim` for list limiting to reduce duplication.
- [ ] Add tests asserting truncation for floods (message chars), forecast narrative, and correlation total char budget.

---

## 6. Caching and performance

- Cache expensive/slow external results with sensible TTLs to reduce API load.
- Use per-provider cache namespaces/stores if needed; respect freshness (short TTLs for incidents/levels).
- Cache LLM chat responses keyed by user message, region, and last tool exchange hash.

Implementation checklist

- [x] Document current caches and TTLs in Architecture docs.
- [x] Verify cache store selection logic (e.g., `array` vs `redis`) matches environment.
- [x] Add cache-hit/miss counters in logs for visibility.

---

## 7. CI, linting, and static analysis

- Ensure Pint runs in CI and fails on diff.
- Optionally add PHPStan/Psalm at a reasonable level focused on services and orchestrator.
- Run tests in CI with clear job separation: lint → static analysis → tests.

Implementation checklist

- [x] CI job: `.github/workflows/style.yml` enforces Pint on code paths.
- [x] Verify `php artisan test --compact` workflow in `.github/workflows/tests.yml`.
- [ ] Optional: add PHPStan with baseline for gradual improvement.

---

## 8. Incremental rollout plan

1. [x] Centralize tool names via enum.
2. [x] Refactor `prepareToolResultForLlm()` to use `LlmTrim` helper.
3. [x] Add/expand service unit tests with fixtures and failure-path coverage.
4. [x] Improve docs: Architecture conventions and Contributing code standards.
5. [x] Add CI gates (Pint + tests).
6. [ ] Add targeted orchestrator boundary tests.
7. [ ] Monitor logs for token usage, timeouts, and CB openings; iterate limits.

Success metrics

- Reduced duplication in orchestrator (one helper for list limiting).  
- All services have unit tests covering happy and failure paths.  
- Tool-name drift eliminated by central source of truth.  
- CI enforces style and tests; zero style diffs on main.
