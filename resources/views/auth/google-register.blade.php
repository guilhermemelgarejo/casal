<x-guest-layout>
    <h1 class="h5 fw-semibold text-center text-dark mb-3">Concluir cadastro com Google</h1>
    <p class="small text-secondary">Você usará <strong>{{ $identity['email'] }}</strong> para entrar. Não é necessário criar uma senha.</p>
    <x-input-error :messages="$errors->get('google')" class="mb-3" />
    <form method="POST" action="{{ route('google.register.store') }}">
        @csrf
        <div class="mb-3">
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" name="name" :value="old('name', $identity['name'])" required autocomplete="name" maxlength="255" autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        @if ($identity['invite_code'])
            <p class="small text-secondary">Seu convite para entrar no casal será conferido ao concluir o cadastro.</p>
        @endif
        <x-input-error :messages="$errors->get('invite_code')" class="mb-3" />
        @include('partials.subscription-public-info', ['compact' => true])
        <div class="form-check my-3">
            <input class="form-check-input" type="checkbox" name="terms" id="google-terms" value="1" required @checked(old('terms'))>
            <label class="form-check-label small text-secondary" for="google-terms">
                Declaro ter 18 anos ou mais, aceito os <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Termos e Condições de Uso</a>
                e estou ciente da <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Política de Privacidade</a>.
            </label>
            <x-input-error :messages="$errors->get('terms')" class="mt-2" />
        </div>
        <div class="d-flex justify-content-between align-items-center gap-3">
            <a href="{{ route('login') }}" class="small">Voltar ao login</a>
            <x-primary-button>Concluir cadastro</x-primary-button>
        </div>
    </form>
</x-guest-layout>
