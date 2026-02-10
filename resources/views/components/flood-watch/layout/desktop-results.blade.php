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
    'floodsInView' => null,
    'incidentsInView' => null,
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

    {{-- Row 1: Your risk | Route check (same row) --}}
    <div class="grid grid-cols-2 gap-4">
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
    </div>

    {{-- AI summary / advice (reuses summary component) --}}
    @if ($assistantResponse !== null && $assistantResponse !== '')
        <x-flood-watch.results.summary :assistant-response="$assistantResponse" />
    @endif

    @php
        $floodsForList = $floodsInView ?? $floods;
        $incidentsForList = $incidentsInView ?? $incidents;
    @endphp
    {{-- Row 2: Map full width (wire:ignore so map is not recreated when viewport-filtered lists update; key so new search gets fresh map) --}}
    <div class="w-full min-w-0" wire:ignore wire:key="desktop-map-wrapper-{{ $lastChecked ?? 'initial' }}-{{ $routeKey ?? 'none' }}">
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

    {{-- Row 2: 3 columns = Flood Warnings | Road Status | Forecast (compact cards; favour visible map area) --}}
    @php
        $floodSummary = count($floodsForList) > 0
            ? collect($floodsForList)->map(fn ($f) => trim(($f['description'] ?? '') . (isset($f['severity']) ? ' (' . $f['severity'] . ')' : '')))->filter()->implode(', ')
            : __('flood-watch.dashboard.no_flood_warnings');
        $roadSummary = count($incidentsForList) > 0
            ? collect($incidentsForList)->map(fn ($i) => trim(($i['road'] ?? '') . (isset($i['statusLabel']) ? ' ' . $i['statusLabel'] : '')))->filter()->implode('; ')
            : __('flood-watch.dashboard.roads_clear');
        $forecastSummary = !empty($forecast['england_forecast'])
            ? $forecast['england_forecast']
            : __('flood-watch.dashboard.no_forecast');
    @endphp
    <div class="p-4 border-t border-slate-200 grid grid-cols-3 gap-4">
        <div class="p-3 bg-white border border-slate-200 min-h-0 flex flex-col max-h-48">
            <h4 class="text-xs font-semibold text-slate-500 shrink-0">{{ __('flood-watch.dashboard.flood_warnings') }}</h4>
            <p class="text-sm mt-1 overflow-y-auto min-h-0">{{ $floodSummary }}</p>
        </div>
        <div class="p-3 bg-white border border-slate-200 min-h-0 flex flex-col max-h-48">
            <h4 class="text-xs font-semibold text-slate-500 shrink-0">{{ __('flood-watch.dashboard.road_status') }}</h4>
            <p class="text-sm mt-1 overflow-y-auto min-h-0">{{ $roadSummary }}</p>
        </div>
        <div class="p-3 bg-white border border-slate-200 min-h-0 flex flex-col max-h-48">
            <h4 class="text-xs font-semibold text-slate-500 shrink-0">{{ __('flood-watch.dashboard.forecast_outlook') }}</h4>
            <p class="text-sm mt-1 overflow-y-auto min-h-0">{{ $forecastSummary }}</p>
        </div>
    </div>

    <x-flood-watch.results.weather :weather="$weather" />

    <x-flood-watch.results.flood-risk
        :floods="$floodsForList"
        :has-user-location="$hasUserLocation"
    />
</div>
