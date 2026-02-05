<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div class="w-full mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg {{ isset($left) ? 'sm:max-w-2xl' : 'sm:max-w-md' }}">
                @isset($left)
                    <div class="grid sm:grid-cols-2 gap-6 sm:gap-8">
                        <div class="order-2 sm:order-1 flex flex-col justify-center">
                            {{ $left }}
                        </div>
                        <div class="order-1 sm:order-2">
                            {{ $slot }}
                        </div>
                    </div>
                @else
                    {{ $slot }}
                @endisset
            </div>
        </div>
    </body>
</html>
