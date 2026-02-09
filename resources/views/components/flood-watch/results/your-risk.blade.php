@props([
    'houseRisk' => 'clear',
    'roadsRisk' => 'clear',
])

@php
    $houseLabel = $houseRisk === 'at_risk'
        ? __('flood-watch.dashboard.house_risk_at_risk')
        : __('flood-watch.dashboard.house_risk_clear');
    $roadsLabel = match ($roadsRisk) {
        'closed' => __('flood-watch.dashboard.roads_risk_closed'),
        'delays' => __('flood-watch.dashboard.roads_risk_delays'),
        default => __('flood-watch.dashboard.roads_risk_clear'),
    };
@endphp

<div id="your-risk">
    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.your_risk') }}</h2>
    <div class="space-y-2">
        <div class="p-4 rounded-lg bg-white shadow-sm border border-slate-200">
            <p class="text-sm font-medium text-slate-700">{{ __('flood-watch.dashboard.flood_risk') }}</p>
            <p class="text-slate-600">{{ $houseLabel }}</p>
        </div>
        <div class="p-4 rounded-lg bg-white shadow-sm border border-slate-200">
            <p class="text-sm font-medium text-slate-700">{{ __('flood-watch.dashboard.road_status') }}</p>
            <p class="text-slate-600">{{ $roadsLabel }}</p>
        </div>
    </div>
</div>
