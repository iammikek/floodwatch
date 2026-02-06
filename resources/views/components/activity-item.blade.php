@props([
    'activity',
    'timeFormat' => 'H:i',
    'class' => '',
])

@php
    $typeLabels = [
        'flood_warning' => __('flood-watch.activities.type_flood_warning'),
        'road_closure' => __('flood-watch.activities.type_road_closure'),
        'road_reopened' => __('flood-watch.activities.type_road_reopened'),
        'river_level_elevated' => __('flood-watch.activities.type_river_level_elevated'),
    ];
    $severityLabels = [
        'severe' => 'Severe',
        'high' => 'High',
        'moderate' => 'Moderate',
        'low' => 'Low',
    ];
    $typeLabel = $typeLabels[$activity->type] ?? $activity->type;
    $severityLabel = $severityLabels[$activity->severity] ?? $activity->severity;
    $metadata = $activity->metadata ?? [];
@endphp

<div
    {{ $attributes->merge(['class' => 'relative flex gap-2 py-2 px-2 -mx-2 rounded cursor-help ' . $class]) }}
    x-data="{
        show: false,
        x: 0,
        y: 0,
        updatePosition() {
            const r = $el.getBoundingClientRect();
            this.x = Math.min(r.left, window.innerWidth - 320);
            this.y = r.bottom + 6;
        }
    }"
    @mouseenter="updatePosition(); show = true"
    @mouseleave="show = false"
    @focusin="updatePosition(); show = true"
    @focusout="show = false"
    @click="updatePosition(); show = !show"
    @click.outside="show = false"
    tabindex="0"
    role="button"
    aria-haspopup="true"
    :aria-expanded="show"
>
    <span class="text-xs text-slate-500 shrink-0">{{ $activity->occurred_at->format($timeFormat) }}</span>
    <p class="text-sm text-slate-700 flex-1 min-w-0">{{ $activity->description }}</p>

    <template x-teleport="body">
        <div
            x-show="show"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed z-[9999] p-3 rounded-lg bg-slate-800 text-slate-100 text-xs shadow-xl max-w-sm w-[280px]"
            :style="`left: ${x}px; top: ${y}px`"
            @click.stop
        >
            <dl class="space-y-1.5">
                <div class="flex gap-2">
                    <dt class="text-slate-400 shrink-0">{{ __('flood-watch.activities.tooltip_type') }}:</dt>
                    <dd>{{ $typeLabel }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-slate-400 shrink-0">{{ __('flood-watch.activities.tooltip_severity') }}:</dt>
                    <dd>{{ $severityLabel }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-slate-400 shrink-0">{{ __('flood-watch.activities.tooltip_time') }}:</dt>
                    <dd>{{ $activity->occurred_at->format('j M Y, g:i a') }}</dd>
                </div>
                <div class="pt-1.5 mt-1.5 border-t border-slate-600">
                    <dd class="text-slate-200">{{ $activity->description }}</dd>
                </div>
                @if (!empty($metadata))
                    <div class="pt-1.5 mt-1.5 border-t border-slate-600 space-y-1">
                        @if (!empty($metadata['road']))
                            <div class="flex gap-2">
                                <dt class="text-slate-400 shrink-0">{{ __('flood-watch.dashboard.road') }}:</dt>
                                <dd>{{ $metadata['road'] }}</dd>
                            </div>
                        @endif
                        @if (!empty($metadata['floodAreaID']))
                            <div class="flex gap-2">
                                <dt class="text-slate-400 shrink-0">Area ID:</dt>
                                <dd class="font-mono text-[11px]">{{ $metadata['floodAreaID'] }}</dd>
                            </div>
                        @endif
                        @if (!empty($metadata['station']))
                            <div class="flex gap-2">
                                <dt class="text-slate-400 shrink-0">Station:</dt>
                                <dd>{{ $metadata['station'] }}</dd>
                            </div>
                        @endif
                    </div>
                @endif
            </dl>
        </div>
    </template>
</div>
