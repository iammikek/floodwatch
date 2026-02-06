@props([
    'risk' => null,
    'incidents' => [],
])

<section id="risk-gauge" class="mb-6 p-4 rounded-lg bg-white shadow-sm border border-slate-200">
    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('flood-watch.dashboard.risk_gauge_title') }}</h2>
    <div class="flex flex-col sm:flex-row sm:items-start gap-4">
        <div class="flex-1 min-w-0">
            @if ($risk)
                <div class="flex items-center gap-4">
                    <div class="flex-1 h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            style="width: {{ $risk['index'] }}%; background: linear-gradient(to right, #22c55e 0%, #eab308 25%, #f97316 50%, #ef4444 75%);"
                        ></div>
                    </div>
                    <span class="text-lg font-bold text-slate-900 shrink-0">{{ $risk['index'] }}</span>
                    <span class="text-sm text-slate-500 shrink-0">/ 100</span>
                </div>
                <p class="text-sm text-slate-600 mt-1">{{ $risk['label'] }} · {{ $risk['summary'] }}</p>
            @else
                <p class="text-sm text-slate-500">{{ __('flood-watch.dashboard.risk_gauge_unavailable') }}</p>
            @endif
        </div>
        @php
            $monitoredTotal = count(config('flood-watch.incident_allowed_roads', [])) ?: config('flood-watch.status_grid_monitored_routes', 7);
        @endphp
        <div class="shrink-0 px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-center min-w-[140px]">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('flood-watch.dashboard.status_grid_infrastructure') }}</p>
            <p class="text-sm text-slate-700 mt-0.5">{{ __('flood-watch.dashboard.status_grid_incidents_on_routes', ['active' => count($incidents), 'total' => $monitoredTotal]) }}</p>
        </div>
    </div>
</section>
