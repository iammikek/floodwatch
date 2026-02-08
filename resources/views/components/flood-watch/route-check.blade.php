<div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200">
    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">
        {{ __('flood-watch.dashboard.route_check') }}
    </h2>
    <div class="flex flex-col sm:flex-row gap-2">
        <input
            type="text"
            wire:model="routeFrom"
            placeholder="{{ __('flood-watch.dashboard.route_check_from') }}"
            class="block flex-1 min-h-[44px] rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base sm:text-sm"
        />
        <input
            type="text"
            wire:model="routeTo"
            placeholder="{{ __('flood-watch.dashboard.route_check_to') }}"
            class="block flex-1 min-h-[44px] rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base sm:text-sm"
        />
        <button
            type="button"
            class="min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-3 sm:py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            {{ __('flood-watch.dashboard.route_check_button') }}
        </button>
    </div>
</div>
