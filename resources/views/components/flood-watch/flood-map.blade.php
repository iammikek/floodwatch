@props([
    'mapCenter' => null,
    'riverLevels' => [],
    'floods' => [],
    'incidents' => [],
    'hasUserLocation' => false,
    'lastChecked' => null,
    'routeGeometry' => null,
])

@if ($mapCenter)
<div id="map-section" wire:key="map-{{ $lastChecked ?? 'initial' }}-{{ $routeGeometry ? 'route' : 'no-route' }}" class="rounded-lg overflow-hidden border border-slate-200">
    <div
        class="flex flex-col"
        x-data="floodMap({ center: @js($mapCenter), stations: @js($riverLevels), floods: @js($floods), incidents: @js($incidents), hasUser: @js($hasUserLocation), routeGeometry: @js($routeGeometry), t: @js(['your_location' => __('flood-watch.map.your_location'), 'elevated_level' => __('flood-watch.map.elevated_level'), 'expected_level' => __('flood-watch.map.expected_level'), 'low_level' => __('flood-watch.map.low_level'), 'typical_range' => __('flood-watch.map.typical_range'), 'flood_warning' => __('flood-watch.dashboard.flood_warning'), 'flood_area' => __('flood-watch.dashboard.flood_area'), 'km_from_location' => __('flood-watch.dashboard.km_from_location'), 'road' => __('flood-watch.dashboard.road'), 'road_incident' => __('flood-watch.dashboard.road_incident')]) })"
        x-init="init()"
    >
        <div id="flood-map" class="h-72 sm:h-80 md:h-96 w-full bg-slate-100"></div>
        @if (count($incidents) > 0)
            <div class="px-3 py-2 bg-blue-50/50 border-t border-slate-200">
                <p class="text-xs font-medium text-blue-800 mb-1.5">{{ __('flood-watch.dashboard.road_incidents_on_map') }}</p>
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
