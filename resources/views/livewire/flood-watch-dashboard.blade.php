<div class="min-h-screen bg-slate-50 dark:bg-slate-900 p-6">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white mb-6">
            Flood Watch
        </h1>
        <p class="text-slate-600 dark:text-slate-400 mb-6">
            Somerset Levels flood and road status. Ask the Somerset Emergency Assistant to check current conditions.
        </p>

        <div class="flex gap-3 mb-6">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                Flood Risk
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                Road Status
            </span>
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
            <div class="space-y-6">
                @if ($lastChecked)
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Last checked: {{ \Carbon\Carbon::parse($lastChecked)->format('j M Y, g:i a') }}
                    </p>
                @endif

                @if (count($floods) > 0)
                    <div>
                        <h2 class="text-lg font-medium text-slate-900 dark:text-white mb-3">Flood warnings</h2>
                        <ul class="space-y-3">
                            @foreach ($floods as $flood)
                                <li class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $flood['description'] ?? 'Flood area' }}</p>
                                    <p class="text-sm text-amber-600 dark:text-amber-400 mt-1">{{ $flood['severity'] ?? '' }}</p>
                                    @if (!empty($flood['message']))
                                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">{{ Str::limit($flood['message'], 200) }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (count($incidents) > 0)
                    <div>
                        <h2 class="text-lg font-medium text-slate-900 dark:text-white mb-3">Road status</h2>
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
                    </div>
                @endif

                <div class="p-4 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 prose prose-slate dark:prose-invert max-w-none">
                    <h2 class="text-lg font-medium text-slate-900 dark:text-white mb-2">Summary</h2>
                    {!! Str::markdown($assistantResponse) !!}
                </div>
            </div>
        @elseif (!$loading && !$error)
            <p class="text-slate-500 dark:text-slate-400 text-sm">Click "Check status" to ask the Somerset Emergency Assistant for current flood and road data.</p>
        @endif
    </div>
</div>
