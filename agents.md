# AI Agents Quick Guide

This document is intentionally minimal to reduce context size and credit usage.

**Project**
- Flood Watch correlates Environment Agency flood data with National Highways road status across the South West (Somerset, Bristol, Devon, Cornwall).
- Laravel 12, PHP 8.2+, Sail, Livewire 4, Pest/PHPUnit, OpenAI via openai-php/laravel.

**LLM Usage**
- Default model: gpt-4o-mini.
- Tools: GetFloodData, GetHighwaysIncidents, GetFloodForecast, GetRiverLevels.
- Tests must fake the OpenAI client; never call real APIs.

**Run**
- sail up -d
- sail test

**More Docs**
- See docs/ARCHITECTURE.md and docs/PLAN.md for details.
