@php
    request()->session()->put('flood_watch_loaded', true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('flood-watch.cockpit.title') }} — {{ config('app.name') }}</title>
    @vite(['resources/js/cockpit/main.js'])
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white px-4 py-2">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="text-sm font-medium text-slate-700 hover:text-slate-900">{{ config('app.name') }}</a>
                <span class="text-xs uppercase tracking-wide text-slate-400">{{ __('flood-watch.cockpit.badge') }}</span>
            </div>
            <a href="{{ route('legacy.dashboard') }}" class="text-sm text-blue-600 hover:text-blue-700">{{ __('flood-watch.cockpit.legacy_dashboard') }}</a>
        </div>
    </header>
    <div id="app"></div>
</body>
</html>
