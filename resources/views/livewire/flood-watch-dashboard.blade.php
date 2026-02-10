<div
    class="min-h-screen bg-slate-50 p-4 sm:p-6 pb-safe"
    x-data="{
        init() {
            this.$nextTick(() => {
                const getWire = () => {
                    const el = document.querySelector('[wire\\:id]');
                    return el && typeof Livewire !== 'undefined' ? Livewire.find(el.getAttribute('wire:id')) : null;
                };
                const setLayoutFromViewport = () => {
                    const w = getWire();
                    if (!w) return;
                    const isDesktop = window.innerWidth >= 1024;
                    const want = isDesktop ? 'desktop' : 'mobile';
                    const hasMap = document.getElementById('flood-map') !== null;
                    const hasMobileLayout = document.getElementById('ai-advice') !== null;
                    const needUpdate = !hasMap && !hasMobileLayout || (hasMap && !isDesktop) || (hasMobileLayout && isDesktop);
                    if (needUpdate) w.set('layoutVariant', want);
                };
                setLayoutFromViewport();
                let resizeT = null;
                window.addEventListener('resize', () => {
                    if (resizeT) clearTimeout(resizeT);
                    resizeT = setTimeout(setLayoutFromViewport, 150);
                });
                const wire = getWire();
                if (wire) {
                    const storedLoc = localStorage.getItem('flood-watch-location');
                    if (storedLoc) wire.set('location', storedLoc);
                    const storedResults = localStorage.getItem('flood-watch-results');
                    if (storedResults) {
                        try {
                            wire.restoreFromStorage(JSON.parse(storedResults));
                        } catch (e) {}
                    }
                }
                Livewire.on('search-completed', () => {
                    const w = getWire();
                    if (!w) return;
                    const loc = w.location;
                    if (loc) localStorage.setItem('flood-watch-location', loc);
                    try {
                        localStorage.setItem('flood-watch-results', JSON.stringify({
                            assistantResponse: w.assistantResponse,
                            floods: w.floods || [],
                            incidents: w.incidents || [],
                            forecast: w.forecast || [], weather: w.weather || [],
                            riverLevels: w.riverLevels || [], mapCenter: w.mapCenter,
                            hasUserLocation: w.hasUserLocation || false,
                            lastChecked: w.lastChecked
                        }));
                    } catch (e) {}
                });
            });
        }
    }"
    x-init="init()"
