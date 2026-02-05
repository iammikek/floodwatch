<div
    class="min-h-screen bg-slate-50 dark:bg-slate-900 p-4 sm:p-6 pb-safe"
    x-data="{
        init() {
            const storedLoc = localStorage.getItem('flood-watch-location');
            if (storedLoc) {
                $wire.set('location', storedLoc);
            }
            const storedResults = localStorage.getItem('flood-watch-results');
            if (storedResults) {
                try {
                    const data = JSON.parse(storedResults);
                    $wire.restoreFromStorage(data);
                } catch (e) {}
            }
            Livewire.on('search-completed', () => {
                const loc = $wire.location;
                if (loc) {
                    localStorage.setItem('flood-watch-location', loc);
                }
                try {
                    const floods = ($wire.floods || []).map(f => {
                        const { polygon, ...rest } = f;
                        return rest;
                    });
                    const results = {
                        assistantResponse: $wire.assistantResponse,
                        floods: floods,
                        incidents: $wire.incidents || [],
                        forecast: $wire.forecast || [],
                        weather: $wire.weather || [],
                        riverLevels: $wire.riverLevels || [],
                        mapCenter: $wire.mapCenter,
                        hasUserLocation: $wire.hasUserLocation || false,
                        lastChecked: $wire.lastChecked
                    };
                    localStorage.setItem('flood-watch-results', JSON.stringify(results));
                } catch (e) {}
            });
        }
    }"
    x-init="init()"
