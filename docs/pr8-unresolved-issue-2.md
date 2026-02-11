# Issue: Missing Str import in Blade template

## Source
PR #8 Review Comment: https://github.com/iammikek/floodwatch/pull/8#discussion_r2790180074

## Status
Unresolved

## Description
This template calls `Str::markdown($body)` but `Str` isn't imported/qualified here (unlike other templates using `\\Illuminate\\Support\\Str::...`). If the `Str` alias isn't available in this runtime, this will error at render time.

## Location
- **File**: `resources/views/components/flood-watch/results/summary-collapsible-mobile.blade.php`
- **Line**: 26

## Suggested Fix
Prefer `\\Illuminate\\Support\\Str::markdown($body)` or add an explicit `@php use Illuminate\\Support\\Str; @endphp` for consistency.

```php
{!! \\Illuminate\\Support\\Str::markdown($body) !!}
```

## Impact
- Potential runtime error when rendering the mobile summary
- May cause page crashes for mobile users
- Inconsistent with other Blade templates in the codebase

## Labels
- bug
- priority: medium
- component: ui
- frontend
