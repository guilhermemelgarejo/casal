<x-guest-layout>
    <h1 class="h5 fw-semibold text-center text-dark mb-3">Criar conta</h1>
    <x-input-error :messages="$errors->get('google')" class="mb-3" />
    @include('partials.google-login-button')

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" class="mt-1" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mb-3">
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        @if(request('invite_code') || old('invite_code'))
            <input type="hidden" name="invite_code" value="{{ request('invite_code', old('invite_code')) }}">
        @endif

        <div class="mb-3">
            <x-input-label for="password" value="Senha" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mb-3">
            <x-input-label for="password_confirmation" value="Confirmar senha" />
            <x-text-input id="password_confirmation" class="mt-1" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-3">
            @include('partials.subscription-public-info', ['compact' => true])
        </div>

        <p class="small text-secondary mt-3 mb-0">
            Ao clicar em Cadastrar, você declara ter 18 anos ou mais e concorda com os
            <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Termos e Condições de Uso</a>
            e declara ciência da <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Política de Privacidade</a>.
        </p>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
            <a class="small text-decoration-none" href="{{ route('login') }}">
                Já tem conta?
            </a>

            <x-primary-button>Cadastrar</x-primary-button>
        </div>
    </form>
</x-guest-layout>
