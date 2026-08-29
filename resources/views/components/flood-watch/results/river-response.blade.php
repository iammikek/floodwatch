@props([
    'riverLevels' => [],
])

@php
    $counts = ['elevated' => 0, 'expected' => 0, 'low' => 0, 'unknown' => 0];
    foreach ($riverLevels as $level) {
        $status = (string) ($level['levelStatus'] ?? 'unknown');
        if (! array_key_exists($status, $counts)) {
            $status = 'unknown';
        }
        $counts[$status]++;
    }

    $total = max(1, count($riverLevels));
    $topStations = collect($riverLevels)
        ->sortByDesc(fn (array $level) => [
            ($level['levelStatus'] ?? '') === 'elevated' ? 1 : 0,
            isset($level['value']) && is_numeric($level['value']) ? (float) $level['value'] : -INF,
        ])
        ->take(4)
        ->values();

    $segments = [
        ['key' => 'elevated', 'class' => 'bg-red-500', 'count' => $counts['elevated']],
        ['key' => 'expected', 'class' => 'bg-blue-500', 'count' => $counts['expected']],
        ['key' => 'low', 'class' => 'bg-slate-400', 'count' => $counts['low']],
        ['key' => 'unknown', 'class' => 'bg-slate-200', 'count' => $counts['unknown']],
    ];
@endphp

<section id="river-response" class="p-4 bg-white border border-slate-200 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="lg:w-80">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('flood-watch.dashboard.river_response') }}</p>
            <h2 class="mt-2 text-lg font-semibold text-slate-900">
                {{ __('flood-watch.dashboard.river_response_headline', ['count' => count($riverLevels)]) }}
            </h2>
            <p class="mt-2 text-sm text-slate-600">{{ __('flood-watch.dashboard.river_response_copy') }}</p>
        </div>

        <div class="flex-1">
            <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                @foreach ($segments as $segment)
                    @if ($segment['count'] > 0)
                        <div class="{{ $segment['class'] }} h-full float-left" style="width: {{ round(($segment['count'] / $total) * 100, 2) }}%"></div>
                    @endif
                @endforeach
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-4">
                <div class="rounded border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('flood-watch.dashboard.elevated') }}</p>
                    <p class="mt-1 text-xl font-semibold text-red-700">{{ $counts['elevated'] }}</p>
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('flood-watch.dashboard.expected') }}</p>
                    <p class="mt-1 text-xl font-semibold text-blue-700">{{ $counts['expected'] }}</p>
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('flood-watch.dashboard.low') }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-700">{{ $counts['low'] }}</p>
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('flood-watch.dashboard.monitored_stations') }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ count($riverLevels) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <h3 class="text-sm font-semibold text-slate-700">{{ __('flood-watch.dashboard.priority_gauges') }}</h3>
        @if ($topStations->isEmpty())
            <p class="mt-2 text-sm text-slate-600">{{ __('flood-watch.dashboard.no_river_levels') }}</p>
        @else
            <div class="mt-2 grid gap-3 lg:grid-cols-2">
                @foreach ($topStations as $station)
                    @php
                        $status = (string) ($station['levelStatus'] ?? 'unknown');
                        $statusClasses = match ($status) {
                            'elevated' => 'text-red-700 bg-red-50 border-red-200',
                            'expected' => 'text-blue-700 bg-blue-50 border-blue-200',
                            'low' => 'text-slate-700 bg-slate-100 border-slate-200',
                            default => 'text-slate-600 bg-slate-50 border-slate-200',
                        };
                    @endphp
                    <div class="rounded border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $station['station'] ?? __('flood-watch.dashboard.river_gauge') }}</p>
                                <p class="text-sm text-slate-600">{{ $station['river'] ?? '' }}</p>
                            </div>
                            <span class="inline-flex items-center rounded border px-2 py-1 text-xs font-semibold {{ $statusClasses }}">
                                {{ match($status) {
                                    'elevated' => __('flood-watch.dashboard.elevated'),
                                    'expected' => __('flood-watch.dashboard.expected'),
                                    'low' => __('flood-watch.dashboard.low'),
                                    default => __('flood-watch.dashboard.updated'),
                                } }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-700">
                            <span class="font-semibold">
                                {{ isset($station['value']) && is_numeric($station['value']) ? number_format((float) $station['value'], 2) : '—' }}
                                {{ $station['unit'] ?? 'm' }}
                            </span>
                            @if (!empty($station['dateTime']))
                                <span class="text-slate-500">· {{ \Carbon\Carbon::parse($station['dateTime'])->format('j M, H:i') }}</span>
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
