@props(['class' => ''])

<nav
    class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600 {{ $class }}"
    aria-label="{{ __('flood-watch.dashboard.summary') }}"
>
    <a href="#road-status" class="underline hover:text-slate-800">{{ __('flood-watch.dashboard.road_status') }}</a>
    <span aria-hidden="true">·</span>
    <a href="#weather" class="underline hover:text-slate-800">{{ __('flood-watch.dashboard.weather_forecast') }}</a>
    <span aria-hidden="true">·</span>
    <a href="#flood-risk" class="underline hover:text-slate-800">{{ __('flood-watch.dashboard.flood_warnings') }}</a>
</nav>
