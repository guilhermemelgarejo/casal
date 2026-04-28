<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Entre em contato com o DuoZen.">
        <title>Contato - {{ config('app.name', 'DuoZen') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @include('layouts.partials.assets')
    </head>
    <body class="bg-body-secondary contact-page">
        <div class="min-vh-100 d-flex flex-column">
            <nav class="navbar navbar-expand-lg bg-body border-bottom shadow-sm contact-navbar">
                <div class="container py-0">
                    <a class="navbar-brand mb-0 d-inline-flex align-items-center" href="{{ url('/') }}">
                        <img
                            src="{{ asset('images/duozen-logo.png') }}"
                            alt="{{ config('app.name', 'DuoZen') }}"
                            style="height: 3.5rem; width: auto; max-height: 100%;"
                        />
                    </a>
                    <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                        @auth
                            <a class="btn btn-link btn-sm text-decoration-none text-secondary rounded-pill px-3" href="{{ route('dashboard') }}" title="Abrir o painel financeiro">Painel</a>
                            <a class="btn btn-primary btn-sm rounded-pill px-3" href="{{ route('profile.edit') }}" title="Abrir o perfil">Minha conta</a>
                        @else
                            <a class="btn btn-link btn-sm text-decoration-none text-secondary rounded-pill px-3" href="{{ route('login') }}" title="Entrar na sua conta">Entrar</a>
                            @if (Route::has('register'))
                                <a class="btn btn-primary btn-sm rounded-pill px-3" href="{{ route('register') }}" title="Criar uma nova conta no DuoZen">Criar conta</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </nav>

            <main class="flex-grow-1">
                <section class="contact-hero py-5">
                    <div class="container py-lg-4 position-relative">
                        <div class="row align-items-center g-4 g-lg-5">
                            <div class="col-lg-5">
                                <span class="contact-hero__badge">Contato</span>
                                <h1 class="contact-hero__title display-6 fw-bold mb-3 mt-3">Fale sobre o {{ config('app.name', 'DuoZen') }}</h1>
                                <p class="lead text-secondary mb-4">
                                    Envie dúvidas, sugestões ou relatos de problema. Quanto mais contexto você mandar, mais fácil fica ajudar.
                                </p>
                                <div class="contact-info-grid">
                                    <div class="contact-info-card contact-info-card--primary">
                                        <span class="contact-info-card__label">Canal</span>
                                        <strong class="contact-info-card__value">E-mail</strong>
                                        <span class="contact-info-card__hint">resposta pelo endereço informado</span>
                                    </div>
                                    <div class="contact-info-card">
                                        <span class="contact-info-card__label">Uso ideal</span>
                                        <strong class="contact-info-card__value">Suporte</strong>
                                        <span class="contact-info-card__hint">dúvidas, bugs e sugestões</span>
                                    </div>
                                </div>
                                @auth
                                    <div class="contact-user-note mt-3">
                                        <span class="contact-user-note__icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </span>
                                        <span>Como você está logado, a mensagem também inclui seus dados da conta e casal vinculado.</span>
                                    </div>
                                @endauth
                            </div>

                            <div class="col-lg-7">
                                <div class="card contact-card">
                                    <div class="contact-card__head px-4 px-md-5 py-4">
                                        <span class="contact-section-kicker">Mensagem</span>
                                        <h2 class="h4 fw-semibold mb-1">Como podemos ajudar?</h2>
                                        <p class="small text-secondary mb-0">Preencha os campos abaixo e envie sua solicitação.</p>
                                    </div>
                                    <div class="card-body p-4 p-md-5">
                                        @if (session('success'))
                                            <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-start gap-3" role="alert">
                                                <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                </span>
                                                <span class="pt-1">{{ session('success') }}</span>
                                            </div>
                                        @endif

                                        @if ($errors->any())
                                            <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-start gap-3" role="alert">
                                                <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                                </span>
                                                <span class="pt-1">Confira os campos destacados e tente novamente.</span>
                                            </div>
                                        @endif

                                        <form method="POST" action="{{ route('contact.send') }}" class="vstack gap-3">
                                            @csrf

                                            <div class="contact-form-panel">
                                            <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="name" class="form-label fw-semibold">Nome</label>
                                                <input
                                                    id="name"
                                                    name="name"
                                                    type="text"
                                                    class="form-control rounded-3 @error('name') is-invalid @enderror"
                                                    value="{{ old('name', $user?->name) }}"
                                                    maxlength="120"
                                                    required
                                                >
                                                @error('name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-6">
                                                <label for="email" class="form-label fw-semibold">E-mail</label>
                                                <input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    class="form-control rounded-3 @error('email') is-invalid @enderror"
                                                    value="{{ old('email', $user?->email) }}"
                                                    maxlength="255"
                                                    required
                                                >
                                                @error('email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            </div>
                                            </div>

                                            <div class="contact-form-panel">
                                                <label for="subject" class="form-label fw-semibold">Assunto <span class="text-secondary fw-normal">(opcional)</span></label>
                                                <input
                                                    id="subject"
                                                    name="subject"
                                                    type="text"
                                                    class="form-control rounded-3 @error('subject') is-invalid @enderror"
                                                    value="{{ old('subject') }}"
                                                    maxlength="120"
                                                    placeholder="Ex.: dúvida sobre assinatura"
                                                >
                                                @error('subject')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="contact-form-panel">
                                                <label for="message" class="form-label fw-semibold">Mensagem</label>
                                                <textarea
                                                    id="message"
                                                    name="message"
                                                    class="form-control rounded-3 @error('message') is-invalid @enderror"
                                                    rows="7"
                                                    maxlength="5000"
                                                    placeholder="Conte o que aconteceu, quais passos você tentou ou que melhoria gostaria de sugerir."
                                                    required
                                                >{{ old('message') }}</textarea>
                                                @error('message')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center justify-content-between pt-2">
                                                <p class="small text-secondary mb-0">Normalmente respondo pelo e-mail informado no formulário.</p>
                                                <button type="submit" class="btn btn-primary rounded-pill px-4">Enviar mensagem</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="contact-footer py-4 mt-auto">
                <div class="container d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 text-secondary small">
                    <span>&copy; {{ date('Y') }} {{ config('app.name', 'DuoZen') }}</span>
                    <span class="contact-footer__pill">Finanças a dois, com contexto</span>
                </div>
            </footer>
        </div>
        @include('layouts.partials.scripts')
    </body>
</html>
