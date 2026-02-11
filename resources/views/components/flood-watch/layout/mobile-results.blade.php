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
    <x-flood-watch.status.mobile-summary-bar
        :floods="$floods"
        :incidents="$incidents"
        :last-checked="$lastChecked"
    />

    {{-- AI advice (Option B): collapsible teaser at top, expand for full summary --}}
    <x-flood-watch.results.summary-collapsible-mobile :assistant-response="$assistantResponse" />

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

    <x-flood-watch.section-jump-nav />

    <x-flood-watch.results.weather :weather="$weather" variant="mobile" />

    <x-flood-watch.results.road-status :incidents="$incidents" />

    {{-- Sticky summary bar (Option C): appears when scrolling, tap to open full AI advice --}}
    <x-flood-watch.results.summary-sticky-bar-mobile :assistant-response="$assistantResponse" />
</div>
