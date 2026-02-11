@props([])

<div class="contents">
    <h1 class="text-xl sm:text-2xl font-semibold text-slate-900 shrink-0">
        {{ __('flood-watch.dashboard.title') }}
    </h1>

    <div class="flex items-center gap-3 shrink-0">
        @guest
            <a href="{{ route('login') }}" class="text-sm text-slate-600 hover:text-slate-800">{{ __('Log in') }}</a>
            <a href="{{ route('register') }}" class="text-sm text-blue-600 hover:text-blue-700">{{ __('Register') }}</a>
        @endguest

        {{ $slot ?? '' }}
    </div>
</div>
