@props([
    'retryAfterTimestamp' => null,
    'canRetry' => true,
    'assistantResponse' => null,
    'error' => null,
    'lastChecked' => null,
])

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h1 class="text-2xl font-semibold text-slate-900 shrink-0">
        {{ __('flood-watch.dashboard.title') }}
    </h1>
    <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:gap-3">
        @if ($error)
            <div
                class="flex items-center gap-2 px-3 py-2 rounded-lg bg-red-50 text-red-700 text-sm border border-red-200"
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
                @if($retryAfterTimestamp) wire:poll.1s="checkRetry" @endif
            >
                <span>{{ $error }}</span>
                <span x-show="retryAfter && secondsLeft > 0" x-cloak x-transition>— {{ __('flood-watch.dashboard.retry_in_prefix') }} <span x-text="secondsLeft"></span> {{ __('flood-watch.dashboard.retry_in_suffix') }}</span>
                <span x-show="retryAfter && secondsLeft === 0" x-cloak x-transition>— {{ __('flood-watch.dashboard.retry_now') }}</span>
            </div>
        @endif
        @if ($lastChecked)
            <span class="text-sm text-slate-500 shrink-0">{{ __('flood-watch.dashboard.last_checked') }}: {{ \Carbon\Carbon::parse($lastChecked)->format('j M Y, g:i a') }}</span>
        @endif
        <input
            type="text"
            id="location"
            wire:model="location"
            placeholder="{{ __('flood-watch.dashboard.location_placeholder') }}"
            class="block w-full sm:w-48 min-h-[44px] rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base sm:text-sm"
        />
        <button
            type="button"
            wire:click="search"
            @click="window.__loadLeaflet && window.__loadLeaflet()"
            wire:loading.attr="disabled"
            @if($retryAfterTimestamp && !$canRetry) disabled @endif
            class="min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-3 sm:py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
        >
            <span wire:loading.remove wire:target="search">{{ __('flood-watch.dashboard.check_my_location') }}</span>
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
