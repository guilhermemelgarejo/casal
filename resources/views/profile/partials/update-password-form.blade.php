<section class="card border-0 shadow-sm profile-section-card mb-4">
    <div class="profile-section-head profile-section-head--password">
        <div class="d-flex align-items-start justify-content-between gap-3">
            <div>
                <span class="profile-section-kicker">Segurança</span>
                <h2 class="h5 mb-1 fw-semibold">Alterar senha</h2>
                <p class="small text-secondary mb-0">Prefira uma senha longa e única. Será pedida de novo em ações sensíveis.</p>
            </div>
            <span class="profile-section-icon profile-section-icon--password" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4" /></svg>
            </span>
        </div>
    </div>

    <div class="card-body p-4">
        <form method="post" action="{{ route('password.update') }}" class="vstack gap-3">
            @csrf
            @method('put')

            <div class="profile-form-panel">
                <x-input-label for="update_password_current_password" value="Senha atual" />
                <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 rounded-3" autocomplete="current-password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div class="profile-form-panel">
                <x-input-label for="update_password_password" value="Nova senha" />
                <x-text-input id="update_password_password" name="password" type="password" class="mt-1 rounded-3" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <div class="profile-form-panel">
                <x-input-label for="update_password_password_confirmation" value="Confirmar nova senha" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 rounded-3" autocomplete="new-password" />
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
