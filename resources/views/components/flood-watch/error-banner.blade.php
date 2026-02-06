@props([
    'error',
    'retryAfterTimestamp' => null,
])

<div
    class="mb-6 p-4 rounded-lg bg-red-50 text-red-700 text-sm"
    @if($retryAfterTimestamp) wire:poll.1s="checkRetry" @endif
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
>
    <span>{{ $error }}</span>
    <span x-show="retryAfter && secondsLeft > 0" x-cloak x-transition> — {{ __('flood-watch.dashboard.retry_in_prefix') }} <span x-text="secondsLeft"></span> {{ __('flood-watch.dashboard.retry_in_suffix') }}</span>
    <span x-show="retryAfter && secondsLeft === 0" x-cloak x-transition> — {{ __('flood-watch.dashboard.retry_now') }}</span>
</div>
