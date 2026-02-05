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
                    return L.divIcon({
                        className: 'flood-map-marker flood-map-marker-user',
                        html: '<span class=\'flood-map-marker-inner\' title=\'Your location\'>📍</span>',
                        iconSize: [28, 28],
                        iconAnchor: [14, 14]
                    });
                },
                stationIcon(s) {
                    const status = s.levelStatus || 'unknown';
                    const type = s.stationType || 'river_gauge';
                    const statusClass = status === 'elevated' ? 'elevated' : status === 'low' ? 'low' : 'expected';
                    const icon = type === 'pumping_station' ? '⚙' : type === 'barrier' ? '🛡' : type === 'drain' ? '〰' : '💧';
                    const levelLabel = status === 'elevated' ? 'Elevated' : status === 'expected' ? 'Expected' : status === 'low' ? 'Low' : '';
                    return L.divIcon({
                        className: 'flood-map-marker flood-map-marker-station ' + statusClass,
                        html: '<span class=\'flood-map-marker-inner\' title=\'' + (levelLabel ? levelLabel + ' level' : '') + '\'>' + icon + '</span>',
                        iconSize: [26, 26],
                        iconAnchor: [13, 13]
                    });
                },
                stationPopup(s) {
                    let html = '<b>' + (s.station || '') + '</b><br>' + (s.river || '') + '<br>' + s.value + ' ' + (s.unit || 'm');
                    if (s.levelStatus === 'elevated') html += '<br><span style=\'color:#b91c1c;font-weight:600\'>↑ Elevated</span>';
                    else if (s.levelStatus === 'expected') html += '<br><span style=\'color:#1d4ed8\'>→ Expected</span>';
                    else if (s.levelStatus === 'low') html += '<br><span style=\'color:#64748b\'>↓ Low</span>';
                    if (s.typicalRangeLow != null && s.typicalRangeHigh != null) {
                        html += '<br><small>Typical: ' + s.typicalRangeLow + '–' + s.typicalRangeHigh + ' ' + (s.unit || 'm') + '</small>';
                    }
                    return html;
                },
                floodIcon(f) {
                    const level = f.severityLevel || 0;
                    const isSevere = level === 1;
                    return L.divIcon({
                        className: 'flood-map-marker flood-map-marker-flood' + (isSevere ? ' severe' : ''),
                        html: '<span class=\'flood-map-marker-inner\' title=\'Flood warning\'>' + (isSevere ? '🚨' : '⚠') + '</span>',
                        iconSize: [26, 26],
                        iconAnchor: [13, 13]
                    });
                },
                floodPopup(f) {
                    let html = '<b>' + (f.description || 'Flood area') + '</b><br><span style=\'color:#b91c1c;font-weight:600\'>' + (f.severity || '') + '</span>';
                    if (f.distanceKm != null) html += '<br><small>' + f.distanceKm + ' km from your location</small>';
                    if (f.message) html += '<br><small>' + f.message.replace(/<[^>]*>/g, '').substring(0, 150) + (f.message.length > 150 ? '…' : '') + '</small>';
                    return html;
                },
                floodPolygonStyle(f) {
                    const level = f.severityLevel || 0;
                    const isSevere = level === 1;
                    return {
                        color: isSevere ? '#dc2626' : '#f59e0b',
                        fillColor: isSevere ? '#dc2626' : '#f59e0b',
                        fillOpacity: 0.25,
                        weight: 2
                    };
                },
                incidentIcon(i) {
                    const icon = i.icon || '🛣️';
                    const title = this.esc(i.incidentType || i.managementType || 'Road incident');
                    return L.divIcon({
                        className: 'flood-map-marker flood-map-marker-incident',
                        html: '<span class=\'flood-map-marker-inner\' title=\'' + title + '\'>' + icon + '</span>',
                        iconSize: [26, 26],
                        iconAnchor: [13, 13]
                    });
                },
                incidentPopup(i) {
                    const icon = i.icon || '🛣️';
                    let html = '<span style="font-size:1.1em">' + icon + '</span> <b>' + this.esc(i.road || 'Road') + '</b>';
                    if (i.status) html += '<br>' + this.esc(i.status);
                    if (i.incidentType) html += '<br>' + this.esc(i.incidentType);
                    if (i.delayTime) html += '<br><small>' + this.esc(i.delayTime) + '</small>';
                    return html;
                },
                init() {
                    if (!this.center) return;
                    this.$nextTick(() => {
                        requestAnimationFrame(() => {
                            const el = document.getElementById('flood-map');
                            if (!el) return;
                            if (this.map) {
                                this.map.remove();
                                this.map = null;
                            }
                            this.map = L.map('flood-map').setView([this.center.lat, this.center.long], 11);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                            }).addTo(this.map);
                            if (this.hasUser) {
                                L.marker([this.center.lat, this.center.long], { icon: this.userIcon() })
                                    .addTo(this.map)
                                    .bindPopup('<b>Your location</b>');
                            }
                            (this.stations || []).forEach(s => {
                                if (s.lat != null && s.long != null) {
                                    L.marker([s.lat, s.long], { icon: this.stationIcon(s) })
                                        .addTo(this.map)
                                        .bindPopup(this.stationPopup(s));
                                }
                            });
                            (this.floods || []).forEach(f => {
                                if (f.polygon && f.polygon.features) {
                                    const style = this.floodPolygonStyle(f);
                                    L.geoJSON(f.polygon, {
                                        style: () => style,
                                        onEachFeature: (feature, layer) => {
                                            layer.bindPopup(this.floodPopup(f));
                                        }
                                    }).addTo(this.map);
                                }
                                if (f.lat != null && f.long != null) {
                                    L.marker([f.lat, f.long], { icon: this.floodIcon(f) })
                                        .addTo(this.map)
                                        .bindPopup(this.floodPopup(f));
                                }
                            });
                            (this.incidents || []).forEach(i => {
                                if (i.lat != null && i.long != null) {
                                    L.marker([i.lat, i.long], { icon: this.incidentIcon(i) })
                                        .addTo(this.map)
                                        .bindPopup(this.incidentPopup(i));
                                }
                            });
                            this.map.invalidateSize();
                        });
                    });
                }
            }));
        });
        </script>
    </body>
</html>
