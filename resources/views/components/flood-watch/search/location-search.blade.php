@props([
    'bookmarks' => [],
    'recentSearches' => [],
    'retryAfterTimestamp' => null,
    'canRetry' => true,
    'assistantResponse' => null,
])

<div class="mb-6">
    <label for="location" class="block text-sm font-medium text-slate-700 mb-2">
        {{ __('flood-watch.dashboard.your_location') }}
    </label>
    @if (count($bookmarks) > 0)
        <div class="flex flex-wrap gap-2 mb-3">
            <span class="text-xs text-slate-500 self-center mr-1">{{ __('flood-watch.dashboard.bookmarks') }}:</span>
            @foreach ($bookmarks as $bookmark)
                <button
                    type="button"
                    data-testid="bookmark-{{ $bookmark['id'] }}"
                    wire:click="selectBookmark({{ $bookmark['id'] }})"
                    @click="window.__loadLeaflet && window.__loadLeaflet()"
                    wire:loading.attr="disabled"
                    wire:target="selectBookmark({{ $bookmark['id'] }})"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium {{ $bookmark['is_default'] ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700' }} hover:bg-slate-200 transition-colors disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="selectBookmark({{ $bookmark['id'] }})">
                        {{ $bookmark['label'] }} ({{ $bookmark['location'] }})
                    </span>
                    <span wire:loading wire:target="selectBookmark({{ $bookmark['id'] }})" class="inline-flex items-center gap-1.5">
                        <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('flood-watch.dashboard.searching') }}
                    </span>
                </button>
            @endforeach
        </div>
    @endif
    @if (count($recentSearches) > 0)
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
                gpsTimeoutId: null,
                init() {
                    const self = this;
                    const clearGps = () => {
                        self.gpsLoading = false;
                        if (self.gpsTimeoutId) clearTimeout(self.gpsTimeoutId);
                        self.gpsTimeoutId = null;
                    };
                    Livewire.on('search-completed', clearGps);
                    this.$watch('$wire.loading', (val) => { if (!val) clearGps(); });
                },
                getLocation() {
                    if (!navigator.geolocation) {
                        $wire.set('error', @js(__('flood-watch.dashboard.gps_error')));
                        return;
                    }
                    this.gpsLoading = true;
                    this.gpsTimeoutId = setTimeout(() => { this.gpsLoading = false; this.gpsTimeoutId = null; }, 60000);
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            $wire.dispatch('location-from-gps', { lat: pos.coords.latitude, lng: pos.coords.longitude });
                        },
                        () => {
                            this.gpsLoading = false;
                            if (this.gpsTimeoutId) clearTimeout(this.gpsTimeoutId);
                            this.gpsTimeoutId = null;
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
                :disabled="gpsLoading || $wire.loading"
                wire:loading.attr="disabled"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-3 sm:py-2 rounded-lg border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
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
            @if($retryAfterTimestamp && !$canRetry) disabled @endif
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
