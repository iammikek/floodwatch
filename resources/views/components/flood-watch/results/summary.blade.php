@props([
    'assistantResponse' => '',
])

@php
    use Illuminate\Support\Str;
    $body = trim(preg_replace('/#+\s*Action\s+Steps\s*\n+.*?(?=\n\s*#+\s|\z)/si', '', trim($assistantResponse)));
@endphp
<div class="p-4 bg-white shadow-sm border border-slate-200 prose prose-slate max-w-none [&_li]:pb-1">
    <h2 class="text-lg font-medium text-slate-900 mb-2">{{ __('flood-watch.dashboard.summary') }}</h2>
    {!! Str::markdown($body) !!}
</div>
