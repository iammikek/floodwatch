@props([
    'assistantResponse' => '',
])

@php
    use Illuminate\Support\Str;
    $body = preg_replace('/^#+\s*Current\s+Status\s*\n+/i', '', trim($assistantResponse));
    $body = trim(preg_replace('/#+\s*Action\s+Steps\s*\n+.*?(?=\n\s*#+\s|\z)/si', '', $body));
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags(Str::markdown($body))));
    $teaser = $plain === '' ? '' : Str::limit($plain, 200);
@endphp
<div
    id="ai-advice"
    class="p-4 border-b-2 border-amber-200 bg-amber-50/70 rounded-lg"
    x-data="{ open: false }"
    @open-summary.window="open = true"
>
    <p class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('flood-watch.dashboard.ai_advice') }}</p>
    @if ($assistantResponse !== '')
        <p class="text-sm text-slate-700" x-show="!open" x-transition>{{ $teaser }}</p>
        <div
            x-show="open"
            x-cloak
            x-transition
            class="text-sm text-slate-700 prose prose-slate max-w-none mt-2 [&_li]:pb-1"
        >
            {!! Str::markdown($body) !!}
        </div>
        <button
            type="button"
            @click="open = !open"
            class="mt-3 text-sm font-medium text-blue-600 flex items-center gap-1 hover:text-blue-700"
            :aria-expanded="open"
        >
                <span x-text="open ? '{{ __('flood-watch.dashboard.show_less') }}' : '{{ __('flood-watch.dashboard.read_full_summary') }}'"></span>
                <svg class="w-4 h-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
    @else
        <p class="text-sm text-slate-500">{{ __('flood-watch.dashboard.prompt') }}</p>
    @endif
</div>
