# Documentation Structure Plan

Plan to align top-level documentation with OSS best practices. **Scope**: README, CONTRIBUTING, `docs/`, and (for contribution tooling) `.github/` issue/PR templates and docs QA workflow. No application code changes.

**Reference**: Suggested structure — elevator pitch, quick start, architecture overview, LLM usage, running locally & tests, deployment & infra, example API/UX flows, contribution guidance.

**Topic-based split**: Core docs live under `docs/` as: `installation.md`, `architecture.md`, `agents-and-llm.md`, `usage.md`, `api.md`, `contributing.md`, `tests.md`. README becomes a short hub with links to these (see §7).

**Also in this plan**: §8 CONTRIBUTING and issue/PR templates; §9 Automate documentation QA (GitHub Actions); §10 Docs style and language.

**Related**: For code quality, architecture modularity, and testing improvements, see **[Code Quality and Architecture Plan](CODE_QUALITY_AND_ARCHITECTURE_PLAN.md)**. For LLM integration best practices (prompts, RAG, versioning, hallucinations), see **[Agents & LLM](agents-and-llm.md)**.

---

## 1. Gap analysis: README vs suggested structure

| Suggested element | Current state | Gap / action |
|-------------------|---------------|--------------|
| **What the project is (elevator pitch)** | First paragraph describes the app and scope. | **Partial.** Add a single-sentence elevator pitch at the very top (one line), then keep existing paragraph. |
| **Quick start (prerequisites & install)** | "Requirements" (Docker, Composer) + "Getting Started" (composer, yarn, sail up, migrate, boost). | **Partial.** Prerequisites are light; CONTRIBUTING has full list (PHP, Node/Yarn, Redis). In README: add a short "Prerequisites" list (or "see CONTRIBUTING for full list") and ensure first-run steps include `cp .env.example .env`, `key:generate`, and `yarn build` (or point to CONTRIBUTING for full setup). |
| **Architecture overview (high-level)** | No architecture section in README; link to `docs/architecture.md`. | **Gap.** Add an "Architecture overview" section with a brief bullet list of main components (LocationResolver → FloodWatchService/LLM → tools, cache, dashboard) and one sentence + link to `docs/architecture.md`. |
| **LLM usage (how AI integrates, tool roles)** | Strong: "LLM Tools" table, "Technical: LLM Tool Calling", "System Prompt", "Correlated Scenarios". | **Met.** Optional: add one sentence that the LLM orchestrates tool calls (not pre-scripted). No change required. |
| **Running locally & tests** | "Development" has `sail up -d`, `sail test`, `sail artisan`. | **Partial.** Explicitly add "Run tests: `sail test`" (or "Run tests: `sail test`; with coverage: `sail test --coverage`") so it's obvious. |
| **Deployment & infra** | Not in README; full content in `docs/DEPLOYMENT.md`. | **Gap.** Add a short "Deployment" section: one sentence (e.g. pilot on Railway), link to `docs/DEPLOYMENT.md`. |
| **Example API/UX flows** | "Correlated Scenarios" and "User Experience" describe behavior. | **Partial.** Add a simple "Example flow" (e.g. user enters postcode → geocode → LLM calls tools → correlated summary + dashboard). Can be a short bullet list or a tiny mermaid flow in README, or a link to `docs/` for a longer flow. |
| **Contribution guidance** | README links to CONTRIBUTING.md under "Documentation". | **Met.** Consider adding a dedicated "Contributing" line or section near the top/bottom for visibility; keep link to CONTRIBUTING.md. |

---

## 2. CONTRIBUTING.md

- **Current state**: Comprehensive (prerequisites, setup, workflow, code standards, testing, docs structure, PR process, AI-assisted dev, project-specific LLM/API/Livewire guidelines).
- **Action**: No structural change. Optionally add a one-line reference in CONTRIBUTING that "Top-level docs (README, CONTRIBUTING) follow the structure in `docs/DOCUMENTATION_STRUCTURE_PLAN.md`" for maintainers. **Optional.**

---

## 3. docs/ folder

