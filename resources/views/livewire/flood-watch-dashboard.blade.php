<div
    class="min-h-screen bg-slate-50 p-4 sm:p-6 pb-safe"
    x-data="{
        init() {
            this.$nextTick(() => {
                const el = this.$el?.closest('[wire\\:id]');
                const wire = el ? Livewire.find(el.getAttribute('wire:id')) : null;
                if (!wire) return;
                const storedLoc = localStorage.getItem('flood-watch-location');
                if (storedLoc) wire.set('location', storedLoc);
                const storedResults = localStorage.getItem('flood-watch-results');
                if (storedResults) {
                    try {
                        wire.restoreFromStorage(JSON.parse(storedResults));
                    } catch (e) {}
                }
                Livewire.on('search-completed', () => {
                    const loc = wire.location;
                    if (loc) localStorage.setItem('flood-watch-location', loc);
                    try {
                        const floods = (wire.floods || []).map(f => { const { polygon, ...rest } = f; return rest; });
                        localStorage.setItem('flood-watch-results', JSON.stringify({
                            assistantResponse: wire.assistantResponse,
                            floods, incidents: wire.incidents || [],
                            forecast: wire.forecast || [], weather: wire.weather || [],
                            riverLevels: wire.riverLevels || [], mapCenter: wire.mapCenter,
                            hasUserLocation: wire.hasUserLocation || false,
                            lastChecked: wire.lastChecked
                        }));
                    } catch (e) {}
                });
            });
        }
    }"
    x-init="init()"
