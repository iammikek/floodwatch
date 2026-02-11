@props([
    'mapCenter' => null,
    'riverLevels' => [],
    'floods' => [],
    'incidents' => [],
    'hasUserLocation' => false,
    'lastChecked' => null,
    'routeGeometry' => null,
    'routeKey' => null,
])

@php
    $polygonsUrl = route('flood-watch.polygons');
    $riverLevelsUrl = route('flood-watch.river-levels');
    $tileUrl = config('flood-watch.map.tile_url');
    $tileAttribution = config('flood-watch.map.tile_attribution');
    $tileLayers = config('flood-watch.map.tile_layers', []);
@endphp
@if ($mapCenter)
<div id="map-section" wire:key="map-{{ $lastChecked ?? 'initial' }}-{{ $routeKey ?? ($routeGeometry ? 'route' : 'no-route') }}" class="overflow-hidden border border-slate-200">
    <div
        class="flex flex-col"
        x-data="floodMap({ center: @js($mapCenter), stations: @js($riverLevels), floods: @js($floods), incidents: @js($incidents), hasUser: @js($hasUserLocation), routeGeometry: @js($routeGeometry), polygonsUrl: @js($polygonsUrl), riverLevelsUrl: @js($riverLevelsUrl), tileUrl: @js($tileUrl), tileAttribution: @js($tileAttribution), tileLayers: @js($tileLayers), t: @js(['your_location' => __('flood-watch.map.your_location'), 'elevated_level' => __('flood-watch.map.elevated_level'), 'expected_level' => __('flood-watch.map.expected_level'), 'low_level' => __('flood-watch.map.low_level'), 'typical_range' => __('flood-watch.map.typical_range'), 'flood_warning' => __('flood-watch.dashboard.flood_warning'), 'flood_area' => __('flood-watch.dashboard.flood_area'), 'km_from_location' => __('flood-watch.dashboard.km_from_location'), 'road' => __('flood-watch.dashboard.road'), 'road_incident' => __('flood-watch.dashboard.road_incident')]) })"
        x-init="init()"
    >
        <div class="relative">
            <div id="flood-map" class="h-72 sm:h-80 md:h-96 lg:h-[28rem] w-full bg-slate-100"></div>
            @if (count($tileLayers) > 0)
            <div class="absolute top-2 right-2 z-[1000]" @click.outside="mapStyleOpen = false">
                <button
                    type="button"
                    @click="mapStyleOpen = !mapStyleOpen"
                    class="flex items-center gap-1.5 px-2.5 py-1.5 bg-white/95 border border-slate-200 shadow-sm text-xs font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                    :aria-expanded="mapStyleOpen"
                >
                    <span x-text="tileLayers.find(l => l.id === selectedTileId)?.label || tileLayers[0]?.label || 'Map'">Light</span>
                    <svg class="w-3.5 h-3.5 shrink-0 transition-transform" :class="{ 'rotate-180': mapStyleOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div
                    x-show="mapStyleOpen"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute top-full right-0 mt-1 py-1 w-48 bg-white border border-slate-200 shadow-lg z-10"
                    style="display: none;"
                    @click="mapStyleOpen = false"
                >
                    @foreach ($tileLayers as $layer)
                    <button
                        type="button"
                        @click="setBaseLayer(@js($layer['url']), @js($layer['attribution']), @js($layer['id']))"
                        class="w-full text-left px-3 py-2 text-xs font-medium transition-colors flex items-center justify-between"
                        :class="selectedTileId === @js($layer['id']) ? 'bg-blue-50 text-blue-800' : 'text-slate-700 hover:bg-slate-50'"
                    >
                        <span>{{ $layer['label'] }}</span>
                        <span x-show="selectedTileId === @js($layer['id'])" class="text-blue-600">✓</span>
                    </button>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @if (count($incidents) > 0)
            <div class="px-3 py-2 bg-blue-50/50 border-t border-slate-200">
                <button
                    type="button"
                    @click="fitToIncidents()"
                    class="text-xs font-medium text-blue-800 mb-1.5 hover:text-blue-900 hover:underline cursor-pointer text-left"
                >
                    {{ __('flood-watch.dashboard.road_incidents_on_map') }}
                </button>
                <div class="flex flex-wrap gap-2">
                    @foreach ($incidents as $incident)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                            <span title="{{ $incident['typeLabel'] ?? '' }}">{{ $incident['icon'] ?? '🛣️' }}</span>
                            <span class="font-medium">{{ $incident['road'] ?? __('flood-watch.dashboard.road') }}</span>
                            @if (!empty($incident['status']))
                                <span>· {{ $incident['status'] }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="flex flex-wrap gap-x-4 gap-y-2 px-3 py-2 bg-slate-50 border-t border-slate-200 text-xs text-slate-600">
            @if ($hasUserLocation)
                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-user">📍</span> {{ __('flood-watch.dashboard.your_location') }}</span>
            @endif
            @if ($routeGeometry)
                <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-0.5 bg-blue-600 rounded"></span> {{ __('flood-watch.dashboard.route_line') }}</span>
            @endif
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">💧</span> {{ __('flood-watch.dashboard.river_gauge') }}</span>
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">⚙</span> {{ __('flood-watch.dashboard.pumping_station') }}</span>
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">🛡</span> {{ __('flood-watch.dashboard.barrier') }}</span>
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">〰</span> {{ __('flood-watch.dashboard.drain') }}</span>
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-marker-incident">🛣</span> {{ __('flood-watch.dashboard.road_incident') }}</span>
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-flood">⚠</span> {{ __('flood-watch.dashboard.flood_warning') }}</span>
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-polygon" style="display:inline-block;width:12px;height:12px;background:#f59e0b;opacity:0.4;border:1px solid #f59e0b;border-radius:2px"></span> {{ __('flood-watch.dashboard.flood_area') }}</span>
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-elevated">●</span> {{ __('flood-watch.dashboard.elevated') }}</span>
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-expected">●</span> {{ __('flood-watch.dashboard.expected') }}</span>
            <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-low">●</span> {{ __('flood-watch.dashboard.low') }}</span>
        </div>
    </div>
</div>
@endif
