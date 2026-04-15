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
        @if (!empty($showOnboardingTour))
            @php
                $onboardingTourJs = public_path('js/onboarding-tour.js');
                $onboardingTourConfig = [
                    'dismissUrl' => route('onboarding.dismiss'),
                    'csrf' => csrf_token(),
                    'route' => optional(request()->route())->getName(),
                    'steps' => [
                        [
                            'route' => 'dashboard',
                            'selector' => '#onboarding-anchor-welcome',
                            'title' => 'Bem-vindos ao DuoZen',
                            'body' => 'Este é o painel. Nos próximos passos vamos indicar onde cadastrar a primeira conta, criar uma categoria à vossa medida e registar o primeiro lançamento.',
                            'prevUrl' => null,
                            'nextUrl' => route('accounts.index'),
                        ],
                        [
                            'route' => 'accounts.index',
                            'selector' => '#btn-new-account',
                            'title' => 'Primeira conta ou cartão',
                            'body' => 'Cadastrem aqui uma conta corrente (Pix, débito, etc.) ou um cartão de crédito. É preciso pelo menos uma conta para lançar movimentos em caixa.',
                            'prevUrl' => route('dashboard'),
                            'nextUrl' => route('categories.index'),
                        ],
                        [
                            'route' => 'categories.index',
                            'selector' => '#btn-new-category',
                            'title' => 'Categorias',
                            'body' => 'Já criámos categorias iniciais (Alimentação, Moradia, …). Podem acrescentar as vossas em Nova categoria ou usar as existentes nos lançamentos.',
                            'prevUrl' => route('accounts.index'),
                            'nextUrl' => route('dashboard'),
                        ],
                        [
                            'route' => 'dashboard',
                            'selector' => '#onboarding-tx-actions',
                            'title' => 'Primeiro lançamento',
                            'body' => 'Use + Receita ou + Despesa para registar valores. Escolham conta, categorias e valores — o painel e o orçamento atualizam a partir daqui.',
                            'prevUrl' => route('categories.index'),
                            'nextUrl' => null,
                        ],
                    ],
                ];
            @endphp
            <script>
                window.__DUOZEN_ONBOARDING__ = @json($onboardingTourConfig);
            </script>
            <script src="{{ asset('js/onboarding-tour.js') }}?v={{ file_exists($onboardingTourJs) ? filemtime($onboardingTourJs) : 1 }}" defer></script>
        @endif
        @include('layouts.partials.scripts')
        {{-- Depois do Bootstrap: scripts empilhados usam bootstrap.Modal ou eventos show.bs.modal --}}
        @stack('scripts')
    </body>
</html>
