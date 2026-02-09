@props([
    'location' => '',
    'displayLocation' => null,
    'outcode' => null,
    'bookmarks' => [],
    'recentSearches' => [],
    'retryAfterTimestamp' => null,
    'canRetry' => true,
    'assistantResponse' => null,
    'loading' => false,
])

@php
    $hasLocation = trim($location) !== '';
    $hasResults = !$loading && $assistantResponse;
    $showCompactBar = $hasLocation && $hasResults;
    $displayText = $displayLocation ?? $location;
    $locationSuffix = $outcode ? " · {$outcode}" : '';
@endphp

<div
    class="border-b border-slate-200 bg-white -mx-4 px-4 sm:-mx-6 sm:px-6 py-4 mb-6"
    x-data="{ showChange: false, showCompact: @js($showCompactBar) }"
    @open-change-location.window="showChange = true"
    @search-completed.window="showChange = false"
>
    <div class="max-w-2xl mx-auto">
        {{-- Compact header: title + location bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="text-xl sm:text-2xl font-semibold text-slate-900 shrink-0">
                {{ __('flood-watch.dashboard.title') }}
            </h1>

            <div x-show="showCompact && !showChange" x-cloak style="display: none;">
                {{-- Location bar: compact display with actions --}}
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <p class="text-sm font-medium text-slate-700">
                        📍 {{ $displayText }}{{ $locationSuffix }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            x-data
                            @click="$dispatch('open-change-location')"
                            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                        >
                            {{ __('flood-watch.dashboard.change') }}
                        </button>
                        <button
                            type="button"
                            x-data="{
                                gpsLoading: false,
                                init() {
                                    Livewire.on('search-completed', () => { this.gpsLoading = false; });
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
                                        { enableHighAccuracy: true, timeout: 10000 }
                                    );
                                }
                            }"
                            @click="getLocation(); window.__loadLeaflet && window.__loadLeaflet()"
                            :disabled="gpsLoading || $wire.loading"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1 rounded border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        >
                            <span x-show="!gpsLoading">📍</span>
                            <svg x-show="gpsLoading" x-cloak class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('flood-watch.dashboard.use_my_location') }}
                        </button>
                        @if (auth()->check())
                            <button
                                type="button"
                                wire:click="search"
                                wire:loading.attr="disabled"
                                @if($retryAfterTimestamp && !$canRetry) disabled @endif
                                class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1 rounded border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="search">{{ __('flood-watch.dashboard.refresh') }}</span>
                                <span wire:loading wire:target="search" class="inline-flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('flood-watch.dashboard.searching') }}
                                </span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
            <div x-show="!showCompact || showChange" x-cloak style="display: none;">
                {{-- No location or no results: show location search --}}
                <div class="flex-1 min-w-0 -mb-2">
                    <x-flood-watch.search.location-search
                        :bookmarks="$bookmarks"
                        :recent-searches="$recentSearches"
                        :retry-after-timestamp="$retryAfterTimestamp"
                        :can-retry="$canRetry"
                        :assistant-response="$assistantResponse"
                    />
                </div>
            </div>
        </div>

        @if ($hasLocation && $hasResults && count($bookmarks) > 0)
            <div class="mt-6 flex flex-wrap gap-2">
                <span class="text-xs text-slate-500 self-center">{{ __('flood-watch.dashboard.bookmarks') }}:</span>
                @foreach ($bookmarks as $bookmark)
                    <button
                        type="button"
                        data-testid="bookmark-{{ $bookmark['id'] }}"
                        wire:click="selectBookmark({{ $bookmark['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="selectBookmark({{ $bookmark['id'] }})"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium {{ $bookmark['is_default'] ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700' }} hover:bg-slate-200 transition-colors disabled:opacity-50"
                    >
                        {{ $bookmark['label'] }} ({{ $bookmark['location'] }})
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</div>
