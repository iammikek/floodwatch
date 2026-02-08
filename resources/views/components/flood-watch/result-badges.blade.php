@props([
    'hasResults' => false,
    'floodStatus' => null,
    'roadStatus' => null,
    'forecastStatus' => null,
    'weatherStatus' => null,
    'riverStatus' => null,
])

<div class="flex flex-nowrap sm:flex-wrap gap-2 sm:gap-3 mb-6 overflow-x-auto pb-1 -mx-1 scrollbar-hide">
    <a href="#flood-risk" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800 hover:bg-amber-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
        {{ __('flood-watch.dashboard.flood_risk') }}
        @if($floodStatus)
            <span class="opacity-90">· {{ $floodStatus }}</span>
        @endif
    </a>
    <a href="#road-status" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
        {{ __('flood-watch.dashboard.road_status') }}
        @if($roadStatus)
            <span class="opacity-90">· {{ $roadStatus }}</span>
        @endif
    </a>
    <a href="#forecast" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 hover:bg-emerald-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
        {{ __('flood-watch.dashboard.forecast') }}
        @if($forecastStatus)
            <span class="opacity-90">· {{ $forecastStatus }}</span>
        @endif
    </a>
    <a href="#weather" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-sky-100 text-sky-800 hover:bg-sky-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
        {{ __('flood-watch.dashboard.weather') }}
        @if($weatherStatus)
            <span class="opacity-90">· {{ $weatherStatus }}</span>
        @endif
    </a>
    <a href="#map-section" class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1 rounded-full text-sm font-medium bg-slate-100 text-slate-800 hover:bg-slate-200 transition-colors shrink-0 {{ $hasResults ? 'cursor-pointer' : 'pointer-events-none cursor-default' }}">
        {{ __('flood-watch.dashboard.map') }}
        @if($riverStatus)
            <span class="opacity-90">· {{ $riverStatus }}</span>
        @endif
    </a>
</div>
