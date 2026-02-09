@props([
    'assistantResponse' => '',
])

<div class="p-4 rounded-lg bg-white shadow-sm border border-slate-200 prose prose-slate max-w-none">
    <h2 class="text-lg font-medium text-slate-900 mb-2">{{ __('flood-watch.dashboard.summary') }}</h2>
    {!! Str::markdown($assistantResponse) !!}
</div>
