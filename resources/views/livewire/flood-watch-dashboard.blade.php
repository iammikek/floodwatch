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
    <div class="max-w-7xl mx-auto w-full">
        <x-flood-watch.header
            :retryAfterTimestamp="$retryAfterTimestamp"
            :canRetry="$this->canRetry()"
            :assistantResponse="$assistantResponse"
        />

        <x-flood-watch.risk-gauge :risk="$risk" />

        <div class="flex flex-col lg:flex-row gap-4 mb-6 max-h-[50vh] sm:max-h-[42vh] lg:max-h-[38vh] min-h-0 overflow-hidden">
            <x-flood-watch.status-grid
                :riverLevels="$riverLevels"
                :assistantResponse="$assistantResponse"
                :incidents="$incidents"
                :weather="$weather"
            />
            <x-flood-watch.activity-feed :activities="$activities" />
        </div>

        @guest
            <x-flood-watch.guest-banner />
        @endguest

        @php
            $hasResults = !$loading && $assistantResponse;
            $floodStatus = $hasResults ? (count($floods) > 0 ? count($floods) . ' ' . (count($floods) === 1 ? __('flood-watch.dashboard.warning') : __('flood-watch.dashboard.warnings')) : __('flood-watch.dashboard.no_alerts')) : null;
            $roadStatus = $hasResults ? (count($incidents) > 0 ? count($incidents) . ' ' . (count($incidents) === 1 ? __('flood-watch.dashboard.incident') : __('flood-watch.dashboard.incidents')) : __('flood-watch.dashboard.clear')) : null;
            $forecastStatus = $hasResults ? (!empty($forecast['england_forecast']) ? __('flood-watch.dashboard.available') : '—') : null;
            $weatherStatus = $hasResults ? (count($weather) > 0 ? count($weather) . ' ' . __('flood-watch.dashboard.days') : '—') : null;
            $riverStatus = $hasResults ? (count($riverLevels) > 0 ? count($riverLevels) . ' ' . __('flood-watch.dashboard.stations') : '—') : null;
        @endphp
        <x-flood-watch.quick-links
            :hasResults="$hasResults"
            :floodStatus="$floodStatus"
            :roadStatus="$roadStatus"
            :forecastStatus="$forecastStatus"
            :weatherStatus="$weatherStatus"
            :riverStatus="$riverStatus"
        />

        @if ($error)
            <x-flood-watch.error-banner
                :error="$error"
                :retryAfterTimestamp="$retryAfterTimestamp"
            />
        @endif

        <x-flood-watch.loading-bar />

        <x-flood-watch.map-section
            :mapCenter="$mapCenter"
            :lastChecked="$lastChecked"
            :riverLevels="$riverLevels"
            :floods="$floods"
            :incidents="$incidents"
            :hasUserLocation="$hasUserLocation"
        />

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

        <x-flood-watch.footer />
    </div>
</div>
