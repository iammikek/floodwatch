@props([
    'routeCheckLoading' => false,
    'routeCheckResult' => null,
])

<div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200">
    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">
        {{ __('flood-watch.dashboard.route_check') }}
    </h2>
    <div class="flex flex-col gap-2">
        <div class="flex flex-col sm:flex-row gap-2">
            <div class="flex flex-1 gap-2">
                <input
                    type="text"
                    wire:model="routeFrom"
                    placeholder="{{ __('flood-watch.dashboard.route_check_from') }}"
                    aria-label="{{ __('flood-watch.dashboard.route_check_from') }}"
                    @disabled($routeCheckLoading)
                    class="block flex-1 min-h-[44px] rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                />
                <button
                    type="button"
                    @click="
                        if (!navigator.geolocation) {
                            $wire.set('error', @js(__('flood-watch.dashboard.gps_error')));
                            return;
                        }
                        navigator.geolocation.getCurrentPosition(
                            (pos) => $wire.dispatch('location-from-gps-for-route', { lat: pos.coords.latitude, lng: pos.coords.longitude }),
                            () => { $wire.set('error', @js(__('flood-watch.dashboard.gps_error'))); },
                            { enableHighAccuracy: true, timeout: 10000 }
                        )
                    "
                    @disabled($routeCheckLoading)
                    class="shrink-0 min-h-[44px] inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    aria-label="{{ __('flood-watch.dashboard.use_my_location') }}"
                    title="{{ __('flood-watch.dashboard.use_my_location') }}"
                >
                    📍
                </button>
            </div>
            <input
                type="text"
                wire:model="routeTo"
                placeholder="{{ __('flood-watch.dashboard.route_check_to') }}"
                aria-label="{{ __('flood-watch.dashboard.route_check_to') }}"
                @disabled($routeCheckLoading)
                class="block flex-1 min-h-[44px] rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
            />
            <button
                type="button"
                wire:click="checkRoute"
                @disabled($routeCheckLoading)
                class="min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-3 sm:py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span wire:loading.remove wire:target="checkRoute">{{ __('flood-watch.dashboard.route_check_button') }}</span>
                <span wire:loading wire:target="checkRoute" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('flood-watch.dashboard.searching') }}
                </span>
            </button>
        </div>

        @if ($routeCheckResult)
            <div class="mt-4 p-4 rounded-lg bg-white border border-slate-200 space-y-3">
                @php
                    $verdict = $routeCheckResult['verdict'] ?? 'clear';
                    $verdictKey = 'flood-watch.route_check.verdict_' . $verdict;
                    $verdictClasses = match ($verdict) {
                        'blocked' => 'bg-red-100 text-red-800',
                        'at_risk' => 'bg-amber-100 text-amber-800',
                        'delays' => 'bg-yellow-100 text-yellow-800',
                        'error' => 'bg-slate-100 text-slate-700',
                        default => 'bg-emerald-100 text-emerald-800',
                    };
                @endphp
                <div class="flex items-center gap-2">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-sm font-medium {{ $verdictClasses }}">
                        {{ __($verdictKey) }}
                    </span>
                </div>
                <p class="text-slate-600 text-sm">{{ $routeCheckResult['summary'] ?? '' }}</p>

                @if (!empty($routeCheckResult['floods_on_route']))
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase mb-1">{{ __('flood-watch.route_check.floods_on_route') }}</p>
                        <ul class="space-y-1 text-sm">
                            @foreach ($routeCheckResult['floods_on_route'] as $flood)
                                <li class="text-amber-700">{{ $flood['description'] ?? __('flood-watch.dashboard.flood_area') }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!empty($routeCheckResult['incidents_on_route']))
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase mb-1">{{ __('flood-watch.route_check.incidents_on_route') }}</p>
                        <ul class="space-y-1 text-sm">
                            @foreach ($routeCheckResult['incidents_on_route'] as $incident)
                                <li class="text-slate-700">
                                    <span>{{ $incident['icon'] ?? '🛣️' }}</span>
                                    {{ $incident['road'] ?? __('flood-watch.dashboard.road') }}
                                    @if (!empty($incident['typeLabel']))
                                        — {{ $incident['typeLabel'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!empty($routeCheckResult['alternatives']))
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase mb-1">{{ __('flood-watch.route_check.alternatives') }}</p>
                        <ul class="space-y-2 text-sm">
                            @foreach ($routeCheckResult['alternatives'] as $alt)
                                <li class="text-slate-600">
                                    {{ implode(' → ', $alt['names'] ?? []) }}
                                    <span class="text-slate-400">({{ $alt['distance'] ?? 0 }} km, ~{{ $alt['duration'] ?? 0 }} min)</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
