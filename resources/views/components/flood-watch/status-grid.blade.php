@props([
    'riverLevels' => [],
    'assistantResponse' => null,
    'incidents' => [],
    'weather' => [],
])

<div class="flex-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 min-h-0 overflow-hidden grid-rows-4 sm:grid-rows-2 lg:grid-rows-1">
    <x-flood-watch.status-grid-card :title="__('flood-watch.dashboard.status_grid_hydrological')">
        @php
            $elevatedStations = array_values(array_filter($riverLevels, fn ($r) => ($r['levelStatus'] ?? '') === 'elevated'));
            $elevatedCount = count($elevatedStations);
        @endphp
        @if (count($riverLevels) > 0)
            @if ($elevatedCount > 0)
                <p class="text-slate-600 text-sm font-medium">
                    {{ __('flood-watch.dashboard.status_grid_stations_elevated', ['count' => $elevatedCount]) }}
                </p>
                <ul class="mt-1.5 space-y-1 text-slate-600 text-xs">
                    @foreach ($elevatedStations as $station)
                        <li
                            class="flex items-center gap-1.5 cursor-pointer hover:bg-slate-100 rounded px-1.5 py-0.5 -mx-1.5 transition-colors"
                            role="button"
                            tabindex="0"
                            title="{{ __('flood-watch.dashboard.focus_on_map') }}"
                            data-lat="{{ $station['lat'] ?? '' }}"
                            data-long="{{ $station['long'] ?? '' }}"
                            @click="const el = $event.currentTarget; const lat = parseFloat(el.dataset.lat), long = parseFloat(el.dataset.long); if (!isNaN(lat) && !isNaN(long)) { window.dispatchEvent(new CustomEvent('flood-map-focus-station', { detail: { lat, long } })); document.getElementById('map-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }"
                            @keydown.enter.prevent="$event.currentTarget.click()"
                            @keydown.space.prevent="$event.currentTarget.click()"
                        >
                            @if (! empty($station['trend']) && $station['trend'] !== 'unknown')
                                <span class="shrink-0" title="{{ __('flood-watch.dashboard.trend_' . $station['trend']) }}">
                                    @switch($station['trend'])
                                        @case('rising')
                                            ↑
                                            @break
                                        @case('falling')
                                            ↓
                                            @break
                                        @default
                                            →
                                    @endswitch
                                </span>
                            @endif
                            <span>{{ $station['station'] ?? '' }}{{ ! empty($station['river']) ? ' (' . ($station['river']) . ')' : '' }}</span>
                            @if (isset($station['value'], $station['typicalRangeHigh']) && $station['value'] > $station['typicalRangeHigh'])
                                <span class="text-amber-600 shrink-0">{{ number_format($station['value'] - $station['typicalRangeHigh'], 1) }} m above</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-slate-600 text-sm">{{ count($riverLevels) }} {{ __('flood-watch.dashboard.stations') }}</p>
            @endif
        @else
            <p class="text-slate-600 text-sm">{{ __('flood-watch.dashboard.status_grid_no_data') }}</p>
        @endif
    </x-flood-watch.status-grid-card>

    <x-flood-watch.status-grid-card :title="__('flood-watch.dashboard.status_grid_infrastructure')">
        <p class="text-slate-600 text-sm">
            @if ($assistantResponse)
                @php
                    $monitoredTotal = count(config('flood-watch.incident_allowed_roads', [])) ?: config('flood-watch.status_grid_monitored_routes', 7);
                @endphp
                {{ __('flood-watch.dashboard.status_grid_incidents_on_routes', ['active' => count($incidents), 'total' => $monitoredTotal]) }}
            @else
                {{ __('flood-watch.dashboard.status_grid_no_data') }}
            @endif
        </p>
    </x-flood-watch.status-grid-card>

    <x-flood-watch.status-grid-card id="weather" :title="__('flood-watch.dashboard.status_grid_weather')">
        @if (count($weather) > 0)
            @php
                $precip48h = array_sum(array_column(array_slice($weather, 0, 2), 'precipitation'));
                $precip5d = array_sum(array_column($weather, 'precipitation'));
            @endphp
            @if ($precip48h > 0 || $precip5d > 0)
                <p class="text-sky-600 text-sm font-medium mb-2">
                    @if ($precip48h > 0)
                        {{ __('flood-watch.dashboard.status_grid_precipitation_48h', ['mm' => round($precip48h, 1)]) }}
                        @if ($precip5d > $precip48h)
                            <span class="text-slate-500 font-normal">({{ round($precip5d, 1) }} mm 5d)</span>
                        @endif
                    @else
                        {{ __('flood-watch.dashboard.status_grid_precipitation_5d', ['mm' => round($precip5d, 1)]) }}
                    @endif
                </p>
            @endif
            <div class="space-y-1.5 text-slate-600 text-sm">
                @foreach ($weather as $day)
                    <div class="flex items-center justify-between gap-2">
                        <span>{{ \Carbon\Carbon::parse($day['date'])->format('D') }}</span>
                        <span class="shrink-0" title="{{ $day['description'] ?? '' }}">{{ $day['icon'] ?? '🌤️' }}</span>
                        <span>{{ round($day['temp_max'] ?? 0) }}°</span>
                        @if (($day['precipitation'] ?? 0) > 0)
                            <span class="text-sky-600 text-xs">💧 {{ round($day['precipitation'], 1) }} mm</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-slate-600 text-sm">{{ __('flood-watch.dashboard.status_grid_no_data') }}</p>
        @endif
    </x-flood-watch.status-grid-card>


    <x-flood-watch.status-grid-card :title="__('flood-watch.dashboard.status_grid_ai_advisory')">
        <p class="text-slate-600 text-sm italic">
            @if ($assistantResponse)
                {{ strip_tags(Str::markdown($assistantResponse)) }}
            @else
                {{ __('flood-watch.dashboard.status_grid_prompt') }}
            @endif
        </p>
    </x-flood-watch.status-grid-card>
</div>
