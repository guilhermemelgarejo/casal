<div class="dz-card p-3 p-sm-4" style="border-color: rgba(239, 68, 68, 0.25);">
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <span class="dz-kpi-card__label d-block mb-1 text-danger" style="font-size: 0.72rem; letter-spacing: 0.05em;">Zona de Risco</span>
            <h2 class="h5 fw-bold mb-1 text-danger">Excluir conta</h2>
            <p class="small text-secondary mb-0" style="font-size: 0.82rem;">Excluir a conta remove seus dados de usuário de forma permanente. O casal e os dados compartilhados podem continuar a existir para o outro membro.</p>
        </div>
        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--danger flex-shrink-0" style="width: 38px; height: 38px; border-radius: var(--dz-radius-md); font-size: 1.1rem;">
            ⚠️
        </div>
    </div>

    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 p-3 rounded-3" style="background: var(--dz-danger-subtle, rgba(239, 68, 68, 0.08)); border: 1px dashed rgba(239, 68, 68, 0.25);">
        <p class="small text-secondary mb-0" style="font-size: 0.82rem; line-height: 1.4;">
            Antes de excluir, confirme se não há pendências no casal ou assinatura.
        </p>
        <button type="button" class="dz-btn dz-btn-danger flex-shrink-0 text-nowrap" data-bs-toggle="modal" data-bs-target="#modal-confirm-user-deletion">
            Excluir a minha conta
        </button>
    </div>

    <x-modal name="confirm-user-deletion" maxWidth="md" :force-show="$errors->userDeletion->isNotEmpty()">
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <div class="modal-header tx-modal-head--danger">
                <h2 class="modal-title h5 mb-0 fw-bold" id="modal-confirm-user-deletion-label">
                    Excluir conta?
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body p-3 p-sm-4">
                <p class="text-secondary small mb-3">
                    Esta ação é <strong class="text-danger">irreversível</strong>. Todos os seus dados pessoais serão removidos permanentemente. Digite sua senha para confirmar:
                </p>

                <div class="mb-2">
                    <label for="password" class="form-label small fw-semibold mb-1" style="color: var(--dz-text-title);">Sua Senha Atual</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="form-control rounded-3"
                        placeholder="Digite sua senha atual..."
                        autocomplete="current-password"
                        style="background: var(--dz-bg-card); border-color: var(--dz-border);"
                    />
                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1" />
                </div>
            </div>

            <div class="modal-footer d-flex align-items-center justify-content-end gap-2">
                <button type="button" data-bs-dismiss="modal" class="dz-btn dz-btn-outline">
                    Cancelar
                </button>

                <button type="submit" class="dz-btn dz-btn-danger">
                    Excluir definitivamente
                </button>
            </div>
        </form>
    </x-modal>
</div>
