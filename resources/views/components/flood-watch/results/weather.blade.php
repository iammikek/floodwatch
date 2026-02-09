@props([
    'weather' => [],
])

<div id="weather">
    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.weather_forecast') }}</h2>
    @if (count($weather) > 0)
        <div class="flex flex-nowrap gap-3 overflow-x-auto pb-2 -mx-1 scrollbar-hide">
            @foreach ($weather as $day)
                <div class="flex-1 min-w-[6.5rem] sm:min-w-[7rem] p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-center shrink-0">
                    <p class="text-sm font-medium text-slate-600">
                        {{ \Carbon\Carbon::parse($day['date'])->format('D j M') }}
                    </p>
                    <p class="text-3xl my-2" title="{{ $day['description'] ?? '' }}">{{ $day['icon'] ?? '🌤️' }}</p>
                    <p class="text-slate-900 font-semibold">{{ round($day['temp_max'] ?? 0) }}° / {{ round($day['temp_min'] ?? 0) }}°</p>
                    @if (($day['precipitation'] ?? 0) > 0)
                        <p class="text-sm text-sky-600 mt-1">💧 {{ round($day['precipitation'], 1) }} mm</p>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">{{ $day['description'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    @else
        <p class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.no_weather') }}</p>
    @endif
</div>
