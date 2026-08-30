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
    <style>
        .fw-topnav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            max-width: 1180px;
            margin: 0 auto;
            padding: 0.35rem 1.25rem;
            font-size: 0.75rem;
            line-height: 1.2;
            color: #6b7280;
        }
        .fw-topnav a {
            color: inherit;
            text-decoration: none;
        }
        .fw-topnav a:hover { color: #111827; }
        .fw-topnav__brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }
        .fw-topnav__brand strong {
            font-weight: 600;
            color: #374151;
        }
        .fw-topnav__links {
            display: flex;
            align-items: center;
            gap: 0.15rem;
            flex-shrink: 0;
        }
        .fw-topnav__links a {
            padding: 0.2rem 0.45rem;
            border-radius: 0.25rem;
        }
        .fw-topnav__links a[aria-current="page"] {
            color: #111827;
            background: #e5e7eb;
            font-weight: 600;
        }
        .fw-topnav-wrap {
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
    <nav class="fw-topnav-wrap" aria-label="{{ __('flood-watch.cockpit.nav_label') }}">
        <div class="fw-topnav">
            <div class="fw-topnav__brand">
                <a href="{{ route('home') }}"><strong>{{ config('app.name') }}</strong></a>
            </div>
            <div class="fw-topnav__links">
                <a href="{{ route('home') }}" aria-current="page">{{ __('flood-watch.cockpit.nav_cockpit') }}</a>
                <a href="{{ route('legacy.dashboard') }}">{{ __('flood-watch.cockpit.nav_classic') }}</a>
            </div>
        </div>
    </nav>
    <div id="app"></div>
</body>
</html>
