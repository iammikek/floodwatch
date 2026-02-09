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

<div id="your-risk" class="space-y-6">
    <div class="p-4 border-b border-slate-200">
        <p class="text-xs font-semibold text-slate-500 uppercase">{{ __('flood-watch.dashboard.your_risk') }}</p>
        <p class="text-sm mt-1 {{ $houseRisk === 'at_risk' ? 'text-amber-700 font-medium' : 'text-slate-600' }}">{{ __('flood-watch.dashboard.house_label') }}: {{ $houseLabel }}</p>
        <p class="text-sm mt-1 text-slate-600">{{ __('flood-watch.dashboard.roads_label') }}: {{ $roadsLabel }}</p>
    </div>
    <div id="action-steps" class="p-4 border-b border-slate-200">
        <p class="text-xs font-semibold text-slate-500 uppercase">{{ __('flood-watch.dashboard.action_steps') }}</p>
        <ul class="text-sm mt-2 space-y-1 text-slate-700 list-disc list-inside">
            @foreach ($actionSteps as $step)
                <li>{{ $actionLabels[$step] ?? $step }}</li>
            @endforeach
        </ul>
    </div>
    @if ($hasDangerToLife)
        <x-flood-watch.results.danger-to-life />
    @endif
</div>
