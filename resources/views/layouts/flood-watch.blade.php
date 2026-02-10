<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body>
        @auth
            <header class="bg-white border-b border-slate-200 px-4 py-2">
                <div class="max-w-2xl lg:max-w-6xl mx-auto flex justify-between items-center">
                    <a href="{{ url('/') }}" class="shrink-0 flex items-center">
                        <x-application-logo class="block h-4 w-auto fill-current text-slate-800" />
                    </a>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-slate-600">{{ Auth::user()->name }}</span>
                        @if (Auth::user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="text-sm text-blue-600 hover:text-blue-700">{{ __('Admin') }}</a>
                            <a href="{{ url('/pulse') }}" class="text-sm text-blue-600 hover:text-blue-700">{{ __('Pulse') }}</a>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="text-sm text-slate-600 hover:text-slate-800">{{ __('Profile') }}</a>
                        <form method="POST" action="{{ route('logout') }}" class="inline-flex items-center">
                            @csrf
                            <button type="submit" class="text-sm text-slate-600 hover:text-slate-800 p-0 border-0 bg-transparent cursor-pointer">{{ __('Log Out') }}</button>
                        </form>
                    </div>
                    </div>
                </div>
            </header>
        @endauth

        {{ $slot }}

        @livewireScripts
        <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('floodMap', (config) => ({
                ...config,
                map: null,
                esc(s) {
                    if (s == null || s === '') return '';
                    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                },
                userIcon() {
                    const title = this.t?.your_location || 'Your location';
                    return L.divIcon({
                        className: 'leaflet-div-icon flood-map-marker flood-map-marker-user',
                        html: '<span class=\'flood-map-marker-inner\' title=\'' + title.replace(/'/g, '&#39;') + '\'>📍</span>',
                        iconSize: [56, 56],
                        iconAnchor: [28, 28]
                    });
                },
                stationIcon(s) {
                    const status = s.levelStatus || 'unknown';
                    const type = s.stationType || 'river_gauge';
                    const statusClass = status === 'elevated' ? 'elevated' : status === 'low' ? 'low' : 'expected';
                    const icon = type === 'pumping_station' ? '⚙' : type === 'barrier' ? '🛡' : type === 'drain' ? '〰' : '💧';
                    const levelLabel = status === 'elevated' ? (this.t?.elevated_level || 'Elevated level') : status === 'expected' ? (this.t?.expected_level || 'Expected level') : status === 'low' ? (this.t?.low_level || 'Low level') : '';
                    return L.divIcon({
                        className: 'leaflet-div-icon flood-map-marker flood-map-marker-station ' + statusClass,
                        html: '<span class=\'flood-map-marker-inner\' title=\'' + levelLabel.replace(/'/g, '&#39;') + '\'>' + icon + '</span>',
                        iconSize: [52, 52],
                        iconAnchor: [26, 26]
                    });
                },
                stationPopup(s) {
                    const t = this.t || {};
                    let html = '<b>' + (s.station || '') + '</b><br>' + (s.river || '') + '<br>' + s.value + ' ' + (s.unit || 'm');
                    if (s.levelStatus === 'elevated') html += '<br><span style=\'color:#b91c1c;font-weight:600\'>↑ ' + (t.elevated_level || 'Elevated').replace(/ level$/, '') + '</span>';
                    else if (s.levelStatus === 'expected') html += '<br><span style=\'color:#1d4ed8\'>→ ' + (t.expected_level || 'Expected').replace(/ level$/, '') + '</span>';
                    else if (s.levelStatus === 'low') html += '<br><span style=\'color:#64748b\'>↓ ' + (t.low_level || 'Low').replace(/ level$/, '') + '</span>';
                    if (s.typicalRangeLow != null && s.typicalRangeHigh != null) {
                        const typical = (t.typical_range || 'Typical: :low–:high :unit').replace(':low', s.typicalRangeLow).replace(':high', s.typicalRangeHigh).replace(':unit', s.unit || 'm');
                        html += '<br><small>' + typical + '</small>';
                    }
                    return html;
                },
                floodIcon(f) {
                    const level = f.severityLevel || 0;
                    const isSevere = level === 1;
                    const title = this.t?.flood_warning || 'Flood warning';
                    return L.divIcon({
                        className: 'leaflet-div-icon flood-map-marker flood-map-marker-flood' + (isSevere ? ' severe' : ''),
                        html: '<span class=\'flood-map-marker-inner\' title=\'' + title.replace(/'/g, '&#39;') + '\'>' + (isSevere ? '🚨' : '⚠') + '</span>',
                        iconSize: [52, 52],
                        iconAnchor: [26, 26]
                    });
                },
                floodPopup(f) {
                    const t = this.t || {};
                    const floodArea = t.flood_area || 'Flood area';
                    const kmFrom = (t.km_from_location || ':distance km from your location').replace(':distance', f.distanceKm);
                    let html = '<b>' + (f.description || floodArea) + '</b><br><span style=\'color:#b91c1c;font-weight:600\'>' + (f.severity || '') + '</span>';
                    if (f.distanceKm != null) html += '<br><small>' + kmFrom + '</small>';
                    if (f.message) html += '<br><small>' + f.message.replace(/<[^>]*>/g, '').substring(0, 150) + (f.message.length > 150 ? '…' : '') + '</small>';
                    return html;
                },
                normalizeFloodPolygon(polygon) {
                    if (!polygon || typeof polygon !== 'object') return null;
                    if (polygon.type === 'FeatureCollection' && Array.isArray(polygon.features) && polygon.features.length > 0) {
                        return polygon;
                    }
                    if (polygon.type === 'Feature' && polygon.geometry) {
                        return { type: 'FeatureCollection', features: [polygon] };
                    }
                    return null;
                },
                floodPolygonStyle(f) {
                    const level = f.severityLevel || 0;
                    const isSevere = level === 1;
                    return {
                        color: isSevere ? '#dc2626' : '#f59e0b',
                        fillColor: isSevere ? '#dc2626' : '#f59e0b',
                        fillOpacity: 0.3,
                        weight: 1,
                        opacity: 0.7
                    };
                },
                incidentIcon(i) {
                    const icon = i.icon || '🛣️';
                    const fallback = this.t?.road_incident || 'Road incident';
                    const title = this.esc(i.typeLabel || i.incidentType || i.managementType || fallback);
                    return L.divIcon({
                        className: 'leaflet-div-icon flood-map-marker flood-map-marker-incident',
                        html: '<span class=\'flood-map-marker-inner\' title=\'' + title + '\'>' + icon + '</span>',
                        iconSize: [52, 52],
                        iconAnchor: [26, 26]
                    });
                },
                incidentPopup(i) {
                    const icon = i.icon || '🛣️';
                    const road = this.t?.road || 'Road';
                    let html = '<span style="font-size:1.1em">' + icon + '</span> <b>' + this.esc(i.road || road) + '</b>';
                    if (i.statusLabel || i.status) html += '<br>' + this.esc(i.statusLabel || i.status);
                    if (i.typeLabel || i.incidentType) html += '<br>' + this.esc(i.typeLabel || i.incidentType);
                    if (i.delayTime) html += '<br><small>' + this.esc(i.delayTime) + '</small>';
                    return html;
                },
                init() {
                    if (!this.center) return;
                    const lng = this.center.lng ?? this.center.long;
                    if (lng == null) return;
                    const addMarkers = (L) => {
                        if (this.hasUser) {
                            const loc = this.t?.your_location || 'Your location';
                            L.marker([this.center.lat, lng], { icon: this.userIcon() })
                                .addTo(this.map)
                                .bindPopup('<b>' + loc.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</b>');
                        }
                        (this.stations || []).forEach(s => {
                            const slng = s.lng ?? s.long;
                            if (s.lat != null && slng != null) {
                                L.marker([s.lat, slng], { icon: this.stationIcon(s) })
                                    .addTo(this.map)
                                    .bindPopup(this.stationPopup(s));
                            }
                        });
                        (() => {
                            const byArea = {};
                            (this.floods || []).forEach(f => {
                                const id = f.floodAreaID || (f.lat != null && (f.lng != null || f.long != null) ? [f.lat, f.lng ?? f.long].join(',') : null);
                                if (!id) return;
                                const existing = byArea[id];
                                const level = (f.severityLevel ?? 4);
                                if (!existing || level < (existing.severityLevel ?? 4)) byArea[id] = f;
                            });
                            const deduped = Object.values(byArea);
                            deduped.forEach(f => {
                                const geo = this.normalizeFloodPolygon(f.polygon);
                                if (geo) {
                                    const style = this.floodPolygonStyle(f);
                                    L.geoJSON(geo, {
                                        style: () => style,
                                        onEachFeature: (feature, layer) => {
                                            layer.bindPopup(this.floodPopup(f));
                                        }
                                    }).addTo(this.map);
                                }
                                const flng = f.lng ?? f.long;
                                if (f.lat != null && flng != null) {
                                    L.marker([f.lat, flng], { icon: this.floodIcon(f) })
                                        .addTo(this.map)
                                        .bindPopup(this.floodPopup(f));
                                }
                            });
                        })();
                        (this.incidents || []).forEach(i => {
                            const ilng = i.lng ?? i.long;
                            if (i.lat != null && ilng != null) {
                                L.marker([i.lat, ilng], { icon: this.incidentIcon(i) })
                                    .addTo(this.map)
                                    .bindPopup(this.incidentPopup(i));
                            }
                        });
                        if (this.routeGeometry && this.routeGeometry.length >= 2) {
                            const latLngs = this.routeGeometry.map(c => [c[1], c[0]]);
                            const routeLayer = L.polyline(latLngs, { color: '#2563eb', weight: 5, opacity: 0.8 });
                            routeLayer.addTo(this.map);
                            const hasSearchMarkers = this.hasUser || (this.floods && this.floods.length > 0) || (this.incidents && this.incidents.length > 0) || (this.stations && this.stations.length > 0);
                            if (!hasSearchMarkers) {
                                this.map.fitBounds(routeLayer.getBounds(), { padding: [20, 20], maxZoom: 12 });
                            }
                        }
                        this.map.invalidateSize();
                    };
                    this.fitToIncidents = () => {
                        if (!this.map || !this.incidents || this.incidents.length === 0) return;
                        const coords = this.incidents
                            .map(i => {
                                const lat = i.lat ?? i.latitude;
                                const lng = i.lng ?? i.long ?? i.longitude;
                                return (lat != null && lng != null) ? [lat, lng] : null;
                            })
                            .filter(Boolean);
                        if (coords.length === 0) return;
                        const L = window.L;
                        if (!L) return;
                        const bounds = L.latLngBounds(coords);
                        this.map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
                    };
                    this.$nextTick(() => {
                        const el = document.getElementById('flood-map');
                        if (!el) return;
                        (window.__loadLeaflet ? window.__loadLeaflet() : Promise.resolve(window.L)).then(async (L) => {
                            if (!L) return;
                            if (this.map) {
                                this.map.remove();
                                this.map = null;
                            }
                            this.map = L.map('flood-map', { zoomSnap: 0.5 }).setView([this.center.lat, lng], 13);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                                maxZoom: 19
                            }).addTo(this.map);
                            L.control.scale({ imperial: false }).addTo(this.map);
                            this.map.invalidateSize();
                            if (this.polygonsUrl && this.floods && this.floods.length > 0) {
                                const ids = this.floods.map(f => f.floodAreaID).filter(Boolean);
                                if (ids.length > 0) {
                                    try {
                                        const res = await fetch(this.polygonsUrl + '?ids=' + encodeURIComponent(ids.join(',')));
                                        if (res.ok) {
                                            const data = await res.json();
                                            this.floods = this.floods.map(f => {
                                                const poly = f.floodAreaID ? data[f.floodAreaID] : null;
                                                return poly ? { ...f, polygon: poly } : f;
                                            });
                                        }
                                    } catch (e) {}
                                }
                            }
                            (typeof requestIdleCallback !== 'undefined'
                                ? (cb) => requestIdleCallback(cb, { timeout: 100 })
                                : (cb) => setTimeout(cb, 100))(() => addMarkers(L));
                        });
                    });
                }
            }));
        });
        </script>
    </body>
</html>
