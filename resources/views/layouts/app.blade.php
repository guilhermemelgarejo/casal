<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DuoZen') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @include('layouts.partials.assets')
    </head>
    <body class="bg-body-secondary">
        <div class="min-vh-100 d-flex flex-column">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white shadow-sm border-bottom">
                    <div class="container-xxl py-3 px-3 px-lg-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="flex-grow-1">
                {{ $slot }}
            </main>
        </div>
        @stack('scripts')
        @include('layouts.partials.scripts')
    </body>
</html>
