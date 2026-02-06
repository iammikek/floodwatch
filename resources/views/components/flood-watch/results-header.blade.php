@props([
    'lastChecked' => null,
    'retryAfterTimestamp' => null,
    'canRetry' => true,
])

<div class="flex flex-wrap items-center justify-between gap-3">
    @if ($lastChecked)
        <p class="text-sm text-slate-500">
            {{ __('flood-watch.dashboard.last_checked') }}: {{ \Carbon\Carbon::parse($lastChecked)->format('j M Y, g:i a') }}
        </p>
    @endif
    <div class="flex flex-wrap items-center gap-3">
        @auth
        <div
            x-data="{
                lastChecked: @js($lastChecked),
                nextRefreshAt: null,
                minutesLeft: null,
                nextRefreshTemplate: @js(__('flood-watch.dashboard.next_refresh')),
                refreshText: @js(__('flood-watch.dashboard.refreshing')),
                init() {
                    this.update();
                    setInterval(() => this.update(), 60000);
                },
                update() {
                    if (!this.lastChecked) return;
                    const last = new Date(this.lastChecked);
                    if (isNaN(last.getTime())) return;
                    this.nextRefreshAt = new Date(last.getTime() + 15 * 60 * 1000);
                    const diff = this.nextRefreshAt - Date.now();
                    this.minutesLeft = Math.max(0, Math.ceil(diff / 60000));
                }
            }"
            x-init="init()"
            class="contents"
        >
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    wire:model.live="autoRefreshEnabled"
                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <span class="text-sm text-slate-600">{{ __('flood-watch.dashboard.auto_refresh') }}</span>
            </label>
            <span
                x-show="$wire.autoRefreshEnabled && lastChecked && minutesLeft !== null"
                x-cloak
                x-transition
                class="text-sm text-slate-500"
                x-text="minutesLeft > 0 ? nextRefreshTemplate.replace(':minutes', minutesLeft).replace(':time', nextRefreshAt.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})) : refreshText"
            ></span>
        </div>
        @endauth
        <button
            type="button"
            wire:click="search"
            @click="window.__loadLeaflet && window.__loadLeaflet()"
            wire:loading.attr="disabled"
            @if($retryAfterTimestamp && !$canRetry) disabled @endif
            class="min-h-[44px] min-w-[44px] inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="search">{{ __('flood-watch.dashboard.refresh') }}</span>
            <span wire:loading wire:target="search" class="inline-flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ __('flood-watch.dashboard.refreshing') }}
            </span>
        </button>
    </div>
</div>
