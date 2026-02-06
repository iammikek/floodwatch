<div class="min-h-screen bg-slate-50 p-4 sm:p-6 pb-safe">
    <div class="max-w-3xl mx-auto w-full">
        <div class="mb-6">
            <a href="{{ route('flood-watch.dashboard') }}" class="text-sm text-blue-600 hover:text-blue-700 mb-2 inline-block">
                ← {{ __('flood-watch.dashboard.title') }}
            </a>
            <h1 class="text-2xl font-semibold text-slate-900">
                {{ __('flood-watch.activities.title') }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ __('flood-watch.activities.subtitle') }}
            </p>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="divide-y divide-slate-100">
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
                        time-format="d M H:i"
                        :class="$severityClasses . ' py-3 px-4'"
                    />
                @empty
                    <p class="text-sm text-slate-500 p-6">{{ __('flood-watch.activities.empty') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
