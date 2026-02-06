@props([
    'risk' => null,
])

<section id="risk-gauge" class="mb-6 p-4 rounded-lg bg-white shadow-sm border border-slate-200">
    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('flood-watch.dashboard.risk_gauge_title') }}</h2>
    @if ($risk)
        <div class="flex items-center gap-4">
            <div class="flex-1 h-3 bg-slate-100 rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    style="width: {{ $risk['index'] }}%; background: linear-gradient(to right, #22c55e 0%, #eab308 25%, #f97316 50%, #ef4444 75%);"
                ></div>
            </div>
            <span class="text-lg font-bold text-slate-900">{{ $risk['index'] }}</span>
            <span class="text-sm text-slate-500">/ 100</span>
        </div>
        <p class="text-sm text-slate-600 mt-1">{{ $risk['label'] }} · {{ $risk['summary'] }}</p>
    @else
        <p class="text-sm text-slate-500">{{ __('flood-watch.dashboard.risk_gauge_unavailable') }}</p>
    @endif
</section>
