@props([
    'activities',
])

<aside id="activity-feed" class="w-full lg:w-72 shrink-0 min-h-0 bg-white rounded-lg border border-slate-200 overflow-hidden flex flex-col">
    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3 border-b border-slate-100">
        {{ __('flood-watch.dashboard.activity_feed_title') }}
    </h3>
    <div class="flex-1 overflow-y-auto p-2">
        @forelse ($activities as $activity)
            @php
                $severityClasses = match ($activity->severity) {
                    'severe' => 'bg-red-50 border-l-4 border-l-red-500',
                    'high' => 'bg-amber-50 border-l-4 border-l-amber-500',
                    'moderate' => 'bg-slate-50 border-l-4 border-l-slate-300',
                    default => '',
                };
            @endphp
            <x-activity-item
                :activity="$activity"
                time-format="H:i"
                :class="$severityClasses . ($loop->last ? '' : ' border-b border-slate-100')"
            />
        @empty
            <p class="text-sm text-slate-500 p-4">{{ __('flood-watch.dashboard.activity_feed_empty') }}</p>
        @endforelse
    </div>
    <a href="{{ route('activities') }}" class="block text-center text-sm text-blue-600 hover:text-blue-700 py-2 border-t border-slate-100">
        {{ __('flood-watch.dashboard.activity_feed_view_all') }}
    </a>
</aside>
