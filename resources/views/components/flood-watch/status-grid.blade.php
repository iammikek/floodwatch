@props([
    'mapCenter' => null,
    'mapDataUrl' => null,
    'riverLevels' => [],
    'assistantResponse' => null,
    'weather' => [],
])

<div class="flex-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 min-h-0 overflow-hidden grid-rows-3 sm:grid-rows-2 lg:grid-rows-1">
    @php
        $hasMapDataFetch = $mapCenter && $mapDataUrl;
    @endphp
    @if ($hasMapDataFetch)
    <div
        x-data="{
            riverLevels: [],
            floods: [],
            loading: true,
            elevatedFormat: @js(__('flood-watch.dashboard.status_grid_stations_elevated', ['count' => '__COUNT__'])),
            floodWarningsFormat: @js(__('flood-watch.dashboard.status_grid_flood_warnings_count', ['count' => '__COUNT__'])),
            stationsLabel: @js(__('flood-watch.dashboard.stations')),
            noDataLabel: @js(__('flood-watch.dashboard.status_grid_no_data')),
            focusTitle: @js(__('flood-watch.dashboard.focus_on_map')),
            riverLevelsUrl: @js(route('api.v1.river-levels')),
            async init() {
                const center = @js($mapCenter);
                const url = @js($mapDataUrl);
                const params = '?lat=' + encodeURIComponent(center.lat) + '&long=' + encodeURIComponent(center.long);
                try {
                    const [mapRes, rlRes] = await Promise.all([
                        fetch(url + params),
                        fetch(this.riverLevelsUrl + params)
                    ]);
                    if (mapRes.ok) {
                        const json = await mapRes.json();
                        const d = json.data || {};
                        this.floods = d.floods || [];
                        this.riverLevels = d.riverLevels || [];
                    }
                    if (this.riverLevels.length === 0 && rlRes.ok) {
                        const rlJson = await rlRes.json();
                        const rlData = rlJson.data;
                        this.riverLevels = Array.isArray(rlData) ? rlData.map(r => r.attributes || r) : [];
                    }
                } catch (e) {}
                this.loading = false;
            },
            elevatedCount() { return this.riverLevels.filter(r => (r.levelStatus || '') === 'elevated'); }
        }"
        x-init="init()"
        class="contents"
    >
    @endif
    <x-flood-watch.status-grid-card :title="__('flood-watch.dashboard.status_grid_hydrological')">
        @if ($hasMapDataFetch)
            <template x-if="loading">
                <p class="text-slate-500 text-sm" x-text="noDataLabel"></p>
            </template>
            <template x-if="!loading && (riverLevels.length > 0 || floods.length > 0)">
                <div class="space-y-1.5">
                    <template x-if="floods.length > 0">
                        <div>
                            <p class="text-slate-600 text-sm font-medium" x-text="floodWarningsFormat.replace('__COUNT__', floods.length)"></p>
                            <ul class="mt-1 space-y-0.5 text-slate-600 text-xs">
                                <template x-for="(flood, i) in floods" :key="(flood.description || '') + (flood.lat || '') + i">
                                    <li
                                        class="flex items-center gap-1.5 rounded px-1.5 py-0.5 -mx-1.5 cursor-pointer hover:bg-slate-100 transition-colors border-l-4"
                                        :class="{
                                            'border-l-red-500 bg-red-50/50': flood.severityLevel === 1,
                                            'border-l-amber-500 bg-amber-50/50': flood.severityLevel === 2,
                                            'border-l-slate-300 bg-slate-50/50': flood.severityLevel === 3,
                                            'border-l-transparent': !flood.severityLevel || flood.severityLevel > 3
                                        }"
                                        role="button"
                                        tabindex="0"
                                        :title="focusTitle"
                                        :data-lat="flood.lat || ''"
                                        :data-long="flood.long || ''"
                                        @click="const el = $event.currentTarget; const lat = parseFloat(el.dataset.lat), long = parseFloat(el.dataset.long); if (!isNaN(lat) && !isNaN(long)) { window.dispatchEvent(new CustomEvent('flood-map-focus-station', { detail: { lat, long } })); document.getElementById('map-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }"
                                        @keydown.enter.prevent="$event.currentTarget.click()"
                                        @keydown.space.prevent="$event.currentTarget.click()"
                                    >
                                        <span class="shrink-0" x-text="(flood.severityLevel === 1) ? '🚨' : '⚠'"></span>
                                        <span class="truncate" x-text="flood.description || 'Flood area'"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                    <template x-if="riverLevels.length > 0">
                        <div>
                            <template x-if="elevatedCount().length > 0">
                                <div>
                                    <p class="text-slate-600 text-sm font-medium" x-text="elevatedFormat.replace('__COUNT__', elevatedCount().length)"></p>
                                    <ul class="mt-1 space-y-0.5 text-slate-600 text-xs">
                                        <template x-for="station in elevatedCount()" :key="(station.station || '') + (station.lat || '')">
                                            <li
                                                class="flex items-center gap-1.5 cursor-pointer hover:bg-slate-100 rounded px-1.5 py-0.5 -mx-1.5 transition-colors"
                                                role="button"
                                                tabindex="0"
                                                :title="focusTitle"
                                                :data-lat="station.lat || ''"
                                                :data-long="station.long || ''"
                                                @click="const el = $event.currentTarget; const lat = parseFloat(el.dataset.lat), long = parseFloat(el.dataset.long); if (!isNaN(lat) && !isNaN(long)) { window.dispatchEvent(new CustomEvent('flood-map-focus-station', { detail: { lat, long } })); document.getElementById('map-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }"
                                                @keydown.enter.prevent="$event.currentTarget.click()"
                                                @keydown.space.prevent="$event.currentTarget.click()"
                                            >
                                                <span x-show="station.trend && station.trend !== 'unknown'" x-text="station.trend === 'rising' ? '↑' : station.trend === 'falling' ? '↓' : '→'"></span>
                                                <span x-text="(station.station || '') + (station.river ? ' (' + station.river + ')' : '')"></span>
                                                <span x-show="station.value != null && station.typicalRangeHigh != null && station.value > station.typicalRangeHigh" class="text-amber-600 shrink-0" x-text="(station.value - station.typicalRangeHigh).toFixed(1) + ' m above'"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                            <template x-if="riverLevels.length > 0 && elevatedCount().length === 0">
                                <p class="text-slate-600 text-sm" x-text="riverLevels.length + ' ' + stationsLabel"></p>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!loading && riverLevels.length === 0 && floods.length === 0">
                <p class="text-slate-600 text-sm" x-text="noDataLabel"></p>
            </template>
        @else
            <p class="text-slate-600 text-sm">{{ __('flood-watch.dashboard.status_grid_no_data') }}</p>
        @endif
    </x-flood-watch.status-grid-card>

    @if ($hasMapDataFetch)
    </div>
    @endif

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