>
    <div class="max-w-2xl lg:max-w-6xl mx-auto w-full">
        <x-flood-watch.search.location-header
            :location="$location"
            :display-location="$displayLocation"
            :outcode="$outcode"
            :bookmarks="$this->bookmarks"
            :recent-searches="$this->recentSearches ?? []"
            :retry-after-timestamp="$retryAfterTimestamp"
            :can-retry="$this->canRetry()"
            :assistant-response="$assistantResponse"
            :loading="$loading"
        />
        @php
            $hasResults = !$loading && $assistantResponse;
            $floodStatus = $hasResults ? (count($floods) > 0 ? count($floods) . ' ' . (count($floods) === 1 ? __('flood-watch.dashboard.warning') : __('flood-watch.dashboard.warnings')) : __('flood-watch.dashboard.no_alerts')) : null;
            $roadStatus = $hasResults ? (count($incidents) > 0 ? count($incidents) . ' ' . (count($incidents) === 1 ? __('flood-watch.dashboard.incident') : __('flood-watch.dashboard.incidents')) : __('flood-watch.dashboard.clear')) : null;
            $forecastStatus = $hasResults ? (!empty($forecast['england_forecast']) ? __('flood-watch.dashboard.available') : '—') : null;
            $weatherStatus = $hasResults ? (count($weather) > 0 ? count($weather) . ' ' . __('flood-watch.dashboard.days') : '—') : null;
            $riverStatus = $hasResults ? (count($riverLevels) > 0 ? count($riverLevels) . ' ' . __('flood-watch.dashboard.stations') : '—') : null;
        @endphp
        @if (!$assistantResponse)
            <x-flood-watch.search.route-check
                :route-check-loading="$routeCheckLoading"
                :route-check-result="$routeCheckResult"
            />
        @endif

        @php
            $routeGeometry = $routeCheckResult['route_geometry'] ?? null;
            $hasRouteGeometry = $routeGeometry && count($routeGeometry) > 0;
            $routeKey = $routeCheckResult['route_key'] ?? ($hasRouteGeometry ? md5($routeFrom . '|' . $routeTo) : null);
            $mapCenterFromRoute = null;
            if ($hasRouteGeometry && !$mapCenter) {
                $lats = array_column($routeGeometry, 1);
                $lngs = array_column($routeGeometry, 0);
                $mapCenterFromRoute = ['lat' => (min($lats) + max($lats)) / 2, 'lng' => (min($lngs) + max($lngs)) / 2];
            }
            $mapCenterForRoute = $mapCenter ?? $mapCenterFromRoute;
            $routeFloods = $hasRouteGeometry ? ($routeCheckResult['floods_on_route'] ?? []) : [];
            $routeIncidents = $hasRouteGeometry ? ($routeCheckResult['incidents_on_route'] ?? []) : [];
        @endphp

        @if ($hasRouteGeometry && $mapCenterForRoute && !$assistantResponse && $layoutVariant === 'desktop')
            <div class="mt-6" wire:key="route-map-{{ $routeKey }}">
                <x-flood-watch.results.flood-map
                    :map-center="$mapCenterForRoute"
                    :river-levels="[]"
                    :floods="$routeFloods"
                    :incidents="$routeIncidents"
                    :has-user-location="false"
                    :last-checked="null"
                    :route-geometry="$routeGeometry"
                    :route-key="$routeKey"
                />
            </div>
        @endif

        <x-flood-watch.status.error-banner
            :error="$error"
            :retry-after-timestamp="$retryAfterTimestamp"
        />

        <x-flood-watch.status.search-loading />

        @if (!$loading && $assistantResponse)
            @php
                $wirePoll = !$error && $autoRefreshEnabled && auth()->check();
            @endphp

            @if ($layoutVariant === 'mobile')
                <x-flood-watch.layout.mobile-results
                    :last-checked="$lastChecked"
                    :auto-refresh-enabled="$autoRefreshEnabled"
                    :retry-after-timestamp="$retryAfterTimestamp"
                    :can-retry="$this->canRetry()"
                    :house-risk="$this->houseRisk"
                    :roads-risk="$this->roadsRisk"
                    :action-steps="$this->actionSteps"
                    :has-danger-to-life="$this->hasDangerToLife"
                    :route-check-loading="$routeCheckLoading"
                    :route-check-result="$routeCheckResult"
                    :floods="$floods"
                    :has-user-location="$hasUserLocation"
                    :weather="$weather"
                    :incidents="$incidents"
                    :assistant-response="$assistantResponse"
                    :wire-poll="$wirePoll"
                />
            @else
                <x-flood-watch.layout.desktop-results
                    :last-checked="$lastChecked"
                    :auto-refresh-enabled="$autoRefreshEnabled"
                    :retry-after-timestamp="$retryAfterTimestamp"
                    :can-retry="$this->canRetry()"
                    :house-risk="$this->houseRisk"
                    :roads-risk="$this->roadsRisk"
                    :action-steps="$this->actionSteps"
                    :has-danger-to-life="$this->hasDangerToLife"
                    :route-check-loading="$routeCheckLoading"
                    :route-check-result="$routeCheckResult"
                    :map-center="$mapCenter"
                    :river-levels="$riverLevels"
                    :floods="$floods"
                    :incidents="$incidents"
                    :floods-in-view="$this->floodsInView"
                    :incidents-in-view="$this->incidentsInView"
                    :has-user-location="$hasUserLocation"
                    :route-geometry="$routeGeometry"
                    :route-key="$routeKey"
                    :forecast="$forecast"
                    :weather="$weather"
                    :assistant-response="$assistantResponse"
                    :wire-poll="$wirePoll"
                />
            @endif
        @elseif (!$loading && !$error)
            <p class="text-slate-500 text-sm">{{ __('flood-watch.dashboard.prompt') }}</p>
        @endif

        <x-flood-watch.layout.footer :show-section-links="$hasResults" />
    </div>
</div>
