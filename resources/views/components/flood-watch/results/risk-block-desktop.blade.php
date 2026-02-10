@props([
    'houseRisk' => 'clear',
    'roadsRisk' => 'clear',
    'actionSteps' => [],
    'hasDangerToLife' => false,
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
    $actionLabels = [
        'deploy_defences' => __('flood-watch.dashboard.action_deploy_defences'),
        'avoid_routes' => __('flood-watch.dashboard.action_avoid_routes'),
        'monitor_updates' => __('flood-watch.dashboard.action_monitor_updates'),
        'none' => __('flood-watch.dashboard.action_none'),
    ];
@endphp

<div id="your-risk">
    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">{{ __('flood-watch.dashboard.your_risk') }}</h2>
    <div class="p-4 bg-white shadow-sm border border-slate-200 space-y-1.5 text-sm">
        <p class="text-slate-700"><span class="font-medium">{{ __('flood-watch.dashboard.flood_risk') }}:</span> {{ $houseLabel }}</p>
        <p class="text-slate-700"><span class="font-medium">{{ __('flood-watch.dashboard.road_status') }}:</span> {{ $roadsLabel }}</p>
        @foreach ($actionSteps as $step)
            <p class="text-slate-600">{{ $actionLabels[$step] ?? $step }}</p>
        @endforeach
    </div>
    @if ($hasDangerToLife)
        <div class="mt-3 p-3 bg-red-50 border-2 border-red-500">
            <p class="text-xs font-bold text-red-800 mb-2">🆘 {{ __('flood-watch.dashboard.emergency_title') }}</p>
            <ul class="list-disc list-inside space-y-0.5 text-xs text-red-800">
                <li>{{ __('flood-watch.dashboard.emergency_999') }}</li>
                <li>{{ __('flood-watch.dashboard.emergency_floodline') }}</li>
                <li>{{ __('flood-watch.dashboard.emergency_move') }}</li>
            </ul>
        </div>
    @endif
</div>
