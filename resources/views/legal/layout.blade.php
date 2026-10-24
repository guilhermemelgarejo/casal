@php
    $appName = config('app.name', 'DuoZen');
    $landingCss = public_path('css/landing.css');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') — {{ $appName }}</title>
        <meta name="description" content="@yield('description')">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ url()->current() }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        @include('layouts.partials.assets')
        <link href="{{ asset('css/landing.css') }}?v={{ file_exists($landingCss) ? filemtime($landingCss) : 1 }}" rel="stylesheet">
    </head>
    <body class="lp">
        <header class="lp-nav">
            <div class="container lp-nav__inner">
                <a href="{{ url('/') }}" class="d-inline-flex align-items-center" aria-label="{{ $appName }}, página inicial">
                    <img src="{{ asset('images/duozen-logo.png') }}" alt="{{ $appName }}" class="lp-nav__logo">
                </a>
                <div class="lp-nav__actions ms-auto">
                    @auth
                        <a class="lp-btn lp-btn--primary lp-btn--sm" href="{{ url('/dashboard') }}">Ir ao painel</a>
                    @else
                        <a class="lp-nav__login" href="{{ route('login') }}">Entrar</a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="lp-legal">
            <div class="container">
                <article class="lp-legal__doc">
                    @yield('content')
                </article>
            </div>
        </main>

        <footer class="lp-footer">
            <div class="container lp-footer__inner">
                <div class="lp-footer__brand">
                    <img src="{{ asset('images/duozen-logo.png') }}" alt="{{ $appName }}">
                </div>
                <div class="lp-footer__links">
                    <a href="{{ url('/') }}">Início</a>
                    <a href="{{ route('legal.terms') }}">Termos de uso</a>
                    <a href="{{ route('legal.privacy') }}">Privacidade</a>
                    <a href="{{ route('contact.show') }}">Contato</a>
                </div>
                <small class="w-100 text-center text-secondary">&copy; {{ date('Y') }} {{ $appName }}</small>
            </div>
        </footer>
        @include('layouts.partials.scripts')
    </body>
</html>
