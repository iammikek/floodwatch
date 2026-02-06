@props([
    'mapCenter' => null,
    'lastChecked' => null,
    'hasUserLocation' => false,
])

@php
    $mapCenter = $mapCenter ?? ['lat' => config('flood-watch.default_lat'), 'long' => config('flood-watch.default_long')];
@endphp
@if ($mapCenter)
    <div id="map-section" wire:key="map-section" class="rounded-lg overflow-hidden border border-slate-200">
        <div
            class="flex flex-col"
            x-data="floodMap({ center: @js($mapCenter), mapDataUrl: @js(route('api.v1.map-data')), stations: [], floods: [], incidents: [], infrastructure: @js(config('flood-watch.infrastructure_points', [])), riverBoundaryUrl: @js(config('flood-watch.environment_agency.river_boundary_geojson_url')), hasUser: @js($hasUserLocation), t: @js(['your_location' => __('flood-watch.map.your_location'), 'elevated_level' => __('flood-watch.map.elevated_level'), 'expected_level' => __('flood-watch.map.expected_level'), 'low_level' => __('flood-watch.map.low_level'), 'typical_range' => __('flood-watch.map.typical_range'), 'flood_warning' => __('flood-watch.dashboard.flood_warning'), 'flood_area' => __('flood-watch.dashboard.flood_area'), 'km_from_location' => __('flood-watch.dashboard.km_from_location'), 'raised' => __('flood-watch.dashboard.raised'), 'updated' => __('flood-watch.dashboard.updated'), 'no_recent_data' => __('flood-watch.dashboard.no_recent_data'), 'road' => __('flood-watch.dashboard.road'), 'road_incident' => __('flood-watch.dashboard.road_incident'), 'reservoir' => __('flood-watch.dashboard.reservoir')]) })"
            x-init="init()"
        >
            <div class="relative">
                <div id="flood-map" class="min-h-[40vh] h-72 sm:h-80 md:h-96 w-full bg-slate-100"></div>
                <div
                    x-show="mapLoading"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 flex items-center justify-center bg-slate-100/80 z-[1000] rounded-lg"
                    aria-hidden="true"
                >
                    <svg class="animate-spin h-10 w-10 text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-2 px-3 py-2 bg-slate-50 border-t border-slate-200 text-xs text-slate-600">
                @if ($hasUserLocation)
                    <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-user">📍</span> {{ __('flood-watch.dashboard.your_location') }}</span>
                @endif
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">💧</span> {{ __('flood-watch.dashboard.river_gauge') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">⚙</span> {{ __('flood-watch.dashboard.pumping_station') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">🛡</span> {{ __('flood-watch.dashboard.barrier') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">〰</span> {{ __('flood-watch.dashboard.drain') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-marker-incident">🛣</span> {{ __('flood-watch.dashboard.road_incident') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">🏞</span> {{ __('flood-watch.dashboard.reservoir') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-flood">⚠</span> {{ __('flood-watch.dashboard.flood_warning') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-polygon" style="display:inline-block;width:12px;height:12px;background:#f59e0b;opacity:0.4;border:1px solid #f59e0b;border-radius:2px"></span> {{ __('flood-watch.dashboard.flood_area') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-elevated">●</span> {{ __('flood-watch.dashboard.elevated') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-expected">●</span> {{ __('flood-watch.dashboard.expected') }}</span>
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-low">●</span> {{ __('flood-watch.dashboard.low') }}</span>
            </div>
        </div>
    </div>
@endif