>
    <div class="max-w-2xl mx-auto w-full">
        <h1 class="text-2xl font-semibold text-slate-900 mb-6">
            {{ __('flood-watch.dashboard.title') }}
        </h1>
        <p class="text-slate-600 mb-6">
            {{ __('flood-watch.dashboard.intro') }}
        </p>

        @guest
        <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-blue-800">
                {{ __('flood-watch.dashboard.guest_banner') }}
            </p>
            <a href="{{ route('register') }}" class="shrink-0 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-colors">
                {{ __('flood-watch.dashboard.guest_banner_register') }}
            </a>
        </div>
        @endguest

        <div class="flex flex-nowrap sm:flex-wrap gap-2 sm:gap-3 mb-6 overflow-x-auto pb-1 -mx-1 scrollbar-hide">
            @php
                $hasResults = !$loading && $assistantResponse;
                $floodStatus = $hasResults ? (count($floods) > 0 ? count($floods) . ' ' . (count($floods) === 1 ? __('flood-watch.dashboard.warning') : __('flood-watch.dashboard.warnings')) : __('flood-watch.dashboard.no_alerts')) : null;
                $roadStatus = $hasResults ? (count($incidents) > 0 ? count($incidents) . ' ' . (count($incidents) === 1 ? __('flood-watch.dashboard.incident') : __('flood-watch.dashboard.incidents')) : __('flood-watch.dashboard.clear')) : null;
                $forecastStatus = $hasResults ? (!empty($forecast['england_forecast']) ? __('flood-watch.dashboard.available') : '—') : null;
                $weatherStatus = $hasResults ? (count($weather) > 0 ? count($weather) . ' ' . __('flood-watch.dashboard.days') : '—') : null;
                $riverStatus = $hasResults ? (count($riverLevels) > 0 ? count($riverLevels) . ' ' . __('flood-watch.dashboard.stations') : '—') : null;
            @endphp
            <a href="#flood-risk" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800 hover:bg-amber-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                {{ __('flood-watch.dashboard.flood_risk') }}
                @if($floodStatus)
                    <span class="opacity-90">· {{ $floodStatus }}</span>
                @endif
            </a>
            <a href="#road-status" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                {{ __('flood-watch.dashboard.road_status') }}
                @if($roadStatus)
                    <span class="opacity-90">· {{ $roadStatus }}</span>
                @endif
            </a>
            <a href="#forecast" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 hover:bg-emerald-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                {{ __('flood-watch.dashboard.forecast') }}
                @if($forecastStatus)
                    <span class="opacity-90">· {{ $forecastStatus }}</span>
                @endif
            </a>
            <a href="#weather" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-sky-100 text-sky-800 hover:bg-sky-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                {{ __('flood-watch.dashboard.weather') }}
                @if($weatherStatus)
                    <span class="opacity-90">· {{ $weatherStatus }}</span>
                @endif
            </a>
            <a href="#map-section" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-slate-100 text-slate-800 hover:bg-slate-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
                {{ __('flood-watch.dashboard.map') }}
                @if($riverStatus)
                    <span class="opacity-90">· {{ $riverStatus }}</span>
                @endif
            </a>
        </div>

        <div class="mb-6">
            <label for="location" class="block text-sm font-medium text-slate-700 mb-2">
                {{ __('flood-watch.dashboard.your_location') }}
            </label>
            @if (count($recentSearches ?? []) > 0)
                <div class="flex flex-wrap gap-2 mb-3">
                    <span class="text-xs text-slate-500 self-center mr-1">{{ __('flood-watch.dashboard.recent_searches') }}:</span>
                    @foreach ($recentSearches as $recent)
                        <button
                            type="button"
                            wire:click="selectRecentSearch(@js($recent['location']))"
                            @click="window.__loadLeaflet && window.__loadLeaflet()"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors disabled:opacity-50"
                        >
                            {{ $recent['location'] === config('flood-watch.default_location_sentinel') ? __('flood-watch.dashboard.default_location') : $recent['location'] }}
                        </button>
                    @endforeach
                </div>
            @endif
            <div class="flex flex-col sm:flex-row gap-2">
                <input
                    type="text"
                    id="location"
                    wire:model="location"
                    placeholder="{{ __('flood-watch.dashboard.location_placeholder') }}"
                    class="block flex-1 min-h-[44px] rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base sm:text-sm"
                />
                <div
                    x-data="{
                        gpsLoading: false,
                        init() {
                            this.$watch('$wire.loading', (val) => { if (!val) this.gpsLoading = false; });
                        },
                        getLocation() {
                            if (!navigator.geolocation) {
                                $wire.set('error', @js(__('flood-watch.dashboard.gps_error')));
                                return;
                            }
                            this.gpsLoading = true;
                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    $wire.dispatch('location-from-gps', { lat: pos.coords.latitude, lng: pos.coords.longitude });
                                },
                                () => {
                                    this.gpsLoading = false;
                                    $wire.set('error', @js(__('flood-watch.dashboard.gps_error')));
                                },
                                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                            );
                        }
                    }"
                    class="contents"
                >
                    <button
                        type="button"
                        @click="getLocation(); window.__loadLeaflet && window.__loadLeaflet()"
                        :disabled="gpsLoading"
                        wire:loading.attr="disabled"
                        class="min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-3 sm:py-2 rounded-lg border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        aria-label="{{ __('flood-watch.dashboard.use_my_location') }}"
                    >
                        <span x-show="!gpsLoading" class="inline-flex items-center gap-2">📍 {{ __('flood-watch.dashboard.use_my_location') }}</span>
                        <span x-show="gpsLoading" x-cloak x-transition class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('flood-watch.dashboard.getting_location') }}
                        </span>
                    </button>
                </div>
                <button
                    type="button"
                    wire:click="search"
                    @click="window.__loadLeaflet && window.__loadLeaflet()"
                    wire:loading.attr="disabled"
                    @if($retryAfterTimestamp && !$this->canRetry()) disabled @endif
                    class="min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-3 sm:py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="search">{{ __('flood-watch.dashboard.check_status') }}</span>
                    @if (!$assistantResponse)
                    <span wire:loading wire:target="search" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('flood-watch.dashboard.searching') }}
                    </span>
                    @endif
                </button>
            </div>
        </div>

        @if ($error)
            <div
                class="mb-6 p-4 rounded-lg bg-red-50 text-red-700 text-sm"
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
                <span x-show="retryAfter && secondsLeft > 0" x-cloak x-transition> — {{ __('flood-watch.dashboard.retry_in_prefix') }} <span x-text="secondsLeft"></span> {{ __('flood-watch.dashboard.retry_in_suffix') }}</span>
                <span x-show="retryAfter && secondsLeft === 0" x-cloak x-transition> — {{ __('flood-watch.dashboard.retry_now') }}</span>
            </div>
        @endif

        <div wire:loading wire:target="search" class="w-full flex flex-row flex-nowrap items-center gap-3 p-4 rounded-lg bg-white shadow-sm border border-slate-200">
            <svg class="animate-spin h-6 w-6 text-blue-600 shrink-0 flex-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-slate-600 text-sm flex-1 min-w-0 inline-block" wire:stream.replace="searchStatus">{{ __('flood-watch.dashboard.connecting') }}</span>
        </div>

        @if (!$loading && $assistantResponse)
            <div
                class="space-y-6 scroll-smooth"
                id="results"
                @if (!$error && $autoRefreshEnabled && auth()->check()) wire:poll.900s="search" @endif
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($lastChecked)
                        <p class="text-sm text-slate-500">
                            {{ __('flood-watch.dashboard.last_checked') }}: {{ \Carbon\Carbon::parse($lastChecked)->format('j M Y, g:i a') }}
                        </p>
                    @endif
                    <div class="flex flex-wrap items-center gap-3">
                        @auth
                        <div
                            x-data="{
                                lastChecked: @js($lastChecked),
                                nextRefreshAt: null,
                                minutesLeft: null,
                                nextRefreshTemplate: @js(__('flood-watch.dashboard.next_refresh')),
                                refreshText: @js(__('flood-watch.dashboard.refreshing')),
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
                            class="contents"
                        >
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model.live="autoRefreshEnabled"
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm text-slate-600">{{ __('flood-watch.dashboard.auto_refresh') }}</span>
                        </label>
                            <span
                                x-show="$wire.autoRefreshEnabled && lastChecked && minutesLeft !== null"
                                x-cloak
                                x-transition
                                class="text-sm text-slate-500"
                                x-text="minutesLeft > 0 ? nextRefreshTemplate.replace(':minutes', minutesLeft).replace(':time', nextRefreshAt.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})) : refreshText"
                            ></span>
                        </div>
                        @endauth
                        <button
                        type="button"
                        wire:click="search"
                        @click="window.__loadLeaflet && window.__loadLeaflet()"
                        wire:loading.attr="disabled"
                        @if($retryAfterTimestamp && !$this->canRetry()) disabled @endif
                        class="min-h-[44px] min-w-[44px] inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="search">{{ __('flood-watch.dashboard.refresh') }}</span>
                        <span wire:loading wire:target="search" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('flood-watch.dashboard.refreshing') }}
                        </span>
                    </button>
                    </div>
                </div>

                @if ($mapCenter)
                    <div id="map-section" wire:key="map-{{ $lastChecked ?? 'initial' }}" class="rounded-lg overflow-hidden border border-slate-200">
                        <div
                            class="flex flex-col"
                            x-data="floodMap({ center: @js($mapCenter), stations: @js($riverLevels), floods: @js($floods), incidents: @js($incidents), hasUser: @js($hasUserLocation), t: @js(['your_location' => __('flood-watch.map.your_location'), 'elevated_level' => __('flood-watch.map.elevated_level'), 'expected_level' => __('flood-watch.map.expected_level'), 'low_level' => __('flood-watch.map.low_level'), 'typical_range' => __('flood-watch.map.typical_range'), 'flood_warning' => __('flood-watch.dashboard.flood_warning'), 'flood_area' => __('flood-watch.dashboard.flood_area'), 'km_from_location' => __('flood-watch.dashboard.km_from_location'), 'road' => __('flood-watch.dashboard.road'), 'road_incident' => __('flood-watch.dashboard.road_incident')]) })"
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

                <div id="flood-risk">
                    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.flood_warnings') }}</h2>
                    @if (count($floods) > 0)
                        <ul class="space-y-3">
                            @foreach ($floods as $flood)
                                <li class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-left overflow-visible">
                                    <p class="font-medium text-slate-900">{{ $flood['description'] ?? __('flood-watch.dashboard.flood_area') }}</p>
                                    <p class="text-sm text-amber-600 mt-1">{{ $flood['severity'] ?? '' }}</p>
                                    @if (!empty($flood['distanceKm']) && $hasUserLocation)
                                        <p class="text-xs text-slate-500 mt-1">{{ __('flood-watch.dashboard.km_from_location', ['distance' => $flood['distanceKm']]) }}</p>
                                    @endif
                                    @if (!empty($flood['timeRaised']) || !empty($flood['timeMessageChanged']))
                                        <p class="text-xs text-slate-500 mt-1">
                                            @if (!empty($flood['timeRaised']))
                                                {{ __('flood-watch.dashboard.raised') }}: {{ \Carbon\Carbon::parse($flood['timeRaised'])->format('j M Y, g:i a') }}
                                            @endif
                                            @if (!empty($flood['timeMessageChanged']))
                                                @if (!empty($flood['timeRaised'])) · @endif
                                                {{ __('flood-watch.dashboard.updated') }}: {{ \Carbon\Carbon::parse($flood['timeMessageChanged'])->format('j M Y, g:i a') }}
                                            @endif
                                        </p>
                                    @endif
                                    @if (!empty($flood['message']))
                                        <div x-data="{ open: false }" class="mt-2">
                                            <button type="button" @click="open = !open" class="flex items-center gap-2 cursor-pointer text-amber-600 hover:text-amber-700" aria-label="{{ __('flood-watch.dashboard.toggle_message') }}">
                                                <svg class="w-4 h-4 transition-transform duration-200" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                            <p x-show="open" x-cloak x-transition class="mt-2 text-sm text-slate-600 whitespace-pre-wrap break-words">{{ $flood['message'] }}</p>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.no_flood_warnings') }}</p>
                    @endif
                </div>

                <div id="forecast">
                    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.forecast_outlook') }}</h2>
                    @if (count($forecast) > 0 && !empty($forecast['england_forecast']))
                        <div class="p-4 rounded-lg bg-white shadow-sm border border-slate-200">
                            <p class="text-slate-600">{{ $forecast['england_forecast'] }}</p>
                            @if (!empty($forecast['flood_risk_trend']))
                                <p class="text-sm text-slate-500 mt-2">
                                    {{ __('flood-watch.dashboard.trend') }}: @foreach ($forecast['flood_risk_trend'] as $day => $trend){{ ucfirst($day) }}: {{ $trend }}@if (!$loop->last) → @endif @endforeach
                                </p>
                            @endif
                            @if (!empty($forecast['issued_at']))
                                <p class="text-xs text-slate-400 mt-1">{{ __('flood-watch.dashboard.issued') }}: {{ \Carbon\Carbon::parse($forecast['issued_at'])->format('j M Y, g:i') }}</p>
                            @endif
                        </div>
                    @else
                        <p class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.no_forecast') }}</p>
                    @endif
                </div>

                <div id="weather">
                    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.weather_forecast') }}</h2>
                    @if (count($weather) > 0)
                        <div class="flex flex-nowrap gap-3 overflow-x-auto pb-2 -mx-1 scrollbar-hide">
                            @foreach ($weather as $day)
                                <div class="flex-1 min-w-[6.5rem] sm:min-w-[7rem] p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-center shrink-0">
                                    <p class="text-sm font-medium text-slate-600">
                                        {{ \Carbon\Carbon::parse($day['date'])->format('D j M') }}
                                    </p>
                                    <p class="text-3xl my-2" title="{{ $day['description'] ?? '' }}">{{ $day['icon'] ?? '🌤️' }}</p>
                                    <p class="text-slate-900 font-semibold">{{ round($day['temp_max'] ?? 0) }}° / {{ round($day['temp_min'] ?? 0) }}°</p>
                                    @if (($day['precipitation'] ?? 0) > 0)
                                        <p class="text-sm text-sky-600 mt-1">💧 {{ round($day['precipitation'], 1) }} mm</p>
                                    @endif
                                    <p class="text-xs text-slate-500 mt-1">{{ $day['description'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.no_weather') }}</p>
                    @endif
                </div>

                <div id="road-status">
                    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.road_status') }}</h2>
                    @if (count($incidents) > 0)
                        <ul class="space-y-3">
                            @foreach ($incidents as $incident)
                                <li class="p-4 rounded-lg bg-white shadow-sm border border-slate-200">
                                    <p class="font-medium text-slate-900 flex items-center gap-2">
                                        <span class="text-lg" title="{{ $incident['typeLabel'] ?? __('flood-watch.dashboard.road_incident') }}">{{ $incident['icon'] ?? '🛣️' }}</span>
                                        {{ $incident['road'] ?? __('flood-watch.dashboard.road') }}
                                    </p>
                                    @if (!empty($incident['statusLabel']))
                                        <p class="text-sm text-blue-600 mt-1">{{ $incident['statusLabel'] }}</p>
                                    @endif
                                    @if (!empty($incident['typeLabel']))
                                        <p class="text-sm text-slate-600 mt-1">{{ $incident['typeLabel'] }}</p>
                                    @endif
                                    @if (!empty($incident['delayTime']))
                                        <p class="text-sm text-slate-500 mt-1">{{ __('flood-watch.dashboard.delay') }}: {{ $incident['delayTime'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.roads_clear') }}</p>
                    @endif
                </div>

                <div class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 prose prose-slate max-w-none">
                    <h2 class="text-lg font-medium text-slate-900 mb-2">{{ __('flood-watch.dashboard.summary') }}</h2>
                    {!! Str::markdown($assistantResponse) !!}
                </div>
            </div>
        @elseif (!$loading && !$error)
            <p class="text-slate-500 text-sm">{{ __('flood-watch.dashboard.prompt') }}</p>
        @endif

        <footer class="mt-12 pt-6 border-t border-slate-200">
            @if (config('app.donation_url'))
                <p class="text-xs text-slate-500 mb-2">
                    {{ __('flood-watch.dashboard.free_to_use') }}
                    <a href="{{ config('app.donation_url') }}" target="_blank" rel="noopener" class="underline hover:text-slate-600">{{ __('flood-watch.dashboard.support_development') }}</a>.
                </p>
            @endif
            <p class="text-xs text-slate-500">
                An <a href="https://automica.io" target="_blank" rel="noopener" class="underline hover:text-slate-600">automica labs</a> project.
                Data: Environment Agency flood and river level data from the
                <a href="https://environment.data.gov.uk/flood-monitoring/doc/reference" target="_blank" rel="noopener" class="underline hover:text-slate-600">Real-Time data API</a>
                (Open Government Licence).
                National Highways road and lane closure data (DATEX II v3.4) from the
                <a href="https://developer.data.nationalhighways.co.uk/" target="_blank" rel="noopener" class="underline hover:text-slate-600">Developer Portal</a>.
                Weather from <a href="https://open-meteo.com/" target="_blank" rel="noopener" class="underline hover:text-slate-600">Open-Meteo</a> (CC-BY 4.0).
                Geocoding by <a href="https://postcodes.io" target="_blank" rel="noopener" class="underline hover:text-slate-600">postcodes.io</a> and <a href="https://nominatim.openstreetmap.org" target="_blank" rel="noopener" class="underline hover:text-slate-600">OpenStreetMap Nominatim</a>.
            </p>
        </footer>
    </div>
</div>
