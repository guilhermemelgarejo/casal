<div class="dz-card p-3 p-sm-4 h-100 d-flex flex-column">
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <span class="dz-kpi-card__label d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Segurança</span>
            <h2 class="h5 fw-bold mb-1" style="color: var(--dz-text-title);">Alterar senha</h2>
            <p class="small text-secondary mb-0" style="font-size: 0.82rem;">Prefira uma senha longa e única. Será pedida de novo em ações sensíveis.</p>
        </div>
        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--warning flex-shrink-0" style="width: 38px; height: 38px; border-radius: var(--dz-radius-md); font-size: 1.1rem;">
            🔒
        </div>
    </div>

    <form method="post" action="{{ route('password.update') }}" class="d-flex flex-column justify-content-between flex-grow-1">
        @csrf
        @method('put')

        <div class="d-flex flex-column gap-3 mb-3">
            <div>
                <label for="update_password_current_password" class="form-label small fw-semibold mb-1" style="color: var(--dz-text-title);">Senha atual</label>
                <input id="update_password_current_password" name="current_password" type="password" class="form-control rounded-3" autocomplete="current-password" style="background: var(--dz-bg-card); border-color: var(--dz-border);" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1" />
            </div>

            <div>
                <label for="update_password_password" class="form-label small fw-semibold mb-1" style="color: var(--dz-text-title);">Nova senha</label>
                <input id="update_password_password" name="password" type="password" class="form-control rounded-3" autocomplete="new-password" style="background: var(--dz-bg-card); border-color: var(--dz-border);" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1" />
            </div>

            <div>
                <label for="update_password_password_confirmation" class="form-label small fw-semibold mb-1" style="color: var(--dz-text-title);">Confirmar nova senha</label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control rounded-3" autocomplete="new-password" style="background: var(--dz-bg-card); border-color: var(--dz-border);" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1" />
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 pt-1 mt-auto">
            <button type="submit" class="dz-btn dz-btn-primary">
                Atualizar senha
            </button>

            @if (session('status') === 'password-updated')
                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2.5 py-1" style="font-size: 0.75rem;">
                    ✓ Senha atualizada
                </span>
            @endif
        </div>
    </form>
</div>
