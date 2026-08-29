@props([
    'floods' => [],
    'incidents' => [],
    'riverLevels' => [],
    'routeCheckResult' => null,
])

@php
    $severityCounts = ['severe' => 0, 'warning' => 0, 'alert' => 0];
    foreach ($floods as $flood) {
        $level = isset($flood['severityLevel']) ? (int) $flood['severityLevel'] : 4;
        $label = strtolower(trim((string) ($flood['severity'] ?? '')));
        if ($level === 1 || str_starts_with($label, 'severe')) {
            $severityCounts['severe']++;
        } elseif ($level === 2 || $label === 'flood warning' || str_contains($label, 'warning')) {
            $severityCounts['warning']++;
        } elseif ($level === 3 || str_contains($label, 'alert')) {
            $severityCounts['alert']++;
        }
    }

    $elevatedStations = array_values(array_filter($riverLevels, fn (array $level) => ($level['levelStatus'] ?? null) === 'elevated'));
    $routeVerdict = is_array($routeCheckResult) ? (string) ($routeCheckResult['verdict'] ?? '') : '';
    $routeSummary = is_array($routeCheckResult) ? trim((string) ($routeCheckResult['summary'] ?? '')) : '';

    $headline = __('flood-watch.dashboard.watch_conditions_stable');
    if ($severityCounts['severe'] > 0) {
        $headline = __('flood-watch.dashboard.headline_severe_flooding');
    } elseif ($severityCounts['warning'] > 0 && in_array($routeVerdict, ['blocked', 'at_risk'], true)) {
        $headline = __('flood-watch.dashboard.headline_floods_and_route_risk');
    } elseif ($severityCounts['warning'] > 0 || $severityCounts['alert'] > 0) {
        $headline = __('flood-watch.dashboard.headline_flood_warnings');
    } elseif (count($elevatedStations) > 0) {
        $headline = __('flood-watch.dashboard.headline_elevated_rivers');
    } elseif (count($incidents) > 0) {
        $headline = __('flood-watch.dashboard.headline_road_disruption');
    }

    $routeLabel = match ($routeVerdict) {
        'blocked' => __('flood-watch.route_check.verdict_blocked'),
        'at_risk' => __('flood-watch.route_check.verdict_at_risk'),
        'delays' => __('flood-watch.route_check.verdict_delays'),
        'clear' => __('flood-watch.route_check.verdict_clear'),
        'error' => __('flood-watch.route_check.verdict_error'),
        default => __('flood-watch.dashboard.no_route_selected'),
    };
@endphp

<div class="grid gap-4 lg:grid-cols-4" id="corridor-risk">
    <section class="p-4 bg-white border border-slate-200 shadow-sm lg:col-span-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('flood-watch.dashboard.corridor_risk') }}</p>
        <h2 class="mt-2 text-lg font-semibold text-slate-900">{{ $headline }}</h2>
        <p class="mt-2 text-sm text-slate-600">
            {{ $routeSummary !== '' ? $routeSummary : __('flood-watch.dashboard.corridor_guidance_default') }}
        </p>
    </section>

    <section class="p-4 bg-white border border-slate-200 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('flood-watch.dashboard.flood_exposure') }}</p>
        <div class="mt-3 space-y-2 text-sm text-slate-700">
            <div class="flex items-center justify-between gap-3">
                <span>{{ __('flood-watch.dashboard.severe_warnings') }}</span>
                <span class="font-semibold text-red-700">{{ $severityCounts['severe'] }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
                <span>{{ __('flood-watch.dashboard.flood_warnings') }}</span>
                <span class="font-semibold text-amber-700">{{ $severityCounts['warning'] }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
                <span>{{ __('flood-watch.dashboard.flood_alerts') }}</span>
                <span class="font-semibold text-yellow-700">{{ $severityCounts['alert'] }}</span>
            </div>
        </div>
    </section>

    <section class="p-4 bg-white border border-slate-200 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('flood-watch.dashboard.current_route') }}</p>
        <div class="mt-3">
            <p class="text-2xl font-semibold text-slate-900">{{ $routeLabel }}</p>
            <p class="mt-2 text-sm text-slate-600">
                {{ count($elevatedStations) }}
                {{ trans_choice('flood-watch.dashboard.elevated_gauges_count', count($elevatedStations)) }}
            </p>
            <p class="mt-1 text-sm text-slate-600">
                {{ count($incidents) }}
                {{ trans_choice('flood-watch.dashboard.active_incidents_count', count($incidents)) }}
            </p>
        </div>
    </section>
</div>
