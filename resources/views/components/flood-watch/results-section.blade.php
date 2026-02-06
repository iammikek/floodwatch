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

        <x-flood-watch.flood-warnings
            :floods="$floods"
            :hasUserLocation="$hasUserLocation"
        />

        <x-flood-watch.forecast-outlook :forecast="$forecast" />

        <x-flood-watch.road-status :incidents="$incidents" />

        <div class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 prose prose-slate max-w-none">
            <h2 class="text-lg font-medium text-slate-900 mb-2">{{ __('flood-watch.dashboard.summary') }}</h2>
            {!! Str::markdown($assistantResponse) !!}
        </div>
    </div>
@elseif (!$loading && ! $error)
    <p class="text-slate-500 text-sm">{{ __('flood-watch.dashboard.prompt') }}</p>
@endif
