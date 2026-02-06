@props([
    'loading' => false,
    'floods' => [],
    'incidents' => [],
    'forecast' => [],
    'assistantResponse' => null,
    'lastChecked' => null,
    'hasUserLocation' => false,
    'error' => null,
    'autoRefreshEnabled' => false,
    'retryAfterTimestamp' => null,
])

@if (!$loading && $assistantResponse)
    <div
        class="space-y-6 scroll-smooth"
        id="results"
        @if (!$error && $autoRefreshEnabled && auth()->check()) wire:poll.900s="search" @endif
    >
        <x-flood-watch.results-header
            :lastChecked="$lastChecked"
            :retryAfterTimestamp="$retryAfterTimestamp"
            :canRetry="$this->canRetry()"
        />
    </div>
@elseif (!$loading && ! $error)
    <p class="text-slate-500 text-sm">{{ __('flood-watch.dashboard.prompt') }}</p>
@endif
