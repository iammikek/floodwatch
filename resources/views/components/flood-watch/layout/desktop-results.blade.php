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
    <x-flood-watch.status.results-header
        :last-checked="$lastChecked"
        :auto-refresh-enabled="$autoRefreshEnabled"
        :retry-after-timestamp="$retryAfterTimestamp"
        :can-retry="$canRetry"
    />

    {{-- Row 1: Risk (left) | Route Check (right) --}}
    <div class="grid grid-cols-2 gap-6">
        <div>
            <x-flood-watch.results.risk-block-desktop
                :house-risk="$houseRisk"
                :roads-risk="$roadsRisk"
                :action-steps="$actionSteps"
                :has-danger-to-life="$hasDangerToLife"
            />
        </div>
        <div class="min-w-0">
            <x-flood-watch.search.route-check
                :route-check-loading="$routeCheckLoading"
                :route-check-result="$routeCheckResult"
            />
        </div>
    </div>

    {{-- Row 2: Map (left, larger) | Flood Warnings (right) --}}
    <div class="grid grid-cols-[1.5fr_1fr] gap-6">
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
        <div class="min-w-0">
            <x-flood-watch.results.flood-risk
                :floods="$floods"
                :has-user-location="$hasUserLocation"
            />
        </div>
    </div>

    <x-flood-watch.results.forecast :forecast="$forecast" />

    <x-flood-watch.results.weather :weather="$weather" />

    <x-flood-watch.results.road-status :incidents="$incidents" />

    <x-flood-watch.results.summary :assistant-response="$assistantResponse" />
</div>
