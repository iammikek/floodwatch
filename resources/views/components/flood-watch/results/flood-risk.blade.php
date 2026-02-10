@php use Carbon\Carbon; @endphp
@props([
    'floods' => [],
    'hasUserLocation' => false,
])

<div id="flood-risk">
    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.flood_warnings') }}</h2>
    @if (count($floods) > 0)
        <ul class="divide-y divide-slate-200 bg-white">
            @foreach ($floods as $flood)
                <li class="p-4 text-left overflow-visible">
                    <p class="font-medium text-slate-900">{{ $flood['description'] ?? __('flood-watch.dashboard.flood_area') }}</p>
                    <p class="text-sm text-amber-600 mt-1">{{ $flood['severity'] ?? '' }}
                        @if (!empty($flood['distanceKm']) && $hasUserLocation)
                            <span
                                class="text-xs text-slate-500 mt-1">{{ __('flood-watch.dashboard.km_from_location', ['distance' => $flood['distanceKm']]) }}</span>
                        @endif
                    </p>

                    @if (!empty($flood['timeRaised']) || !empty($flood['timeMessageChanged']))
                        <p class="text-xs text-slate-500 mt-1">
                            @if (!empty($flood['timeRaised']))
                                {{ __('flood-watch.dashboard.raised') }}
                                : {{ Carbon::parse($flood['timeRaised'])->format('j M Y, g:i a') }}
                            @endif
                            @if (!empty($flood['timeMessageChanged']))
                                @if (!empty($flood['timeRaised']))
                                    ·
                                @endif
                                {{ __('flood-watch.dashboard.updated') }}
                                : {{ Carbon::parse($flood['timeMessageChanged'])->format('j M Y, g:i a') }}
                            @endif
                        </p>
                    @endif
                    @if (!empty($flood['message']))
                        <div x-data="{ open: false }" class="mt-2">
                            <button type="button" @click="open = !open"
                                    class="flex items-center gap-2 cursor-pointer text-amber-600 hover:text-amber-700"
                                    aria-label="{{ __('flood-watch.dashboard.toggle_message') }}">
                                <svg class="w-4 h-4 transition-transform duration-200" :class="open && 'rotate-180'"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <p x-show="open" x-cloak x-transition
                               class="mt-2 text-sm text-slate-600 whitespace-pre-wrap break-words">{{ $flood['message'] }}</p>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <p class="p-4 bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.no_flood_warnings') }}</p>
    @endif
</div>
