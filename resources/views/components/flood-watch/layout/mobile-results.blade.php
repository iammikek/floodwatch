@props([
    'lastChecked',
    'autoRefreshEnabled',
    'retryAfterTimestamp',
    'canRetry',
    'houseRisk',
    'roadsRisk',
    'actionSteps',
    'hasDangerToLife',
    'routeCheckLoading',
    'routeCheckResult',
    'floods',
    'hasUserLocation',
    'weather',
    'incidents',
    'assistantResponse',
    'wirePoll' => false,
])

<div
    class="space-y-6 scroll-smooth"
    id="results"
    @if ($wirePoll) wire:poll.900s="search" @endif
>
    <x-flood-watch.status.results-header
        :last-checked="$lastChecked"
        :auto-refresh-enabled="$autoRefreshEnabled"
        :retry-after-timestamp="$retryAfterTimestamp"
        :can-retry="$canRetry"
    />

    {{-- Risk block (stacked) --}}
    <x-flood-watch.results.risk-block-mobile
        :house-risk="$houseRisk"
        :roads-risk="$roadsRisk"
        :action-steps="$actionSteps"
        :has-danger-to-life="$hasDangerToLife"
    />

    {{-- Route Check --}}
    <x-flood-watch.search.route-check
        :route-check-loading="$routeCheckLoading"
        :route-check-result="$routeCheckResult"
    />

    {{-- Flood Warnings (no map on mobile) --}}
    <x-flood-watch.results.flood-risk
        :floods="$floods"
        :has-user-location="$hasUserLocation"
    />

    <x-flood-watch.status.mobile-summary-bar
        :floods="$floods"
        :incidents="$incidents"
        :last-checked="$lastChecked"
    />

    <nav class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600" aria-label="{{ __('flood-watch.dashboard.summary') }}">
        <a href="#road-status" class="underline hover:text-slate-800">{{ __('flood-watch.dashboard.road_status') }}</a>
        <span aria-hidden="true">·</span>
        <a href="#weather" class="underline hover:text-slate-800">{{ __('flood-watch.dashboard.weather_forecast') }}</a>
        <span aria-hidden="true">·</span>
        <a href="#map-section" class="underline hover:text-slate-800">{{ __('flood-watch.dashboard.river_levels') }}</a>
    </nav>

    <x-flood-watch.results.weather :weather="$weather" variant="mobile" />

    <x-flood-watch.results.road-status :incidents="$incidents" />

    <x-flood-watch.results.summary :assistant-response="$assistantResponse" />
</div>