>
    <div class="max-w-2xl mx-auto w-full">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white mb-6">
            Flood Watch
        </h1>
        <p class="text-slate-600 dark:text-slate-400 mb-6">
            Enter your location and we'll use AI to collate flood warnings, river levels, road incidents and forecasts into a single summary. We cross-reference Environment Agency flood data with National Highways road status so you can see how flooding affects travel in Bristol, Somerset, Devon and Cornwall.
        </p>

        <div class="flex flex-nowrap sm:flex-wrap gap-2 sm:gap-3 mb-6 overflow-x-auto pb-1 -mx-1 scrollbar-hide">
            @php
                $hasResults = !$loading && $assistantResponse;
                $floodStatus = $hasResults ? (count($floods) > 0 ? count($floods) . ' ' . Str::plural('warning', count($floods)) : 'No alerts') : null;
                $roadStatus = $hasResults ? (count($incidents) > 0 ? count($incidents) . ' ' . Str::plural('incident', count($incidents)) : 'Clear') : null;
                $forecastStatus = $hasResults ? (!empty($forecast['england_forecast']) ? 'Available' : '—') : null;
                $weatherStatus = $hasResults ? (count($weather) > 0 ? count($weather) . ' days' : '—') : null;
                $riverStatus = $hasResults ? (count($riverLevels) > 0 ? count($riverLevels) . ' stations' : '—') : null;
            @endphp
            <a href="#flood-risk" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 hover:bg-amber-200 dark:hover:bg-amber-900/50 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                Flood Risk
                @if($floodStatus)
                    <span class="opacity-90">· {{ $floodStatus }}</span>
                @endif
            </a>
            <a href="#road-status" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                Road Status
                @if($roadStatus)
                    <span class="opacity-90">· {{ $roadStatus }}</span>
                @endif
            </a>
            <a href="#forecast" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-200 dark:hover:bg-emerald-900/50 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                5-Day Forecast
                @if($forecastStatus)
                    <span class="opacity-90">· {{ $forecastStatus }}</span>
                @endif
            </a>
            <a href="#weather" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400 hover:bg-sky-200 dark:hover:bg-sky-900/50 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                Weather
                @if($weatherStatus)
                    <span class="opacity-90">· {{ $weatherStatus }}</span>
                @endif
            </a>
            <a href="#map-section" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                Map
                @if($riverStatus)
                    <span class="opacity-90">· {{ $riverStatus }}</span>
                @endif
            </a>
        </div>

        <div class="mb-6">
            <label for="location" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                Your location
            </label>
            <div class="flex flex-col sm:flex-row gap-2">
                <input
                    type="text"
                    id="location"
                    wire:model="location"
                    placeholder="e.g. Langport, TA10 0, Bristol"
                    class="block flex-1 min-h-[44px] rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base sm:text-sm"
                />
                <button
                    type="button"
                    wire:click="search"
                    wire:loading.attr="disabled"
                    @if($retryAfterTimestamp && !$this->canRetry()) disabled @endif
                    class="min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-3 sm:py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="search">Check status</span>
                    @if (!$assistantResponse)
                    <span wire:loading wire:target="search" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Searching...
                    </span>
                    @endif
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
                <div class="text-slate-600 dark:text-slate-400 text-sm space-y-1" wire:stream.replace="searchStatus">
                    Connecting...
                </div>
            </div>
        @endif

        @if (!$loading && $assistantResponse)
            <div
                class="space-y-6 scroll-smooth"
                id="results"
                @if (!$error && $autoRefreshEnabled) wire:poll.900s="search" @endif
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($lastChecked)
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Last checked: {{ \Carbon\Carbon::parse($lastChecked)->format('j M Y, g:i a') }}
                        </p>
                    @endif
                    <div
                        class="flex flex-wrap items-center gap-3"
                        x-data="{
                            lastChecked: @js($lastChecked),
                            nextRefreshAt: null,
                            minutesLeft: null,
                            init() {
                                this.update();
                                setInterval(() => this.update(), 60000);
                            },
                            update() {
                                if (!this.lastChecked) return;
                                const last = new Date(this.lastChecked);
                                if (isNaN(last.getTime())) return;
                                this.nextRefreshAt = new Date(last.getTime() + 15 * 60 * 1000);
                                const diff = this.nextRefreshAt - Date.now();
                                this.minutesLeft = Math.max(0, Math.ceil(diff / 60000));
                            }
                        }"
                        x-init="init()"
                    >
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model.live="autoRefreshEnabled"
                                class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm text-slate-600 dark:text-slate-400">Auto-refresh every 15 min</span>
                        </label>
                        <span
                            x-show="$wire.autoRefreshEnabled && lastChecked && minutesLeft !== null"
                            x-cloak
                            x-transition
                            class="text-sm text-slate-500 dark:text-slate-400"
                            x-text="minutesLeft > 0 ? 'Next refresh in ' + minutesLeft + ' min (at ' + nextRefreshAt.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}) + ')' : 'Refreshing...'"
                        ></span>
                        <button
                        type="button"
                        wire:click="search"
                        wire:loading.attr="disabled"
                        @if($retryAfterTimestamp && !$this->canRetry()) disabled @endif
                        class="min-h-[44px] min-w-[44px] inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="search">↻ Refresh</span>
                        <span wire:loading wire:target="search" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Refreshing...
                        </span>
                    </button>
                    </div>
                </div>

                @if ($mapCenter)
                    <div id="map-section" wire:key="map-{{ $lastChecked ?? 'initial' }}" class="rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700">
                        <div
                            class="flex flex-col"
                            x-data="floodMap({ center: @js($mapCenter), stations: @js($riverLevels), floods: @js($floods), incidents: @js($incidents), hasUser: @js($hasUserLocation) })"
                            x-init="init()"
                        >
                            <div id="flood-map" class="h-72 sm:h-80 md:h-96 w-full bg-slate-100 dark:bg-slate-800"></div>
                            @if (count($incidents) > 0)
                                <div class="px-3 py-2 bg-blue-50/50 dark:bg-blue-900/10 border-t border-slate-200 dark:border-slate-700">
                                    <p class="text-xs font-medium text-blue-800 dark:text-blue-300 mb-1.5">Road incidents on map area</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($incidents as $incident)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                                                <span title="{{ $incident['incidentType'] ?? $incident['managementType'] ?? '' }}">{{ $incident['icon'] ?? '🛣️' }}</span>
                                                <span class="font-medium">{{ $incident['road'] ?? 'Road' }}</span>
                                                @if (!empty($incident['status']))
                                                    <span>· {{ $incident['status'] }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="flex flex-wrap gap-x-4 gap-y-2 px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-400">
                                @if ($hasUserLocation)
                                    <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-legend-user">📍</span> Your location</span>
                                @endif
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">💧</span> River gauge</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">⚙</span> Pumping station</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">🛡</span> Barrier</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon">〰</span> Drain</span>
                                <span class="flex items-center gap-1.5"><span class="flood-map-legend-icon flood-map-marker-incident">🛣</span> Road incident</span>
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
                        <div class="flex flex-nowrap gap-3 overflow-x-auto pb-2 -mx-1 scrollbar-hide">
                            @foreach ($weather as $day)
                                <div class="flex-1 min-w-[6.5rem] sm:min-w-[7rem] p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 text-center shrink-0">
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
                                    <p class="font-medium text-slate-900 dark:text-white flex items-center gap-2">
                                        <span class="text-lg" title="{{ $incident['incidentType'] ?? $incident['managementType'] ?? 'Road incident' }}">{{ $incident['icon'] ?? '🛣️' }}</span>
                                        {{ $incident['road'] ?? 'Road' }}
                                    </p>
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
            <p class="text-slate-500 dark:text-slate-400 text-sm">Enter your location (postcode or place name) and click "Check status" to get a personalised flood and road summary.</p>
        @endif

        <footer class="mt-12 pt-6 border-t border-slate-200 dark:border-slate-700">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                An <a href="https://automica.io" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">automica labs</a> project.
                Data: Environment Agency flood and river level data from the
                <a href="https://environment.data.gov.uk/flood-monitoring/doc/reference" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">Real-Time data API</a>
                (Open Government Licence).
                National Highways road and lane closure data (DATEX II v3.4) from the
                <a href="https://developer.data.nationalhighways.co.uk/" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">Developer Portal</a>.
                Weather from <a href="https://open-meteo.com/" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">Open-Meteo</a> (CC-BY 4.0).
                Geocoding by <a href="https://postcodes.io" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">postcodes.io</a> and <a href="https://nominatim.openstreetmap.org" target="_blank" rel="noopener" class="underline hover:text-slate-600 dark:hover:text-slate-300">OpenStreetMap Nominatim</a>.
            </p>
        </footer>
    </div>
</div>
