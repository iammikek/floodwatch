<div
    class="min-h-screen bg-slate-50 dark:bg-slate-900 p-6"
    x-data="{
        init() {
            const stored = localStorage.getItem('flood-watch-location');
            if (stored) {
                $wire.set('location', stored);
            }
            Livewire.on('search-completed', () => {
                const loc = $wire.location;
                if (loc) {
                    localStorage.setItem('flood-watch-location', loc);
                }
                try {
                    const results = {
                        assistantResponse: $wire.assistantResponse,
                        floods: $wire.floods,
                        incidents: $wire.incidents,
                        forecast: $wire.forecast,
                        weather: $wire.weather,
                        riverLevels: $wire.riverLevels,
                        mapCenter: $wire.mapCenter,
                        hasUserLocation: $wire.hasUserLocation,
                        lastChecked: $wire.lastChecked
                    };
                    localStorage.setItem('flood-watch-results', JSON.stringify(results));
                } catch (e) {}
            });
        }
    }"
    x-init="init()"
>
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white mb-6">
            Flood Watch
        </h1>
        <p class="text-slate-600 dark:text-slate-400 mb-6">
            South West flood and road status. Ask the assistant to check current conditions for Bristol, Somerset, Devon or Cornwall.
        </p>

        <div class="flex flex-wrap gap-3 mb-6">
            @php
                $hasResults = !$loading && $assistantResponse;
                $floodStatus = $hasResults ? (count($floods) > 0 ? count($floods) . ' ' . Str::plural('warning', count($floods)) : 'No alerts') : null;
                $roadStatus = $hasResults ? (count($incidents) > 0 ? count($incidents) . ' ' . Str::plural('incident', count($incidents)) : 'Clear') : null;
                $forecastStatus = $hasResults ? (!empty($forecast['england_forecast']) ? 'Available' : '—') : null;
                $weatherStatus = $hasResults ? (count($weather) > 0 ? count($weather) . ' days' : '—') : null;
            @endphp
            <a href="#flood-risk" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 hover:bg-amber-200 dark:hover:bg-amber-900/50 transition-colors {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                Flood Risk
                @if($floodStatus)
                    <span class="opacity-90">· {{ $floodStatus }}</span>
                @endif
            </a>
            <a href="#road-status" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                Road Status
                @if($roadStatus)
                    <span class="opacity-90">· {{ $roadStatus }}</span>
                @endif
            </a>
            <a href="#forecast" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-200 dark:hover:bg-emerald-900/50 transition-colors {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                5-Day Forecast
                @if($forecastStatus)
                    <span class="opacity-90">· {{ $forecastStatus }}</span>
                @endif
            </a>
            <a href="#weather" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400 hover:bg-sky-200 dark:hover:bg-sky-900/50 transition-colors {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                Weather
                @if($weatherStatus)
                    <span class="opacity-90">· {{ $weatherStatus }}</span>
                @endif
            </a>
        </div>

        <div class="mb-6">
            <label for="location" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                Location or postcode (optional)
            </label>
            <div class="flex gap-2">
                <input
                    type="text"
                    id="location"
                    wire:model="location"
                    placeholder="e.g. Langport, TA10 0, Bristol"
                    class="block flex-1 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                />
                <button
                    type="button"
                    wire:click="search"
                    wire:loading.attr="disabled"
                    @if($retryAfterTimestamp && !$this->canRetry()) disabled @endif
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="search">Check status</span>
                    <span wire:loading wire:target="search" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Searching...
                    </span>
                </button>
            </div>
        </div>

        @if ($error)
            <div
                class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm"
                @if($retryAfterTimestamp) wire:poll.1s="checkRetry" @endif
                x-data="{
                    retryAfter: @js($retryAfterTimestamp),
                    secondsLeft: 0,
                    init() {
                        if (this.retryAfter) {
                            const update = () => {
                                this.secondsLeft = Math.max(0, this.retryAfter - Math.floor(Date.now() / 1000));
                            };
                            update();
                            setInterval(update, 1000);
                        }
                    }
                }"
                x-init="init()"
            >
                <span>{{ $error }}</span>
                <span x-show="retryAfter && secondsLeft > 0" x-cloak x-transition> — Retry in <span x-text="secondsLeft"></span> seconds.</span>
                <span x-show="retryAfter && secondsLeft === 0" x-cloak x-transition> — You can retry now.</span>
            </div>
        @endif

        @if ($loading)
            <div class="flex items-center gap-3 p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700">
                <svg class="animate-spin h-6 w-6 text-blue-600 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-slate-600 dark:text-slate-400 text-sm" wire:stream.replace="searchStatus">
                    Starting...
                </p>
            </div>
        @endif

        @if (!$loading && $assistantResponse)
            <div class="space-y-6 scroll-smooth" id="results">
                @if ($lastChecked)
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Last checked: {{ \Carbon\Carbon::parse($lastChecked)->format('j M Y, g:i a') }}
                    </p>
                @endif

                @if ($mapCenter)
                    <div id="map-section" class="rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700">
                        <div
                            class="flex flex-col"
                            x-data="{
                                center: @js($mapCenter),
                                stations: @js($riverLevels),
                                floods: @js($floods),
                                hasUser: @js($hasUserLocation),
                                map: null,
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
                                init() {
                                    if (!this.center) return;
                                    this.$nextTick(() => {
                                        this.map = L.map('flood-map').setView([this.center.lat, this.center.long], 11);
                                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                            attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a>'
                                        }).addTo(this.map);
                                        if (this.hasUser) {
                                            L.marker([this.center.lat, this.center.long], { icon: this.userIcon() })
                                                .addTo(this.map)
                                                .bindPopup('<b>Your location</b>');
                                        }
                                        this.stations.forEach(s => {
                                            L.marker([s.lat, s.long], { icon: this.stationIcon(s) })
                                                .addTo(this.map)
                                                .bindPopup(this.stationPopup(s));
                                        });
                                        this.floods.forEach(f => {
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
                                    });
                                }
                            }"
                            x-init="init()"
                        >
                            <div id="flood-map" class="h-64 w-full bg-slate-100 dark:bg-slate-800"></div>
                            <div class="flex flex-wrap gap-x-4 gap-y-2 px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-400">
                                @if ($hasUserLocation)
                                    <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-user">📍</span> Your location</span>
                                @endif
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">💧</span> River gauge</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">⚙</span> Pumping station</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">🛡</span> Barrier</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">〰</span> Drain</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-flood">⚠</span> Flood warning</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-polygon" style="display:inline-block;width:12px;height:12px;background:#f59e0b;opacity:0.4;border:1px solid #f59e0b;border-radius:2px"></span> Flood area</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-elevated">●</span> Elevated</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-expected">●</span> Expected</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-low">●</span> Low</span>
                            </div>
                        </div>
                    </div>
                @endif

                <div id="flood-risk">
                    <h2 class="text-lg font-medium text-slate-900 dark:text-white mb-3">Flood warnings</h2>
                    @if (count($floods) > 0)
                        <ul class="space-y-3">
                            @foreach ($floods as $flood)
                                <li class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 text-left overflow-visible">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $flood['description'] ?? 'Flood area' }}</p>
                                    <p class="text-sm text-amber-600 dark:text-amber-400 mt-1">{{ $flood['severity'] ?? '' }}</p>
                                    @if (!empty($flood['distanceKm']) && $hasUserLocation)
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $flood['distanceKm'] }} km from your location</p>
                                    @endif
                                    @if (!empty($flood['timeRaised']) || !empty($flood['timeMessageChanged']))
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            @if (!empty($flood['timeRaised']))
                                                Raised: {{ \Carbon\Carbon::parse($flood['timeRaised'])->format('j M Y, g:i a') }}
                                            @endif
                                            @if (!empty($flood['timeMessageChanged']))
                                                @if (!empty($flood['timeRaised'])) · @endif
                                                Updated: {{ \Carbon\Carbon::parse($flood['timeMessageChanged'])->format('j M Y, g:i a') }}
                                            @endif
                                        </p>
                                    @endif
                                    @if (!empty($flood['message']))
                                        <div x-data="{ open: false }" class="mt-2">
                                            <button type="button" @click="open = !open" class="flex items-center gap-2 cursor-pointer text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300" aria-label="Toggle full message">
                                                <svg class="w-4 h-4 transition-transform duration-200" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                            <p x-show="open" x-cloak x-transition class="mt-2 text-sm text-slate-600 dark:text-slate-400 whitespace-pre-wrap break-words">{{ $flood['message'] }}</p>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">No active flood warnings.</p>
                    @endif
                </div>

                <div id="forecast">
                    <h2 class="text-lg font-medium text-slate-900 dark:text-white mb-3">5-day flood outlook</h2>
                    @if (count($forecast) > 0 && !empty($forecast['england_forecast']))
                        <div class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700">
                            <p class="text-slate-600 dark:text-slate-400">{{ $forecast['england_forecast'] }}</p>
                            @if (!empty($forecast['flood_risk_trend']))
                                <p class="text-sm text-slate-500 dark:text-slate-500 mt-2">
                                    Trend: @foreach ($forecast['flood_risk_trend'] as $day => $trend){{ ucfirst($day) }}: {{ $trend }}@if (!$loop->last) → @endif @endforeach
                                </p>
                            @endif
                            @if (!empty($forecast['issued_at']))
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Issued: {{ \Carbon\Carbon::parse($forecast['issued_at'])->format('j M Y, g:i') }}</p>
                            @endif
                        </div>
                    @else
                        <p class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">No forecast available.</p>
                    @endif
                </div>

                <div id="weather">
                    <h2 class="text-lg font-medium text-slate-900 dark:text-white mb-3">5-day weather forecast</h2>
                    @if (count($weather) > 0)
                        <div class="flex flex-nowrap gap-3 overflow-x-auto">
                            @foreach ($weather as $day)
                                <div class="flex-1 min-w-[7rem] p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 text-center">
                                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">
                                        {{ \Carbon\Carbon::parse($day['date'])->format('D j M') }}
                                    </p>
                                    <p class="text-3xl my-2" title="{{ $day['description'] ?? '' }}">{{ $day['icon'] ?? '🌤️' }}</p>
                                    <p class="text-slate-900 dark:text-white font-semibold">{{ round($day['temp_max'] ?? 0) }}° / {{ round($day['temp_min'] ?? 0) }}°</p>
                                    @if (($day['precipitation'] ?? 0) > 0)
                                        <p class="text-sm text-sky-600 dark:text-sky-400 mt-1">💧 {{ round($day['precipitation'], 1) }} mm</p>
                                    @endif
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $day['description'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">No weather data available.</p>
                    @endif
                </div>

                <div id="road-status">
                    <h2 class="text-lg font-medium text-slate-900 dark:text-white mb-3">Road status</h2>
                    @if (count($incidents) > 0)
                        <ul class="space-y-3">
                            @foreach ($incidents as $incident)
                                <li class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $incident['road'] ?? 'Road' }}</p>
                                    @if (!empty($incident['status']))
                                        <p class="text-sm text-blue-600 dark:text-blue-400 mt-1">{{ $incident['status'] }}</p>
                                    @endif
                                    @if (!empty($incident['incidentType']))
                                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ $incident['incidentType'] }}</p>
                                    @endif
                                    @if (!empty($incident['delayTime']))
                                        <p class="text-sm text-slate-500 dark:text-slate-500 mt-1">Delay: {{ $incident['delayTime'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">Roads clear.</p>
                    @endif
                </div>

                <div class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 prose prose-slate dark:prose-invert max-w-none">
                    <h2 class="text-lg font-medium text-slate-900 dark:text-white mb-2">Summary</h2>
                    {!! Str::markdown($assistantResponse) !!}
                </div>
            </div>
        @elseif (!$loading && !$error)
            <p class="text-slate-500 dark:text-slate-400 text-sm">Enter a location or postcode, or click "Check status" to get flood and road data for the South West.</p>
        @endif

        <footer class="mt-12 pt-6 border-t border-slate-200 dark:border-slate-700">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                An <a href="https://automica.io" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">automica labs</a> project.
                Data: Environment Agency flood and river level data from the
                <a href="https://environment.data.gov.uk/flood-monitoring/doc/reference" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">Real-Time data API</a>
                (Open Government Licence).
                Weather from <a href="https://open-meteo.com/" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">Open-Meteo</a> (CC-BY 4.0).
                Geocoding by <a href="https://postcodes.io" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">postcodes.io</a> and <a href="https://nominatim.openstreetmap.org" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">OpenStreetMap Nominatim</a>.
            </p>
        </footer>
    </div>
</div>
