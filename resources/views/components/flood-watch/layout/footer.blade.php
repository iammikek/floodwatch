@props([
    'showSectionLinks' => false,
])

<footer class="mt-12 pt-6 border-t border-slate-200">
    @if ($showSectionLinks)
        <nav class="flex flex-wrap gap-x-4 gap-y-1 mb-4 text-sm text-slate-600" aria-label="{{ __('flood-watch.dashboard.summary') }}">
            <a href="#road-status" class="underline hover:text-slate-800">{{ __('flood-watch.dashboard.road_status') }}</a>
            <span aria-hidden="true">·</span>
            <a href="#forecast" class="underline hover:text-slate-800">{{ __('flood-watch.dashboard.forecast') }}</a>
            <span aria-hidden="true">·</span>
            <a href="#map-section" class="underline hover:text-slate-800">{{ __('flood-watch.dashboard.river_levels') }}</a>
        </nav>
    @endif
    @if (config('flood-watch.donation_url'))
        <p class="text-xs text-slate-500 mb-2">
            {{ __('flood-watch.dashboard.free_to_use') }}
            <a href="{{ config('flood-watch.donation_url') }}" target="_blank" rel="noopener" class="underline hover:text-slate-600">{{ __('flood-watch.dashboard.support_development') }}</a>.
        </p>
    @endif
    <p class="text-xs text-slate-500">
        An <a href="https://automica.io" target="_blank" rel="noopener" class="underline hover:text-slate-600">automica labs</a> project.
        Data: Environment Agency flood and river level data from the
        <a href="https://environment.data.gov.uk/flood-monitoring/doc/reference" target="_blank" rel="noopener" class="underline hover:text-slate-600">Real-Time data API</a>
        (Open Government Licence).
        National Highways road and lane closure data (DATEX II v3.4) from the
        <a href="https://developer.data.nationalhighways.co.uk/" target="_blank" rel="noopener" class="underline hover:text-slate-600">Developer Portal</a>.
        Weather from <a href="https://open-meteo.com/" target="_blank" rel="noopener" class="underline hover:text-slate-600">Open-Meteo</a> (CC-BY 4.0).
        Geocoding by <a href="https://postcodes.io" target="_blank" rel="noopener" class="underline hover:text-slate-600">postcodes.io</a> and <a href="https://nominatim.openstreetmap.org" target="_blank" rel="noopener" class="underline hover:text-slate-600">OpenStreetMap Nominatim</a>.
    </p>
</footer>
