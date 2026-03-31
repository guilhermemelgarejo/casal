<section>
    <header>
        <h2 class="h5">
            Alterar senha
        </h2>

        <p class="text-secondary small">
            Use uma senha longa e aleatória para manter a conta segura.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-4 vstack gap-3">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Senha atual" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Nova senha" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Confirmar senha" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            <x-primary-button>Salvar</x-primary-button>

            @if (session('status') === 'password-updated')
                <p class="text-secondary small mb-0">
                    Salvo.
                </p>
            @endif
        </div>
    </form>
</section>
