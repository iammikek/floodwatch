# Documentation style guide

Use this guide when writing or updating docs in this repo (README, CONTRIBUTING, `docs/`). It keeps docs concise and scannable.

## Principles

- **Concise**: Short sentences. One idea per sentence. Cut filler.
- **Scannable**: Clear headers (H2, H3). Avoid long walls of text.
- **Structured**: Use lists and tables so readers can jump to what they need.
- **Neutral**: Instructional tone. No humour or ambiguity.

## Headers

- Use hierarchical headers (H2 for main sections, H3 for subsections).
- Prefer short, concrete titles (e.g. "Per-tool: API calls" not "Information about the APIs that are called").
- Keep heading levels consistent within a doc.

## Lists

- **Bullet points** for options, criteria, or non-ordered items.
- **Numbered lists** for steps that must be done in order.
- Start list items with a verb or key term when possible.

**Good**: "Run `sail up -d`. Copy `.env.example` to `.env`."  
**Avoid**: "You might want to run sail up and then copy the env file."

## Tables

Use tables for:

- Comparisons (e.g. tool vs data source).
- Reference (env vars, endpoints, config keys).
- Keep cells short; use a second row or a follow-up sentence if you need more detail.

## Code and commands

- Use backticks for inline code, file names, and command names.
- Use fenced code blocks for multi-line examples. Add a language hint (`php`, `bash`, `json`) when it helps.
- Prefer realistic examples (e.g. real config key names, real endpoints).

## Consistency

- Use the same terms across docs (e.g. "FloodWatchService", "postcode", "dashboard").
- Link to the canonical doc for a concept instead of re-explaining in full.
- When a doc is superseded, add a one-line note at the top and link to the new doc.

## Good vs avoid

| Prefer | Avoid |
|--------|--------|
| Short sentence. Next sentence. | Long sentence with several clauses that tries to cover multiple ideas at once and makes the reader work to find the subject and verb. |
| Bullet list of steps or options | Paragraph that buries the steps in prose. |
| Table for env vars or endpoints | Repeating the same structure in many paragraphs. |
| "Run `sail test`." | "It is possible to run the tests by using the sail test command." |
