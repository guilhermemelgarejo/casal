<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Planeje o dinheiro do casal: receitas, despesas, orçamentos e alertas. Período de teste com cartão e plano mensal via Stripe.">
        <title>{{ config('app.name', 'DuoZen') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @include('layouts.partials.assets')
        <style>
            body {
                font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif;
            }
            .landing-hero {
                background: linear-gradient(160deg, #f8fafc 0%, #e7f1ff 45%, #f1f5f9 100%);
            }
            .landing-cta-band {
                background: linear-gradient(135deg, rgb(var(--bs-primary-rgb)) 0%, #0a58ca 100%);
            }
            .landing-cta-band .cta-subtle {
                color: rgba(255, 255, 255, 0.88);
            }
            .feature-icon {
                width: 3rem;
                height: 3rem;
                border-radius: 0.75rem;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.35rem;
                background: var(--bs-primary-bg-subtle);
                color: var(--bs-primary);
            }
        </style>
    </head>
    <body class="bg-body-secondary">
        <div class="min-vh-100 d-flex flex-column">
            @if (Route::has('login'))
                <nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm">
                    <div class="container py-0">
                        <a class="navbar-brand mb-0 d-inline-flex align-items-center" href="{{ url('/') }}">
                            <img
                                src="{{ asset('images/duozen-logo.png') }}"
                                alt="{{ config('app.name', 'DuoZen') }}"
                                style="height: 3.5rem; width: auto; max-height: 100%;"
                            />
                        </a>
                        <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                            <a class="btn btn-link btn-sm text-decoration-none text-secondary px-2" href="{{ route('contact.show') }}" title="Entrar em contato">Contato</a>
                            @auth
                                <a class="btn btn-primary btn-sm" href="{{ url('/dashboard') }}" title="Abrir o painel financeiro">Ir ao painel</a>
                            @else
                                <a class="btn btn-link btn-sm text-decoration-none text-secondary px-2" href="{{ route('login') }}" title="Entrar na sua conta">Entrar</a>
                                @if (Route::has('register'))
                                    <a class="btn btn-primary btn-sm px-3" href="{{ route('register') }}" title="Criar uma nova conta no DuoZen">Criar conta</a>
                                @endif
                            @endauth
                        </div>
                    </div>
                </nav>
            @endif

            <main class="flex-grow-1">
                <section class="landing-hero py-5">
                    <div class="container py-lg-4">
                        <div class="row align-items-center g-5">
                            <div class="col-lg-7 text-center text-lg-start">
                                <p class="text-primary fw-semibold small text-uppercase letter-spacing mb-2">Planejamento financeiro para casais</p>
                                <h1 class="display-5 fw-bold text-dark mb-3 lh-sm">
                                    O dinheiro de vocês, organizado <span class="text-primary">juntos</span> — sem planilhas espalhadas.
                                </h1>
                                <p class="lead text-secondary mb-4 pe-lg-4">
                                    Centralize receitas, despesas, orçamentos e metas a dois. Convide seu parceiro ou parceira, use categorias que fazem sentido para a casa e receba alertas antes dos gastos saírem do controle.
                                </p>
                                @guest
                                    @if (Route::has('register'))
                                        <div class="d-flex flex-column flex-sm-row gap-2 gap-sm-3 justify-content-center justify-content-lg-start mb-3">
                                            <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-4 shadow-sm" title="Cadastrar-se e começar o período de teste">Criar conta grátis</a>
                                            @if (Route::has('login'))
                                                <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-lg px-4" title="Entrar com e-mail e senha">Já tenho conta</a>
                                            @endif
                                        </div>
                                        <p class="small text-secondary mb-0">Leva poucos minutos. Depois criem ou entrem no casal, ativem o plano (teste grátis com cartão) e comecem a lançar.</p>
                                    @elseif (Route::has('login'))
                                        <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4 shadow-sm" title="Entrar na sua conta">Entrar</a>
                                    @endif
                                @else
                                    <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg px-4 shadow-sm" title="Abrir o painel financeiro">Abrir painel</a>
                                @endguest
                            </div>
                            <div class="col-lg-5">
                                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                                    <div class="card-body p-4 p-md-5 bg-white">
                                        <h2 class="h5 fw-bold mb-4">Por que casais usam o {{ config('app.name', 'DuoZen') }}?</h2>
                                        <ul class="list-unstyled mb-0">
                                            <li class="d-flex gap-3 mb-3">
                                                <span class="feature-icon flex-shrink-0">✓</span>
                                                <div>
                                                    <strong class="d-block text-dark">Uma visão só para os dois</strong>
                                                    <span class="text-secondary small">Mesmo painel, mesmas categorias e histórico — transparência sem microgerenciar.</span>
                                                </div>
                                            </li>
                                            <li class="d-flex gap-3 mb-3">
                                                <span class="feature-icon flex-shrink-0">◎</span>
                                                <div>
                                                    <strong class="d-block text-dark">Orçamento e metas alinhados</strong>
                                                    <span class="text-secondary small">Definam limites por categoria e acompanhem metas com o que sobra no mês.</span>
                                                </div>
                                            </li>
                                            <li class="d-flex gap-3">
                                                <span class="feature-icon flex-shrink-0">!</span>
                                                <div>
                                                    <strong class="d-block text-dark">Alertas que evitam surpresas</strong>
                                                    <span class="text-secondary small">Avisos de gastos ajudam a corrigir rota antes do fim do mês.</span>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="py-5 bg-white border-top border-bottom">
                    <div class="container">
                        <div class="text-center col-lg-8 mx-auto mb-5">
                            <h2 class="h3 fw-bold mb-2">Tudo o que vocês precisam no dia a dia</h2>
                            <p class="text-secondary mb-0">Do convite ao controle: fluxo pensado para quem divide conta e responsabilidades.</p>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6 col-lg-3">
                                <div class="card h-100 border-0 shadow-sm rounded-3">
                                    <div class="card-body p-4">
                                        <div class="feature-icon mb-3">👥</div>
                                        <h3 class="h6 fw-bold">Convites e casal</h3>
                                        <p class="text-secondary small mb-0">Tragam o outro com segurança e trabalhem no mesmo espaço.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="card h-100 border-0 shadow-sm rounded-3">
                                    <div class="card-body p-4">
                                        <div class="feature-icon mb-3">📁</div>
                                        <h3 class="h6 fw-bold">Categorias claras</h3>
                                        <p class="text-secondary small mb-0">Organizem por tipo de despesa e vejam para onde o dinheiro vai.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="card h-100 border-0 shadow-sm rounded-3">
                                    <div class="card-body p-4">
                                        <div class="feature-icon mb-3">📊</div>
                                        <h3 class="h6 fw-bold">Orçamentos e metas</h3>
                                        <p class="text-secondary small mb-0">Planejem o mês e acompanhem objetivos sem perder o foco.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="card h-100 border-0 shadow-sm rounded-3">
                                    <div class="card-body p-4">
                                        <div class="feature-icon mb-3">🔔</div>
                                        <h3 class="h6 fw-bold">Alertas de gastos</h3>
                                        <p class="text-secondary small mb-0">Saibam quando estiver perto do limite — juntos.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                @include('partials.subscription-public-info', ['compact' => false])

                <section class="py-5">
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-10 col-xl-8">
                                <blockquote class="text-center border-0 mb-0 px-md-5">
                                    <p class="fs-5 text-dark fst-italic mb-3">“Quando o dinheiro fica visível para os dois, a conversa muda de culpa para planejamento.”</p>
                                    <footer class="text-secondary small">É para isso que o {{ config('app.name', 'DuoZen') }} existe.</footer>
                                </blockquote>
                            </div>
                        </div>
                    </div>
                </section>

                @guest
                    @if (Route::has('register'))
                        <section class="landing-cta-band text-white py-5">
                            <div class="container text-center py-md-2">
                                <h2 class="h3 fw-bold mb-2">Prontos para alinhar as finanças?</h2>
                                <p class="cta-subtle mb-4 col-md-8 mx-auto">Crie sua conta, montem o casal, ativem o plano com período de teste e comecem a registrar em minutos — com a mesma clareza que vocês merecem.</p>
                                <a href="{{ route('register') }}" class="btn btn-light btn-lg px-5 fw-semibold shadow" title="Cadastrar-se e começar o período de teste">Criar conta grátis</a>
                                @if (Route::has('login'))
                                    <p class="small cta-subtle mt-3 mb-0">
                                        Já usa o sistema? <a href="{{ route('login') }}" class="text-white fw-semibold">Entrar</a>
                                    </p>
                                @endif
                            </div>
                        </section>
                    @endif
                @endguest

                <footer class="py-4 bg-white border-top mt-auto">
                    <div class="container text-center text-secondary small">
                        &copy; {{ date('Y') }} {{ config('app.name', 'DuoZen') }}
                        <span class="mx-2">·</span>
                        <a href="{{ route('contact.show') }}" class="text-secondary text-decoration-none">Contato</a>
                    </div>
                </footer>
            </main>
        </div>
        @include('layouts.partials.scripts')
    </body>
</html>
