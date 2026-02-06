<div
    class="min-h-screen flex flex-col bg-slate-50 pb-safe"
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
                        localStorage.setItem('flood-watch-results', JSON.stringify({
                            assistantResponse: wire.assistantResponse,
                            floods: wire.floods || [], incidents: wire.incidents || [],
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
    <div class="flex-1 flex flex-col min-h-0 overflow-y-auto">
        <div class="max-w-7xl mx-auto w-full p-4 sm:p-6">
            <x-flood-watch.header
                :retryAfterTimestamp="$retryAfterTimestamp"
                :canRetry="$this->canRetry()"
                :assistantResponse="$assistantResponse"
                :error="$error"
                :lastChecked="$lastChecked"
            />

            <x-flood-watch.risk-gauge :risk="$risk" :incidents="$incidents" />

            <div class="flex flex-col lg:flex-row gap-4 mb-6 min-h-[280px] max-h-[50vh] sm:max-h-[42vh] lg:max-h-[38vh] overflow-hidden">
                <x-flood-watch.status-grid
                    :mapCenter="$mapCenter"
                    :mapDataUrl="route('api.v1.map-data')"
                    :riverLevels="$riverLevels"
                    :assistantResponse="$assistantResponse"
                    :weather="$weather"
                />
                <x-flood-watch.activity-feed :activities="$activities" />
            </div>

            <x-flood-watch.loading-bar />

            <x-flood-watch.results-section
                :loading="$loading"
                :floods="$floods"
                :incidents="$incidents"
                :forecast="$forecast"
                :assistantResponse="$assistantResponse"
                :lastChecked="$lastChecked"
                :hasUserLocation="$hasUserLocation"
                :error="$error"
                :autoRefreshEnabled="$autoRefreshEnabled"
                :retryAfterTimestamp="$retryAfterTimestamp"
            />
        </div>
    </div>

    <div class="shrink-0 px-4 sm:px-6 pb-4 space-y-4">
        <div class="max-w-7xl mx-auto relative">
            <x-flood-watch.map-section
                :mapCenter="$mapCenter"
                :lastChecked="$lastChecked"
                :hasUserLocation="$hasUserLocation"
            />
            <p class="mt-2 text-xs text-slate-500">
                An <a href="https://automica.io" target="_blank" rel="noopener" class="underline hover:text-slate-600">automica labs</a> project.
                Data: Environment Agency flood and river level data from the
                <a href="https://environment.data.gov.uk/flood-monitoring/doc/reference" target="_blank" rel="noopener" class="underline hover:text-slate-600">Real-Time data API</a>
                (Open Government Licence).
                National Highways road and lane closure data (DATEX II v3.4) from the
                <a href="https://developer.data.nationalhighways.co.uk/" target="_blank" rel="noopener" class="underline hover:text-slate-600">Developer Portal</a>.
                Weather from <a href="https://open-meteo.com/" target="_blank" rel="noopener" class="underline hover:text-slate-600">Open-Meteo</a> (CC-BY 4.0).
                Geocoding by <a href="https://postcodes.io" target="_blank" rel="noopener" class="underline hover:text-slate-600">postcodes.io</a> and <a href="https://nominatim.openstreetmap.org" target="_blank" rel="noopener" class="underline hover:text-slate-600">OpenStreetMap Nominatim</a>.
            </p>
        </div>

        @if ($assistantResponse)
            <details class="group max-w-7xl mx-auto bg-white rounded-lg border border-slate-200">
                <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-lg list-none flex items-center justify-between [&::-webkit-details-marker]:hidden">
                    <span>{{ __('flood-watch.dashboard.forecast_summary_toggle') }}</span>
                    <svg class="w-4 h-4 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                <div class="px-4 pb-4 pt-2 border-t border-slate-100 space-y-4">
                    <div id="forecast">
                        <h3 class="text-sm font-medium text-slate-900 mb-2">{{ __('flood-watch.dashboard.forecast_outlook') }}</h3>
                        @if (count($forecast) > 0 && !empty($forecast['england_forecast']))
                            <p class="text-sm text-slate-600">{{ $forecast['england_forecast'] }}</p>
                            @if (!empty($forecast['flood_risk_trend']))
                                <p class="text-xs text-slate-500 mt-2">
                                    {{ __('flood-watch.dashboard.trend') }}: @foreach ($forecast['flood_risk_trend'] as $day => $trend){{ ucfirst($day) }}: {{ $trend }}@if (!$loop->last) → @endif @endforeach
                                </p>
                            @endif
                            @if (!empty($forecast['issued_at']))
                                <p class="text-xs text-slate-400 mt-1">{{ __('flood-watch.dashboard.issued') }}: {{ \Carbon\Carbon::parse($forecast['issued_at'])->format('j M Y, g:i') }}</p>
                            @endif
                        @else
                            <p class="text-sm text-slate-600">{{ __('flood-watch.dashboard.no_forecast') }}</p>
                        @endif
                    </div>
                    <x-flood-watch.flood-warnings
                        :floods="$floods"
                        :hasUserLocation="$hasUserLocation"
                    />
                    <div>
                        <h3 class="text-sm font-medium text-slate-900 mb-2">{{ __('flood-watch.dashboard.summary') }}</h3>
                        <div class="prose prose-slate prose-sm max-w-none text-slate-600">
                            {!! Str::markdown($assistantResponse) !!}
                        </div>
                    </div>
                </div>
            </details>
        @endif
    </div>
</div>