- **Current state**: ARCHITECTURE.md, DEPLOYMENT.md, LLM guides, PLAN.md, WIREFRAMES.md, etc. Already the single place for deep dives.
- **Action**: Ensure README’s "Documentation" section links to all key docs (already links ARCHITECTURE, DEPLOYMENT via DEPLOYMENT not explicitly — check and add DEPLOYMENT if missing). After README edits, add "Deployment" to the Documentation table in README if not present.

---

## 4. Implementation checklist (doc-only)

Use this to track changes. All edits in **README.md**, **CONTRIBUTING.md**, or **docs/** only.

- [x] **README**
  - [x] Add a one-sentence elevator pitch at the top (below or as part of the title block).
  - [x] Expand **Prerequisites** (or reference CONTRIBUTING for full list) and ensure **Getting Started** includes: copy `.env.example` → `.env`, `key:generate`, and `yarn build` (or "See CONTRIBUTING for full setup").
  - [x] Add **Architecture overview**: 3–5 bullet points (e.g. user input → LocationResolver; FloodWatchService + LLM tools; cache; dashboard). Link to `docs/architecture.md`.
  - [x] Add **Running locally & tests**: explicit "Run tests: `sail test`" (and optionally `sail test --coverage`).
  - [x] Add **Deployment**: one sentence (e.g. pilot on Railway), link to `docs/DEPLOYMENT.md`.
  - [x] Add **Example flow**: short bullet list or minimal mermaid (e.g. postcode → geocode → LLM tools → summary + dashboard); or link to `docs/architecture.md` / new `docs/EXAMPLE_FLOWS.md` if we prefer long form in docs only.
  - [x] Ensure **Documentation** section includes link to `docs/DEPLOYMENT.md` if missing.
  - [x] Optional: add a clear **Contributing** line/section pointing to CONTRIBUTING.md.
- [x] **CONTRIBUTING.md**
  - [x] Optional: add a short note that top-level doc structure is described in `docs/DOCUMENTATION_STRUCTURE_PLAN.md`.
- [x] **docs/**
  - [ ] If we add a longer "Example API/UX flows" doc, add `docs/EXAMPLE_FLOWS.md` and link from README (optional).
  - [x] No other structural changes required for this plan.
  - [x] Reference IDE agent directories: `.cursor/rules` and `.cursor/skills` in README/CONTRIBUTING.
  - [x] Ensure key notes from `.cursor` are mirrored into `.junie/guidelines.md` for portability.
  - [x] Add PR checklist item: when agent rules/skills change, sync `.junie/guidelines.md`.

---

## 5. Wireframes

- **Scope**: "Wireframes" = `docs/WIREFRAMES.md` and any wireframe assets (e.g. `public/wireframes/`).
- **Action**: No changes required by the suggested documentation structure. Wireframes stay as-is unless we decide to document "Example UX flows" there; then we could add a short "Example user flow" section in `docs/WIREFRAMES.md` that mirrors README and links back. **Optional.**

---

## 6. Summary (pre–topic split)

| Location | Changes |
|----------|---------|
| **README.md** | Elevator pitch; prerequisites/quick start; architecture overview; run tests; deployment section; example flow; Documentation link to DEPLOYMENT; optional Contributing emphasis. |
| **CONTRIBUTING.md** | Optional reference to this plan. |
| **docs/** | Optional `docs/EXAMPLE_FLOWS.md`; ensure DEPLOYMENT linked from README. |
| **docs/WIREFRAMES.md** | Optional "Example user flow" cross-link. |

**Order of work**: (1) README updates, (2) verify docs links, (3) optional CONTRIBUTING + EXAMPLE_FLOWS + WIREFRAMES.

---

## 7. Split docs by topic (target structure)

Instead of overloading the README, documentation is split into topic-based files under `docs/`. The README stays a short hub with an elevator pitch and links to these docs.

### Target layout

```
docs/
  installation.md    # Prerequisites, quick start, env, Sail, first run
  architecture.md    # High-level components, data flow, domain structure, extension points
  agents-and-llm.md  # How AI integrates; tools; system prompt; correlation; cost/caching
  usage.md           # How to use the app: postcode, dashboard, UX, example flows
  api.md             # App’s HTTP API: health, map endpoints (polygons, river-levels), auth, rate limits
  contributing.md    # Dev workflow, code standards, PRs, AI-assisted dev (current CONTRIBUTING content)
  tests.md           # How to run tests, write tests, coverage, mocking
```

Other existing docs (PLAN.md, DEPLOYMENT.md, WIREFRAMES.md, SCHEMA.md, build/, archive/, etc.) stay as-is unless content is merged into the topic files below. Links from README and between docs use these canonical topic paths.

### Source → topic mapping

| New doc | Source content | Notes |
|---------|----------------|-------|
| **installation.md** | README “Requirements” + “Getting Started”; CONTRIBUTING “Prerequisites” + “Initial Setup” + “Configuration”. | Single place for prerequisites (Docker, PHP, Composer, Node/Yarn, Redis), clone, `composer install`, `yarn install`, `cp .env.example .env`, `key:generate`, `sail up`, `migrate`, `yarn build`, optional Boost. Env vars (OPENAI, NATIONAL_HIGHWAYS, Redis, cache). |
| **architecture.md** | Current `docs/architecture.md`. | Keep content; ensure this is the canonical name. Update all references from `ARCHITECTURE.md` to `architecture.md`. |
| **agents-and-llm.md** | README “LLM Tools”, “System Prompt”, “Technical: LLM Tool Calling”, “Correlated Scenarios”; `docs/LLM_INTEGRATION_GUIDE.md`; `docs/LLM_DATA_FLOW.md`; `docs/API_OPTIMIZATION_GUIDE.md` (LLM/caching parts); `docs/RISK_CORRELATION.md`. | One place for: tool list and roles, how the LLM orchestrates calls, system prompt and regions, correlation behaviour, data flow to/from LLM, optimization (caching, tokens), risk correlation rules. Deep-dive guides can be merged or linked as “See also” sections. |
| **usage.md** | README “User Experience”, “Scope: South West”, “Example flow”; optional `docs/EXAMPLE_FLOWS.md` if created. | End-user focus: regions/postcodes, dashboard (floods, roads, map, forecast, weather), postcode vs place vs “Use my location”, example UX flow (postcode → geocode → LLM → summary). No implementation detail. |
| **api.md** | `docs/architecture.md` “Public map API endpoints” and “Security”; routes: `/health`, `/flood-watch/polygons`, `/flood-watch/river-levels`; rate limits, session protection. | Document app’s HTTP API: health check, map data endpoints (params, responses, session + throttle), links to architecture for implementation. ✓ |
| **contributing.md** | Current root `CONTRIBUTING.md`. | Move or copy full content into `docs/contributing.md`. Root `CONTRIBUTING.md` can become a short pointer: “See [Contributing](docs/contributing.md).” so GitHub/git still find it. |
| **tests.md** | CONTRIBUTING “Testing”; README “Run tests”. | How to run tests (`sail test`, `sail test --coverage`, filter), where tests live, Pest conventions, mocking (e.g. OpenAI, APIs), coverage expectations. |

### README after the split

README should:

- Open with a **one-sentence elevator pitch** and one short paragraph (what it is, who it’s for).
- **No long sections** for install, architecture, LLM, usage, API, contributing, or tests — instead **link to the topic docs**.
- Include a **Documentation** section that lists:
  - [Installation](docs/installation.md)
  - [Architecture](docs/architecture.md)
  - [Agents & LLM](docs/agents-and-llm.md)
  - [Usage](docs/usage.md)
  - [API](docs/api.md)
  - [Contributing](docs/contributing.md)
  - [Tests](docs/tests.md)
  - Plus existing links (e.g. Deployment, Plan, Wireframes, Risk Correlation, etc.) as needed.
- Optionally keep a **very short** “Quick start” (3–4 commands) that points to `docs/installation.md` for full steps.

### Handling existing filenames

- **ARCHITECTURE.md** → Either rename to `architecture.md` or keep and add `docs/architecture.md` as a redirect/symlink; prefer one canonical file (`architecture.md`) and update all references.
- **CONTRIBUTING.md** (root) → Keep file; replace body with a short pointer to `docs/contributing.md` so repo visitors and GitHub “Contributing” link still work.
- **LLM_INTEGRATION_GUIDE.md**, **LLM_DATA_FLOW.md**, **API_OPTIMIZATION_GUIDE.md**, **RISK_CORRELATION.md** → Content merged into `agents-and-llm.md` (and optionally `architecture.md` for data flow). Either archive originals to `docs/archive/` or keep as “deep dives” with a note at the top: “Summary in [Agents & LLM](agents-and-llm.md).”
- **DEPLOYMENT.md**, **PLAN.md**, **WIREFRAMES.md**, **SCHEMA.md**, **build/**, **archive/** → Unchanged; linked from README or from the new topic docs where relevant.

### Implementation checklist (topic split)

- [x] **Create/migrate topic docs**
  - [x] **docs/installation.md** — Prerequisites, full install steps, env vars, Sail, first run. Link from README.
  - [x] **docs/architecture.md** — Move/rename from ARCHITECTURE.md or consolidate; ensure one canonical architecture doc.
  - [x] **docs/agents-and-llm.md** — New file merging README LLM bits + LLM_INTEGRATION_GUIDE + LLM_DATA_FLOW + API_OPTIMIZATION (LLM parts) + RISK_CORRELATION; or summary + links to existing guides.
  - [x] **docs/usage.md** — New file: user-facing usage, regions, dashboard, example flow. Content from README “User Experience” and “Scope”.
  - [x] **docs/api.md** — New file: health, map endpoints, protection, rate limits. Source: ARCHITECTURE “Public map API endpoints” and routes.
  - [x] **docs/contributing.md** — Copy or move content from root CONTRIBUTING.md.
  - [x] **docs/tests.md** — New file: run tests, write tests, Pest, mocking, coverage. Source: CONTRIBUTING “Testing”, README “Run tests”.
- [x] **README**
  - [x] Trim to elevator pitch + short intro + “Quick start” (minimal) + “Documentation” table linking to the seven topic docs and other key docs (Deployment, Plan, Wireframes, etc.).
- [x] **Root CONTRIBUTING.md**
  - [x] Replace body with short pointer to `docs/contributing.md` (or keep full content and add a note that the canonical doc is in docs/ — prefer pointer to avoid duplication).
- [x] **Cross-links**
  - [x] In each topic doc, add “See also” links to related topic docs where useful (e.g. architecture → agents-and-llm, installation → tests).
  - [x] Update any existing docs that link to ARCHITECTURE.md or CONTRIBUTING.md to use the new paths (architecture.md, contributing.md).
- [x] **Archive or retain**
  - [x] Decide for each of LLM_INTEGRATION_GUIDE, LLM_DATA_FLOW, API_OPTIMIZATION_GUIDE, RISK_CORRELATION: merge into agents-and-llm only, or keep and add “Summary in agents-and-llm” at top. Same for ARCHITECTURE → architecture.

### Order of work (combined plan)

1. Create the seven topic files (installation, architecture, agents-and-llm, usage, api, contributing, tests) by extracting/merging from README, CONTRIBUTING, and existing docs.
2. Slim down README to hub + links; point root CONTRIBUTING to docs/contributing.md.
3. Update cross-references and fix any broken links (ARCHITECTURE → architecture, etc.).
4. Archive or relabel any superseded docs.

---

## 8. CONTRIBUTING.md and issue / PR templates

**Goal**: Clear contribution guidelines and issue + PR templates so contributors know how to contribute and how to document their changes.

### Contribution guidelines

- **Primary source**: `docs/contributing.md` (full content). Root `CONTRIBUTING.md` is a short pointer so GitHub and repo visitors find it (see §7).
- **Content** (already largely in place): prerequisites, setup, development workflow, code standards (Pint, Prettier), testing, when to update docs, PR process, AI-assisted dev, project-specific (LLM, APIs, Livewire). Ensure it explicitly says:
  - **Documenting changes**: When to update which doc (README, docs/, API spec) and that PRs touching behaviour or APIs should mention doc updates in the PR description or checklist.

### Issue templates (GitHub “New issue” forms)

**Location**: `.github/ISSUE_TEMPLATE/`. GitHub shows these in the “New issue” dropdown.

| File | Purpose |
|------|---------|
| **config.yml** | Template chooser: title, description, and which template to open (bug, feature, docs). |
| **bug_report.md** | Bug report: environment, steps to reproduce, expected vs actual, logs/screenshots. |
| **feature_request.md** | Feature request: problem, proposed solution, alternatives. |
| **documentation.md** (optional) | Doc improvement: which doc, what’s wrong or missing, suggested change. |

- **bug_report.md**: Include fields for app version / commit, Sail vs local, and “Documentation updated?” N/A for bugs.
- **feature_request.md**: Include “Documentation impact” (e.g. “Will require updates to usage.md and api.md”).
- **documentation.md**: For “docs only” issues: broken link, outdated section, missing topic. Helps contributors open doc-only issues without choosing bug/feature.

Add a short **blurb in config.yml** that points to [Contributing](docs/contributing.md) for workflow and standards.

### PR template

**Location**: `.github/PULL_REQUEST_TEMPLATE.md`. **Current state**: Exists; content is feature-specific (admin, OpenAiUsageService, LlmRequest).

**Updates**:

- **Keep or generalize** the existing review checklist (or make it a “when applicable” section).
- **Add a general header** so every PR includes:
  - **What does this PR do?** (brief description)
  - **Why?** (problem or goal)
  - **How was it tested?** (manual and/or automated)
- **Add a Documentation section**:
  - [ ] If this PR changes user-facing behaviour or APIs, I updated the relevant docs (README and/or `docs/`).
  - [ ] If this PR only touches docs, I checked links and followed the [docs style guide](docs/DOCS_STYLE.md) (or equivalent).
- Optionally: **Docs-only PRs** — when only `docs/`, `README.md`, or `CONTRIBUTING.md` change, the “Documentation” checklist is required; code-review checklist can be “N/A”.

Result: Contributors see a consistent PR form and are prompted to document their changes.

### Implementation checklist (contributing and templates)

- [x] **docs/contributing.md** — Ensure a “Documenting your changes” subsection: when to update README vs docs/, and that PRs should tick doc updates in the PR template.
- [x] **Root CONTRIBUTING.md** — Short pointer to `docs/contributing.md` (per §7).
- [x] **.github/ISSUE_TEMPLATE/config.yml** — Create with name/description and list of templates (bug, feature, docs).
- [x] **.github/ISSUE_TEMPLATE/bug_report.md** — Environment, steps, expected/actual, “Documentation updated?” N/A.
- [x] **.github/ISSUE_TEMPLATE/feature_request.md** — Problem, solution, alternatives, “Documentation impact”.
- [x] **.github/ISSUE_TEMPLATE/documentation.md** (optional) — Doc-only issue form: which doc, what’s wrong, suggested fix.
- [x] **.github/PULL_REQUEST_TEMPLATE.md** — Add general section (what, why, how tested) and Documentation checklist; link to docs style guide. Keep or generalize existing review checklist.

---

## 9. Automate documentation QA (GitHub Actions)

**Goal**: Run checks before merge so PRs don’t introduce broken links or obviously stale docs.

### Workflow: docs QA

**Location**: `.github/workflows/docs-qa.yml` (new file).

**Trigger**: Pull requests that change any of:

- `docs/**`
- `README.md`
- `CONTRIBUTING.md`
- `.github/ISSUE_TEMPLATE/**`
- `.github/PULL_REQUEST_TEMPLATE.md`

Use `paths` filter so the job only runs when these paths are modified (optional: also run on push to default branch for the same paths).

**Jobs**:

1. **Link check**
   - Checkout repo.
   - Run a **markdown link checker** over `docs/`, `README.md`, `CONTRIBUTING.md`.
   - **Tools (pick one)**:
     - **lychee**: `lychee docs/ README.md CONTRIBUTING.md --no-progress` (fast, can exclude external URLs or allow fail for external).
     - **markdown-link-check**: per-file or recursive; configurable in JSON.
   - Prefer **internal links only** in the first iteration (relative links between docs, anchors). Optionally add external link check with `--allow-fail` or exclude list for flaky sites.
   - **Failure**: Workflow fails so the PR author must fix broken internal links before merge.

2. **Out-of-date / stale checks (optional)**
   - **Lightweight**: Grep for markers like `TODO`, `FIXME`, `OUTDATED`, or “Last updated:” in docs and either report or fail if present in certain files. Use only if the team will maintain such markers.
   - **Heavier**: Script that checks for references to removed files or renamed paths (e.g. `ARCHITECTURE.md` → `architecture.md`) and fails. Add when the topic split is done and we want to prevent old links.
   - Recommendation: Start with **link check only**; add stale checks in a follow-up if needed.

**Output**: Job success/failure visible on the PR. Optionally add a short “Docs QA” summary (e.g. “Checked N links, 0 broken”) as a job summary.

### Implementation checklist (docs QA)

- [x] **.github/workflows/docs-qa.yml** — New workflow.
  - [x] `on: pull_request` with `paths: ['docs/**', 'README.md', 'CONTRIBUTING.md', '.github/ISSUE_TEMPLATE/**', '.github/PULL_REQUEST_TEMPLATE.md']`.
  - [x] One job: install link checker (e.g. lychee or markdown-link-check), run over docs + README + CONTRIBUTING.
  - [x] Fail on broken internal links; optionally allow external link failures or exclude list.
- [ ] **Optional**: Second job or step for “stale” grep (TODO/FIXME/OUTDATED) in docs; or script for old path references after topic rename.

---

## 10. Docs style and language

**Goal**: Documentation is concise, scannable, and consistent: clear headers, bullet points, short sentences.

### Style rules (for all new and updated docs)

- **Concise**: Prefer short sentences. One idea per sentence where possible. Cut filler.
- **Scannable**: Use clear, hierarchical headers (H2, H3). Avoid long walls of text.
- **Lists**: Use bullet points for options, steps, or criteria. Use numbered lists for ordered steps.
- **Tables**: Use for comparisons, reference (e.g. tools, env vars, endpoints). Keep cells short.
- **Tone**: Neutral, instructional. Avoid humour or ambiguity.
- **Consistency**: Same terms across docs (e.g. “FloodWatchService”, “postcode”, “dashboard”). Link to the canonical doc for a concept instead of re-explaining.

### Where to record this

- **Option A**: Add a short **“Docs style”** subsection in this plan (§10) and reference it from `docs/contributing.md` (“When writing docs, follow the style in DOCUMENTATION_STRUCTURE_PLAN.md §10”).
- **Option B**: Create **docs/DOCS_STYLE.md** with the same rules (and examples: good vs bad sentence length, header structure). Then link to it from CONTRIBUTING, PR template, and docs QA readme so contributors and reviewers have one place to look.

Recommendation: **Option B** — create `docs/DOCS_STYLE.md` so the style guide is easy to find and the plan stays a plan.

### Implementation checklist (docs style)

- [x] **docs/DOCS_STYLE.md** — New file: concise, scannable; clear headers; bullet points; short sentences; tables where useful; tone; consistency. Include 1–2 “good vs avoid” examples.
- [x] **docs/contributing.md** — Add “When updating documentation” that links to `docs/DOCS_STYLE.md` and summarises the main rules.
- [x] **.github/PULL_REQUEST_TEMPLATE.md** — In the Documentation checklist, link to `docs/DOCS_STYLE.md` for “docs only” or “doc updates” (see §8).

---

## Order of work (full plan)

1. **Docs style** (§10): Create `docs/DOCS_STYLE.md`; add “When updating documentation” to contributing; link from PR template later.
2. **Topic split** (§7): Create the seven topic docs; slim README; root CONTRIBUTING pointer; cross-links.
3. **Contributing and templates** (§8): Ensure `docs/contributing.md` has “Documenting your changes”; add `.github/ISSUE_TEMPLATE/` (config, bug, feature, optional docs); update `.github/PULL_REQUEST_TEMPLATE.md` with general section and Documentation checklist; link to DOCS_STYLE.
4. **Docs QA** (§9): Add `.github/workflows/docs-qa.yml` (link check on PRs that touch docs/README/CONTRIBUTING/templates); optional stale checks later.
5. **Review**: Run docs QA on a branch, fix any broken links; confirm issue/PR templates and DOCS_STYLE are linked from the right places.
