<div class="min-h-screen bg-slate-50 dark:bg-slate-900 p-6">
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
            <label for="postcode" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                Postcode (optional)
            </label>
            <div class="flex gap-2">
                <input
                    type="text"
                    id="postcode"
                    wire:model="postcode"
                    placeholder="e.g. TA10 0"
                    class="block flex-1 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                />
                <button
                    type="button"
                    wire:click="search"
                    wire:loading.attr="disabled"
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
            <div class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm">
                {{ $error }}
            </div>
        @endif

        @if ($loading)
            <div class="flex items-center gap-3 p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700">
                <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-slate-600 dark:text-slate-400 text-sm">Searching real-time records...</p>
            </div>
        @endif

        @if (!$loading && $assistantResponse)
            <div class="space-y-6 scroll-smooth" id="results">
                @if ($lastChecked)
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Last checked: {{ \Carbon\Carbon::parse($lastChecked)->format('j M Y, g:i a') }}
                    </p>
                @endif

                <div id="flood-risk">
                    <h2 class="text-lg font-medium text-slate-900 dark:text-white mb-3">Flood warnings</h2>
                    @if (count($floods) > 0)
                        <ul class="space-y-3">
                            @foreach ($floods as $flood)
                                <li class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 text-left overflow-visible">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $flood['description'] ?? 'Flood area' }}</p>
                                    <p class="text-sm text-amber-600 dark:text-amber-400 mt-1">{{ $flood['severity'] ?? '' }}</p>
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
                                        <div x-data="{ open: false }" class="mt-2 overflow-visible">
                                            <button type="button" @click="open = !open" class="w-full text-left flex items-start gap-2 cursor-pointer text-sm text-slate-600 dark:text-slate-400 overflow-visible">
                                                <span x-show="!open" x-transition class="flex-1 min-w-0 text-left break-words">{{ Str::limit($flood['message'], 200) }}</span>
                                                <span x-show="open" x-cloak x-transition class="flex-1 min-w-0 text-left whitespace-pre-wrap break-words">{{ $flood['message'] }}</span>
                                                <span class="shrink-0 text-amber-600 dark:text-amber-400 transition-transform duration-200" :class="open && 'rotate-180'" aria-hidden="true">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </span>
                                            </button>
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
            <p class="text-slate-500 dark:text-slate-400 text-sm">Click "Check status" to get current flood and road data for the South West.</p>
        @endif
    </div>
</div>
