@props([
    'incidents' => [],
])

<div id="road-status">
    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.road_status') }}</h2>
    @if (count($incidents) > 0)
        <ul class="divide-y divide-slate-200 bg-white">
            @foreach ($incidents as $incident)
                <li class="p-4">
                    <p class="font-medium text-slate-900 flex items-center gap-2">
                        <span class="text-lg"
                              title="{{ $incident['typeLabel'] ?? __('flood-watch.dashboard.road_incident') }}">{{ $incident['icon'] ?? '🛣️' }}</span>
                        {{ $incident['road'] ?? __('flood-watch.dashboard.road') }}
                        @if (!empty($incident['statusLabel']))
                            <span class="text-sm text-blue-600 mt-1">{{ $incident['statusLabel'] }}</span>
                        @endif
                        @if (!empty($incident['typeLabel']))
                            <span class="text-sm text-slate-600 mt-1">{{ $incident['typeLabel'] }}</span>
                        @endif
                    </p>

                    @if (!empty($incident['delayTime']))
                        <p class="text-sm text-slate-500 mt-1">{{ __('flood-watch.dashboard.delay') }}
                            : {{ $incident['delayTime'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <p class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.roads_clear') }}</p>
    @endif
</div>
