<section class="card border-0 shadow-sm profile-section-card mb-4">
    <div class="profile-section-head profile-section-head--password">
        <h2 class="h5 mb-1 fw-semibold">Alterar senha</h2>
        <p class="small text-secondary mb-0">Prefira uma senha longa e única. Será pedida de novo em ações sensíveis.</p>
    </div>

    <div class="card-body p-4">
        <form method="post" action="{{ route('password.update') }}" class="vstack gap-3">
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
                <x-input-label for="update_password_password_confirmation" value="Confirmar nova senha" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap pt-1">
                <x-primary-button class="rounded-pill px-4">Atualizar senha</x-primary-button>

                @if (session('status') === 'password-updated')
                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-2">
                        Senha atualizada
                    </span>
                @endif
            </div>
        </form>
    </div>
</section>
