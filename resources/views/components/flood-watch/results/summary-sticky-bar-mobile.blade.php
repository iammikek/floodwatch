@props([
    'assistantResponse' => '',
])

@php
    $body = preg_replace('/^#+\s*Current\s+Status\s*\n+/i', '', trim($assistantResponse));
    $body = trim(preg_replace('/#+\s*Action\s+Steps\s*\n+.*?(?=\n\s*#+\s|\z)/si', '', $body));
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags(\Illuminate\Support\Str::markdown($body))));
    $teaser = $plain === '' ? '' : \Illuminate\Support\Str::limit($plain, 80);
@endphp
@if ($assistantResponse !== '')
<div
    class="fixed bottom-0 left-0 right-0 z-40 p-3 bg-slate-800 text-white border-t border-slate-600 shadow-lg lg:hidden"
    style="display: none;"
    x-data="{ show: false }"
    x-init="
        const onScroll = () => { show = window.scrollY > 280 };
        window.addEventListener('scroll', onScroll);
        const unwatch = $watch('show', v => { document.body.style.paddingBottom = v ? '4rem' : '0'; });
        return () => {
            window.removeEventListener('scroll', onScroll);
            document.body.style.paddingBottom = '0';
            if (typeof unwatch === 'function') unwatch();
        }
    "
    x-show="show"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="translate-y-full opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-full opacity-0"
>
    <div class="max-w-xl mx-auto flex items-center justify-between gap-3">
        <p class="text-xs text-slate-200 truncate flex-1 min-w-0" title="{{ $teaser }}">AI: {{ $teaser }}</p>
        <button
            type="button"
            @click="window.dispatchEvent(new CustomEvent('open-summary')); document.getElementById('ai-advice')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
            class="shrink-0 text-xs font-medium text-blue-300 hover:text-blue-200 underline"
        >
            {{ __('flood-watch.dashboard.open_full_summary') }}
        </button>
    </div>
</div>
@endif
