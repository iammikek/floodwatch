@props([
    'floods' => [],
    'incidents' => [],
    'lastChecked' => null,
])

@php
    $floodCount = count($floods);
    $incidentCount = count($incidents);

    $floodsText = $floodCount === 0
        ? __('flood-watch.dashboard.mobile_summary_no_floods')
        : trans_choice('flood-watch.dashboard.mobile_summary_floods', $floodCount, ['count' => $floodCount]);

    $roadsText = $incidentCount === 0
        ? __('flood-watch.dashboard.mobile_summary_roads_clear')
        : trans_choice('flood-watch.dashboard.mobile_summary_roads', $incidentCount, ['count' => $incidentCount]);

    $lastUpdatedTime = $lastChecked
        ? \Carbon\Carbon::parse($lastChecked)->format('g:i a')
        : null;
@endphp

<div class="mt-6 pt-4 border-t border-slate-200">
    <p class="text-sm text-slate-600">{{ $floodsText }} · {{ $roadsText }}@if ($lastUpdatedTime) · {{ $lastUpdatedTime }}@endif</p>
</div>
