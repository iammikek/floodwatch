# AI Agents Guide

Quick reference for AI agents working on this project.

## Project Context

**Flood Watch** - Somerset Levels Safety Agent. Correlates Environment Agency flood data with National Highways v2.0 (DATEX II) road status. Single Source of Truth for flood + road viability.

**Tech Stack**: Laravel 12.x, PHP 8.2+, Concurrency facade, Laravel Sail (Docker), Livewire 4, PHPUnit + Pest, fully TDD, Laravel Boost, LLM integration (openai-php/laravel or similar).

## Somerset Levels Logic

**Default coordinates**: Langport (51.0358, -2.8318)

**Tool A (GetFloodData)**: Environment Agency API. River Parrett, River Tone. Returns severityLevel, area, advice. Endpoint: `https://environment.data.gov.uk/flood-monitoring/id/floods?lat=51.0358&long=-2.8318&dist=15`

**Tool B (GetHighwaysIncidents)**: National Highways v2.0 DATEX II. A361, A372, M5 J23–J25. Returns status, delayTime, incidentType. Requires API key in `.env`.

**Muchelney rule**: If Langport flood level exceeds threshold → proactively check Muchelney road status (predictive warning).

**Concurrency**: Use `Concurrency::run([fn () => $floodService->...(), fn () => $highwaysService->...()])` to fetch both APIs in parallel.

**System prompt (Somerset Emergency Assistant)**: Data Correlation (North Moor/King's Sedgemoor → cross-reference A361 East Lyng). Contextual Awareness (Muchelney cut-off risk; predictive warnings when Parrett rising). Prioritization: Danger to Life → road closures → general flood alerts.

## Laravel Sail

Primary dev environment. All commands run via Sail. Use `./vendor/bin/sail` or configure the `sail` shell alias.

- `./vendor/bin/sail up` or `sail up -d` - Start containers
- `sail artisan <cmd>` - Artisan commands
- `sail test` or `sail php artisan test` - Run tests
- `sail composer <cmd>` - Composer
- `sail shell` - Container shell

## TDD Requirements

- **Red-Green-Refactor**: Write failing test first, then implementation
- **No production code without a failing test first**
- **Test types**: Feature tests (primary) in `tests/Feature/`, Unit tests in `tests/Unit/`
- **Commands**: `sail artisan make:test <Name>`, `sail artisan make:test <Name> --unit`
- **Database**: Tests use `RefreshDatabase`; `.env.testing` for test env
- **Coverage**: Use `--coverage` when needed

## Laravel AI / LLM

- **Laravel Boost** (dev, required): MCP server, AI guidelines, and documentation API for Cursor/Claude. Install: `composer require laravel/boost --dev`, then `php artisan boost:install`. Provides app introspection, database tools, route inspection, Tinker, and version-aware Laravel docs. Run `php artisan boost:update` to refresh guidelines.
- **LLM integration** (app): `openai-php/laravel` or similar for calling LLM APIs from the app
- **Testing LLM code**: Use `OpenAI::fake()` (or equivalent) in tests; never call real APIs in tests

## Project Structure

```
app/
├── Http/Controllers/
├── Models/
├── Services/          # If used
routes/
├── web.php
├── api.php
database/
├── migrations/
tests/
├── Feature/           # Primary tests
├── Unit/
```

## Commands Quick Reference

| Command | Purpose |
|---------|---------|
| `sail up -d` | Start Sail containers |
| `sail artisan migrate` | Run migrations |
| `sail test` | Run test suite |
| `sail artisan make:test X` | Create feature test |
| `sail artisan make:test X --unit` | Create unit test |
| `sail artisan make:controller X` | Create controller |
| `sail artisan make:model X -m` | Create model + migration |
| `sail artisan boost:install` | Install Laravel Boost (after `composer require laravel/boost --dev`) |
| `sail artisan boost:update` | Refresh Boost guidelines and resources |

## Conventions

- TDD: No production code without a failing test first
- Prefer Pest syntax when using Pest
- Use Form Requests for validation
- Use Eloquent conventions (snake_case DB, camelCase PHP)

## Key Files

- `compose.yaml` - Sail Docker config
- `phpunit.xml` - Test config
- `routes/web.php`, `routes/api.php` - Routes
- `.env.testing` - Test environment (optional)
- `.github/workflows/tests.yml` - GitHub Actions: builds frontend, runs Pest test suite on push/PR
