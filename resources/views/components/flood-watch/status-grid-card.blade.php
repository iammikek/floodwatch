@props([
    'title',
    'id' => null,
])

<div
    @if ($id) id="{{ $id }}" @endif
    {{ $attributes->merge(['class' => 'rounded-lg bg-white shadow-sm border border-slate-200 min-h-0 overflow-hidden flex flex-col']) }}
>
    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide px-4 py-3 border-b border-slate-100 shrink-0">{{ $title }}</p>
    <div class="flex-1 overflow-y-auto p-4 min-h-0">
        {{ $slot }}
    </div>
</div>
