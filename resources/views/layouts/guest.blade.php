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
        <div class="min-vh-100 d-flex flex-column justify-content-center align-items-center py-4">
            <div class="mb-0" style="position: relative; z-index: 0;">
                <a href="{{ route('login') }}" class="d-inline-block text-secondary">
                    <img
                        src="{{ asset('images/duozen-logo-full.png') }}"
                        alt="{{ config('app.name', 'DuoZen') }}"
                        class="img-fluid"
                        style="width: min(24rem, 92vw); height: auto;"
                    />
                </a>
            </div>
            <div class="card shadow-sm w-100" style="max-width: 28rem; margin-top: -4rem; position: relative; z-index: 1;">
                <div class="card-body p-4">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @include('layouts.partials.scripts')
    </body>
</html>
