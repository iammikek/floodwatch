@props([
    'steps' => [],
])

@php
    $labels = [
        'deploy_defences' => __('flood-watch.dashboard.action_deploy_defences'),
        'avoid_routes' => __('flood-watch.dashboard.action_avoid_routes'),
        'monitor_updates' => __('flood-watch.dashboard.action_monitor_updates'),
        'none' => __('flood-watch.dashboard.action_none'),
    ];
@endphp

<div id="action-steps">
    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.action_steps') }}</h2>
    <ul class="list-disc list-inside space-y-2 p-4 bg-white shadow-sm border border-slate-200">
        @foreach ($steps as $step)
            <li class="text-slate-600">{{ $labels[$step] ?? $step }}</li>
        @endforeach
    </ul>
</div>
