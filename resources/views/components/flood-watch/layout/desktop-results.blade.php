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
    'mapCenter',
    'riverLevels',
    'floods',
    'incidents',
    'hasUserLocation',
    'routeGeometry',
    'routeKey',
    'forecast',
    'weather',
    'assistantResponse',
    'wirePoll' => false,
])

<div
    class="space-y-6 scroll-smooth"
    id="results"
    @if ($wirePoll) wire:poll.900s="search" @endif
>
    <x-flood-watch.status.mobile-summary-bar
        :floods="$floods"
        :incidents="$incidents"
        :last-checked="$lastChecked"
    />

    {{-- Row 1: Left = Risk + Route Check (stacked) | Right = Map --}}
    <div class="grid grid-cols-[1fr_1.5fr] gap-6">
        <div class="space-y-6">
            <x-flood-watch.results.risk-block-desktop
                :house-risk="$houseRisk"
                :roads-risk="$roadsRisk"
                :action-steps="$actionSteps"
                :has-danger-to-life="$hasDangerToLife"
            />
            <x-flood-watch.search.route-check
                :route-check-loading="$routeCheckLoading"
                :route-check-result="$routeCheckResult"
            />
            <x-flood-watch.results.flood-risk
                :floods="$floods"
                :has-user-location="$hasUserLocation"
            />
        </div>
        <div class="min-w-0">
            <x-flood-watch.results.flood-map
                :map-center="$mapCenter"
                :river-levels="$riverLevels"
                :floods="$floods"
                :incidents="$incidents"
                :has-user-location="$hasUserLocation"
                :last-checked="$lastChecked"
                :route-geometry="$routeGeometry"
                :route-key="$routeKey"
            />
        </div>
    </div>

    {{-- Row 2: 3 columns = Road Status | Forecast | River Levels --}}
    <div class="grid grid-cols-3 gap-6">
        <div class="min-w-0">
            <x-flood-watch.results.road-status :incidents="$incidents" />
        </div>
        <div class="min-w-0">
            <x-flood-watch.results.forecast :forecast="$forecast" />
        </div>
        <div class="min-w-0">
            <x-flood-watch.results.river-levels :river-levels="$riverLevels" />
        </div>
    </div>

    <x-flood-watch.results.weather :weather="$weather" />

    <x-flood-watch.results.summary :assistant-response="$assistantResponse" />
</div>
