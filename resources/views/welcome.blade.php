<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @include('layouts.partials.assets')
    </head>
    <body class="bg-body-secondary">
        <div class="min-vh-100 d-flex flex-column">
            @if (Route::has('login'))
                <nav class="navbar navbar-expand-lg bg-white border-bottom">
                    <div class="container">
                        <span class="navbar-brand mb-0 h5">{{ config('app.name', 'Laravel') }}</span>
                        <div class="ms-auto d-flex gap-2">
                            @auth
                                <a class="btn btn-outline-primary btn-sm" href="{{ url('/dashboard') }}">Dashboard</a>
                            @else
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('login') }}">Entrar</a>
                                @if (Route::has('register'))
                                    <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Registrar</a>
                                @endif
                            @endauth
                        </div>
                    </div>
                </nav>
            @endif

            <main class="flex-grow-1 d-flex align-items-center py-5">
                <div class="container text-center">
                    <h1 class="display-6 fw-bold mb-3">Finanças em casal</h1>
                    <p class="text-secondary col-lg-8 mx-auto mb-4">
                        Organize receitas, despesas, orçamentos e metas a dois — com convites, categorias e alertas de gastos.
                    </p>
                    @guest
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Começar</a>
                        @endif
                    @endguest
                </div>
            </main>
        </div>
        @include('layouts.partials.scripts')
    </body>
</html>
