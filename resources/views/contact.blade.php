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
        <style>
            body {
                font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif;
            }
            .contact-hero {
                background: linear-gradient(160deg, #f8fafc 0%, #e7f1ff 45%, #f1f5f9 100%);
            }
            .contact-card {
                border: 0;
                border-radius: 1.25rem;
                box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, 0.08);
            }
        </style>
    </head>
    <body class="bg-body-secondary">
        <div class="min-vh-100 d-flex flex-column">
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
                        @auth
                            <a class="btn btn-link btn-sm text-decoration-none text-secondary px-2" href="{{ route('dashboard') }}" title="Abrir o painel financeiro">Painel</a>
                            <a class="btn btn-primary btn-sm px-3" href="{{ route('profile.edit') }}" title="Abrir o perfil">Minha conta</a>
                        @else
                            <a class="btn btn-link btn-sm text-decoration-none text-secondary px-2" href="{{ route('login') }}" title="Entrar na sua conta">Entrar</a>
                            @if (Route::has('register'))
                                <a class="btn btn-primary btn-sm px-3" href="{{ route('register') }}" title="Criar uma nova conta no DuoZen">Criar conta</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </nav>

            <main class="flex-grow-1">
                <section class="contact-hero py-5">
                    <div class="container py-lg-4">
                        <div class="row align-items-center g-4 g-lg-5">
                            <div class="col-lg-5">
                                <p class="text-primary fw-semibold small text-uppercase mb-2">Contato</p>
                                <h1 class="display-6 fw-bold text-dark mb-3">Entre em contato sobre o {{ config('app.name', 'DuoZen') }}</h1>
                                <p class="lead text-secondary mb-4">
                                    Envie dúvidas, sugestões ou relatos de problema.
                                </p>
                            </div>

                            <div class="col-lg-7">
                                <div class="card contact-card">
                                    <div class="card-body p-4 p-md-5">
                                        @if (session('success'))
                                            <div class="alert alert-success" role="alert">
                                                {{ session('success') }}
                                            </div>
                                        @endif

                                        @if ($errors->any())
                                            <div class="alert alert-danger" role="alert">
                                                Confira os campos destacados e tente novamente.
                                            </div>
                                        @endif

                                        <form method="POST" action="{{ route('contact.send') }}" class="vstack gap-3">
                                            @csrf

                                            <div>
                                                <label for="name" class="form-label fw-semibold">Nome</label>
                                                <input
                                                    id="name"
                                                    name="name"
                                                    type="text"
                                                    class="form-control @error('name') is-invalid @enderror"
                                                    value="{{ old('name', $user?->name) }}"
                                                    maxlength="120"
                                                    required
                                                >
                                                @error('name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div>
                                                <label for="email" class="form-label fw-semibold">E-mail</label>
                                                <input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    class="form-control @error('email') is-invalid @enderror"
                                                    value="{{ old('email', $user?->email) }}"
                                                    maxlength="255"
                                                    required
                                                >
                                                @error('email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div>
                                                <label for="subject" class="form-label fw-semibold">Assunto <span class="text-secondary fw-normal">(opcional)</span></label>
                                                <input
                                                    id="subject"
                                                    name="subject"
                                                    type="text"
                                                    class="form-control @error('subject') is-invalid @enderror"
                                                    value="{{ old('subject') }}"
                                                    maxlength="120"
                                                >
                                                @error('subject')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div>
                                                <label for="message" class="form-label fw-semibold">Mensagem</label>
                                                <textarea
                                                    id="message"
                                                    name="message"
                                                    class="form-control @error('message') is-invalid @enderror"
                                                    rows="7"
                                                    maxlength="5000"
                                                    required
                                                >{{ old('message') }}</textarea>
                                                @error('message')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center justify-content-between pt-2">
                                                <p class="small text-secondary mb-0">Normalmente respondo pelo e-mail informado no formulário.</p>
                                                <button type="submit" class="btn btn-primary px-4">Enviar mensagem</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="py-4 bg-white border-top mt-auto">
                <div class="container text-center text-secondary small">
                    &copy; {{ date('Y') }} {{ config('app.name', 'DuoZen') }}
                </div>
            </footer>
        </div>
        @include('layouts.partials.scripts')
    </body>
</html>
