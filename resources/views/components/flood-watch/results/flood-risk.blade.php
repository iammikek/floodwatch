@props([
    'floods' => [],
    'hasUserLocation' => false,
])

<div id="flood-risk">
    <h2 class="text-lg font-medium text-slate-900 mb-3">{{ __('flood-watch.dashboard.flood_warnings') }}</h2>
    @if (count($floods) > 0)
        @php
            $normalized = array_map(function ($f) {
                $level = isset($f['severityLevel']) ? (int) $f['severityLevel'] : 0;
                if ($level === 0) {
                    $label = strtolower(trim((string) ($f['severity'] ?? '')));
                    if (str_starts_with($label, 'severe')) {
                        $level = 1;
                    } elseif (str_contains($label, 'warning')) {
                        $level = 2;
                    } elseif (str_contains($label, 'alert')) {
                        $level = 3;
                    } else {
                        $level = 4;
                    }
                }
                $f['_effectiveSeverityLevel'] = $level;
                return $f;
            }, $floods);
            $warningsOnly = array_values(array_filter($normalized, fn ($f) => ($f['_effectiveSeverityLevel'] ?? 4) <= 2));
            $alertsOnly = array_values(array_filter($normalized, fn ($f) => ($f['_effectiveSeverityLevel'] ?? 4) === 3));
        @endphp
        @if (count($warningsOnly) > 0)
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Flood warnings ({{ count($warningsOnly) }})</h3>
            <ul class="divide-y divide-slate-200 bg-white">
                @foreach ($warningsOnly as $flood)
                    <li class="p-4 text-left overflow-visible">
                        @php
                            $title = trim((string) ($flood['description'] ?? ''));
                            $areaId = trim((string) ($flood['floodAreaID'] ?? ''));
                            $hasTitle = $title !== '';
                            $plain = !empty($flood['message']) ? trim(strip_tags((string) $flood['message'])) : '';
                            $lat = isset($flood['lat']) ? (float) $flood['lat'] : null;
                            $lng = isset($flood['lng']) ? (float) $flood['lng'] : (isset($flood['long']) ? (float) $flood['long'] : null);
                            $headline = '';
                            if ($plain !== '') {
                                $parts = preg_split('/(?<=[.!?])\s+/', $plain, 2);
                                $headline = trim($parts[0] ?? '');
                            }
                            $snippet = '';
                            if ($plain !== '') {
                                $rest = $headline !== '' ? trim(mb_substr($plain, mb_strlen($headline))) : $plain;
                                $snippet = mb_substr($rest, 0, 140).(mb_strlen($rest) > 140 ? '…' : '');
                            }
                        @endphp
                        <p class="font-medium text-slate-900">
                            @if ($hasTitle)
                                {{ $title }} 
                            @elseif ($headline !== '')
                                {{ $headline }} 
                            @elseif ($areaId !== '')
                                {{ __('flood-watch.dashboard.flood_area') }} {{ $areaId }} 
                            @elseif ($lat !== null && $lng !== null)
                                {{ __('flood-watch.dashboard.flood_area') }} {{ number_format($lat, 3) }}, {{ number_format($lng, 3) }}
                            @else
                                {{ __('flood-watch.dashboard.flood_area') }} 
                            @endif
                            @php
                                $sevLabel = strtolower(trim((string) ($flood['severity'] ?? '')));
                                $sevLevel = (int) ($flood['_effectiveSeverityLevel'] ?? ($flood['severityLevel'] ?? 0));
                                $sevClasses = 'inline-flex items-center ml-2 px-2 py-0.5 text-xs font-semibold rounded border';
                                if ($sevLevel === 1 || str_starts_with($sevLabel, 'severe')) {
                                    $sevClasses .= ' border-red-600/30 text-red-700 bg-red-600/10';
                                } elseif ($sevLevel === 2 || $sevLabel === 'flood warning') {
                                    $sevClasses .= ' border-amber-600/30 text-amber-700 bg-amber-500/10';
                                } elseif ($sevLevel === 3 || $sevLabel === 'flood alert') {
                                    $sevClasses .= ' border-yellow-600/30 text-yellow-700 bg-yellow-500/10';
                                } else {
                                    $sevClasses .= ' border-slate-300 text-slate-600 bg-slate-100';
                                }
                            @endphp
                            @if (!empty($flood['severity']))
                                <span class="{{ $sevClasses }}">{{ $flood['severity'] }}</span>
                            @endif
                        </p>
                        @if (!empty($flood['distanceKm']) && $hasUserLocation)
                            <p class="text-xs text-slate-500 mt-1">{{ __('flood-watch.dashboard.km_from_location', ['distance' => $flood['distanceKm']]) }}</p>
                        @endif
                        @if ($snippet !== '' && $snippet !== $headline)
                            <p class="text-sm text-slate-600 mt-1">{{ $snippet }}</p>
                        @endif
                        @if (!empty($flood['timeRaised']) || !empty($flood['timeMessageChanged']))
                            <p class="text-xs text-slate-500 mt-1">
                                @if (!empty($flood['timeRaised']))
                                    {{ __('flood-watch.dashboard.raised') }}: {{ \Carbon\Carbon::parse($flood['timeRaised'])->format('j M Y, g:i a') }}
                                @endif
                                @if (!empty($flood['timeMessageChanged']))
                                    @if (!empty($flood['timeRaised']))
                                        ·
                                    @endif
                                    {{ __('flood-watch.dashboard.updated') }}: {{ \Carbon\Carbon::parse($flood['timeMessageChanged'])->format('j M Y, g:i a') }}
                                @endif
                            </p>
                        @endif
                        @if (!empty($flood['message']) && $plain !== '' && $plain !== $headline)
                            <div x-data="{ open: false }" class="mt-2">
                                <button type="button" @click="open = !open"
                                        class="inline-flex items-center gap-2 cursor-pointer text-amber-600 hover:text-amber-700"
                                        aria-label="{{ __('flood-watch.dashboard.toggle_message') }}">
                                    <svg class="w-4 h-4 transition-transform duration-200" :class="open && 'rotate-180'"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    <span class="text-xs" x-text="open ? 'Hide details' : 'Show details'"></span>
                                </button>
                                <p x-show="open" x-cloak x-transition
                                   class="mt-2 text-sm text-slate-600 whitespace-pre-wrap break-words">{{ $flood['message'] }}</p>
                            </div>
                        @elseif (!empty($flood['message']) && ($snippet === '' || $plain === $headline))
                            <p class="mt-2 text-sm text-slate-600 whitespace-pre-wrap break-words">{{ $flood['message'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="p-4 bg-white border border-slate-200 text-slate-600">No flood warnings in view.</p>
        @endif
        @if (count($alertsOnly) > 0)
            <div x-data="{ open: false }" class="mt-3">
                <button type="button" @click="open = !open" class="text-xs font-semibold text-amber-700 hover:text-amber-800">
                    <span x-show="!open">Show flood alerts ({{ count($alertsOnly) }})</span>
                    <span x-show="open" x-cloak>Hide flood alerts ({{ count($alertsOnly) }})</span>
                </button>
                <ul x-show="open" x-cloak class="mt-2 divide-y divide-slate-200 bg-white">
                    @foreach ($alertsOnly as $flood)
                        <li class="p-4 text-left overflow-visible">
                            @php
                                $title = trim((string) ($flood['description'] ?? ''));
                                $areaId = trim((string) ($flood['floodAreaID'] ?? ''));
                                $hasTitle = $title !== '';
                                $plain = !empty($flood['message']) ? trim(strip_tags((string) $flood['message'])) : '';
                                $lat = isset($flood['lat']) ? (float) $flood['lat'] : null;
                                $lng = isset($flood['lng']) ? (float) $flood['lng'] : (isset($flood['long']) ? (float) $flood['long'] : null);
                                $headline = '';
                                if ($plain !== '') {
                                    $parts = preg_split('/(?<=[.!?])\s+/', $plain, 2);
                                    $headline = trim($parts[0] ?? '');
                                }
                                $snippet = '';
                                if ($plain !== '') {
                                    $rest = $headline !== '' ? trim(mb_substr($plain, mb_strlen($headline))) : $plain;
                                    $snippet = mb_substr($rest, 0, 140).(mb_strlen($rest) > 140 ? '…' : '');
                                }
                            @endphp
                            <p class="font-medium text-slate-900">
                                @if ($hasTitle)
                                    {{ $title }} 
                                @elseif ($headline !== '')
                                    {{ $headline }} 
                                @elseif ($areaId !== '')
                                    {{ __('flood-watch.dashboard.flood_area') }} {{ $areaId }} 
                                @elseif ($lat !== null && $lng !== null)
                                    {{ __('flood-watch.dashboard.flood_area') }} {{ number_format($lat, 3) }}, {{ number_format($lng, 3) }}
                                @else
                                    {{ __('flood-watch.dashboard.flood_area') }} 
                                @endif
                                @php
                                    $sevLabel = strtolower(trim((string) ($flood['severity'] ?? '')));
                                    $sevLevel = (int) ($flood['_effectiveSeverityLevel'] ?? ($flood['severityLevel'] ?? 0));
                                    $sevClasses = 'inline-flex items-center ml-2 px-2 py-0.5 text-xs font-semibold rounded border';
                                    if ($sevLevel === 1 || str_starts_with($sevLabel, 'severe')) {
                                        $sevClasses .= ' border-red-600/30 text-red-700 bg-red-600/10';
                                    } elseif ($sevLevel === 2 || $sevLabel === 'flood warning') {
                                        $sevClasses .= ' border-amber-600/30 text-amber-700 bg-amber-500/10';
                                    } elseif ($sevLevel === 3 || $sevLabel === 'flood alert') {
                                        $sevClasses .= ' border-yellow-600/30 text-yellow-700 bg-yellow-500/10';
                                    } else {
                                        $sevClasses .= ' border-slate-300 text-slate-600 bg-slate-100';
                                    }
                                @endphp
                                @if (!empty($flood['severity']))
                                    <span class="{{ $sevClasses }}">{{ $flood['severity'] }}</span>
                                @endif
                            </p>
                            @if (!empty($flood['distanceKm']) && $hasUserLocation)
                                <p class="text-xs text-slate-500 mt-1">{{ __('flood-watch.dashboard.km_from_location', ['distance' => $flood['distanceKm']]) }}</p>
                            @endif
                            @if ($snippet !== '' && $snippet !== $headline)
                                <p class="text-sm text-slate-600 mt-1">{{ $snippet }}</p>
                            @endif
                            @if (!empty($flood['timeRaised']) || !empty($flood['timeMessageChanged']))
                                <p class="text-xs text-slate-500 mt-1">
                                    @if (!empty($flood['timeRaised']))
                                        {{ __('flood-watch.dashboard.raised') }}: {{ \Carbon\Carbon::parse($flood['timeRaised'])->format('j M Y, g:i a') }}
                                    @endif
                                    @if (!empty($flood['timeMessageChanged']))
                                        @if (!empty($flood['timeRaised']))
                                            ·
                                        @endif
                                        {{ __('flood-watch.dashboard.updated') }}: {{ \Carbon\Carbon::parse($flood['timeMessageChanged'])->format('j M Y, g:i a') }}
                                    @endif
                                </p>
                            @endif
                            @if (!empty($flood['message']) && $plain !== '' && $plain !== $headline)
                                <div x-data="{ open: false }" class="mt-2">
                                    <button type="button" @click="open = !open"
                                            class="inline-flex items-center gap-2 cursor-pointer text-amber-600 hover:text-amber-700"
                                            aria-label="{{ __('flood-watch.dashboard.toggle_message') }}">
                                        <svg class="w-4 h-4 transition-transform duration-200" :class="open && 'rotate-180'"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 9l-7 7-7-7"/>
                                        </svg>
                                        <span class="text-xs" x-text="open ? 'Hide details' : 'Show details'"></span>
                                    </button>
                                    <p x-show="open" x-cloak x-transition
                                       class="mt-2 text-sm text-slate-600 whitespace-pre-wrap break-words">{{ $flood['message'] }}</p>
                                </div>
                            @elseif (!empty($flood['message']) && ($snippet === '' || $plain === $headline))
                                <p class="mt-2 text-sm text-slate-600 whitespace-pre-wrap break-words">{{ $flood['message'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @else
        <p class="p-4 bg-white shadow-sm border border-slate-200 text-slate-600">{{ __('flood-watch.dashboard.no_flood_warnings') }}</p>
    @endif
</div>
