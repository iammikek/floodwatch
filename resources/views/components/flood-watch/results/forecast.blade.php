@props([
    'forecast' => [],
])

<div id="forecast">
    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.forecast_outlook') }}</h2>
    @if (count($forecast) > 0 && !empty($forecast['england_forecast']))
        <div class="p-4 bg-white shadow-sm border border-slate-200">
            <p class="text-slate-600">{{ $forecast['england_forecast'] }}</p>
            @if (!empty($forecast['flood_risk_trend']))
                <p class="text-sm text-slate-500 mt-2">
                    {{ __('flood-watch.dashboard.trend') }}: @foreach ($forecast['flood_risk_trend'] as $day => $trend){{ ucfirst($day) }}: {{ $trend }}@if (!$loop->last) → @endif @endforeach
                </p>
            @endif
            @if (!empty($forecast['issued_at']))
                <p class="text-xs text-slate-400 mt-1">{{ __('flood-watch.dashboard.issued') }}: {{ \Carbon\Carbon::parse($forecast['issued_at'])->format('j M Y, g:i') }}</p>
            @endif
        </div>
    @else
        <p class="p-4 bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.no_forecast') }}</p>
    @endif
</div>
