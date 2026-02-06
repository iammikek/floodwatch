@props([
    'riverLevels' => [],
    'assistantResponse' => null,
    'incidents' => [],
    'weather' => [],
])

<div class="flex-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 min-h-0 overflow-hidden grid-rows-4 sm:grid-rows-2 lg:grid-rows-1">
    <x-flood-watch.status-grid-card :title="__('flood-watch.dashboard.status_grid_hydrological')">
        <p class="text-slate-600 text-sm">
            @php
                $elevatedCount = count(array_filter($riverLevels, fn ($r) => ($r['levelStatus'] ?? '') === 'elevated'));
            @endphp
            @if (count($riverLevels) > 0)
                @if ($elevatedCount > 0)
                    {{ __('flood-watch.dashboard.status_grid_stations_elevated', ['count' => $elevatedCount]) }}
                @else
                    {{ count($riverLevels) }} {{ __('flood-watch.dashboard.stations') }}
                @endif
            @else
                {{ __('flood-watch.dashboard.status_grid_no_data') }}
            @endif
        </p>
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
