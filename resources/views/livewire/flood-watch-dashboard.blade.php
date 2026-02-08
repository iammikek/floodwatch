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

        <x-flood-watch.guest-banner />

        @php
            $hasResults = !$loading && $assistantResponse;
            $floodStatus = $hasResults ? (count($floods) > 0 ? count($floods) . ' ' . (count($floods) === 1 ? __('flood-watch.dashboard.warning') : __('flood-watch.dashboard.warnings')) : __('flood-watch.dashboard.no_alerts')) : null;
            $roadStatus = $hasResults ? (count($incidents) > 0 ? count($incidents) . ' ' . (count($incidents) === 1 ? __('flood-watch.dashboard.incident') : __('flood-watch.dashboard.incidents')) : __('flood-watch.dashboard.clear')) : null;
            $forecastStatus = $hasResults ? (!empty($forecast['england_forecast']) ? __('flood-watch.dashboard.available') : '—') : null;
            $weatherStatus = $hasResults ? (count($weather) > 0 ? count($weather) . ' ' . __('flood-watch.dashboard.days') : '—') : null;
            $riverStatus = $hasResults ? (count($riverLevels) > 0 ? count($riverLevels) . ' ' . __('flood-watch.dashboard.stations') : '—') : null;
        @endphp
        <x-flood-watch.result-badges
            :has-results="$hasResults"
            :flood-status="$floodStatus"
            :road-status="$roadStatus"
            :forecast-status="$forecastStatus"
            :weather-status="$weatherStatus"
            :river-status="$riverStatus"
        />

        <x-flood-watch.location-search
            :bookmarks="$this->bookmarks"
            :recent-searches="$this->recentSearches ?? []"
            :retry-after-timestamp="$retryAfterTimestamp"
            :can-retry="$this->canRetry()"
            :assistant-response="$assistantResponse"
        />

        <x-flood-watch.route-check />

        <x-flood-watch.error-banner
            :error="$error"
            :retry-after-timestamp="$retryAfterTimestamp"
        />

        <x-flood-watch.search-loading />

        @if (!$loading && $assistantResponse)
            <div
                class="space-y-6 scroll-smooth"
                id="results"
                @if (!$error && $autoRefreshEnabled && auth()->check()) wire:poll.900s="search" @endif
            >
                <x-flood-watch.results-header
                    :last-checked="$lastChecked"
                    :auto-refresh-enabled="$autoRefreshEnabled"
                    :retry-after-timestamp="$retryAfterTimestamp"
                    :can-retry="$this->canRetry()"
                />

                <x-flood-watch.flood-map
                    :map-center="$mapCenter"
                    :river-levels="$riverLevels"
                    :floods="$floods"
                    :incidents="$incidents"
                    :has-user-location="$hasUserLocation"
                    :last-checked="$lastChecked"
                />

                <x-flood-watch.flood-risk
                    :floods="$floods"
                    :has-user-location="$hasUserLocation"
                />

                <x-flood-watch.forecast :forecast="$forecast" />

                <x-flood-watch.weather :weather="$weather" />

                <x-flood-watch.road-status :incidents="$incidents" />

                <x-flood-watch.summary :assistant-response="$assistantResponse" />
            </div>
        @elseif (!$loading && !$error)
            <p class="text-slate-500 text-sm">{{ __('flood-watch.dashboard.prompt') }}</p>
        @endif

        <x-flood-watch.footer />
    </div>
</div>
