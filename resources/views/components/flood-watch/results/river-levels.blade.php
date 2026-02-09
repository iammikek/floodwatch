@props([
    'riverLevels' => [],
])

<div id="river-levels">
    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.river_levels') }}</h2>
    @if (count($riverLevels) > 0)
        <ul class="space-y-2">
            @foreach ($riverLevels as $level)
                <li class="p-3 rounded-lg bg-white shadow-sm border border-slate-200">
                    <p class="font-medium text-slate-900">{{ $level['station'] ?? '' }}</p>
                    <p class="text-sm text-slate-600">{{ $level['river'] ?? '' }}</p>
                    <p class="text-sm mt-1">
                        <span class="font-medium">{{ $level['value'] ?? '—' }} {{ $level['unit'] ?? 'm' }}</span>
                        @if (!empty($level['levelStatus']) && $level['levelStatus'] !== 'unknown')
                            <span class="ml-2 text-xs {{ $level['levelStatus'] === 'elevated' ? 'text-red-600 font-medium' : ($level['levelStatus'] === 'expected' ? 'text-blue-600' : 'text-slate-500') }}">
                                {{ match($level['levelStatus']) {
                                    'elevated' => __('flood-watch.dashboard.elevated'),
                                    'expected' => __('flood-watch.dashboard.expected'),
                                    'low' => __('flood-watch.dashboard.low'),
                                    default => '',
                                } }}
                            </span>
                        @endif
                    </p>
                </li>
            @endforeach
        </ul>
    @else
        <p class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.no_river_levels') }}</p>
    @endif
</div>
