@props([
    'lastChecked' => null,
    'autoRefreshEnabled' => false,
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
    </div>
</div>
