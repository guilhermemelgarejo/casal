<section class="card border-0 shadow-sm profile-section-card mb-4">
    <div class="profile-section-head profile-section-head--danger">
        <div class="d-flex align-items-start justify-content-between gap-3">
            <div>
                <span class="profile-section-kicker text-danger">Zona de risco</span>
                <h2 class="h5 mb-1 fw-semibold text-danger">Excluir conta</h2>
                <p class="small text-secondary mb-0">Excluir a conta remove seus dados de usuário de forma permanente. O casal e os dados compartilhados podem continuar a existir para o outro membro.</p>
            </div>
            <span class="profile-section-icon profile-section-icon--danger" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" /></svg>
            </span>
        </div>
    </div>

    <div class="card-body p-4">
        <div class="profile-danger-panel">
            <p class="small text-secondary mb-3">Antes de excluir, confirme se não há pendências no casal ou assinatura.</p>
            <x-danger-button type="button" class="rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modal-confirm-user-deletion">
                Excluir a minha conta
            </x-danger-button>
        </div>
    </div>

    <x-modal name="confirm-user-deletion" maxWidth="md" :force-show="$errors->userDeletion->isNotEmpty()">
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <div class="modal-header tx-modal-head--danger">
                <h2 class="modal-title h5 mb-0" id="modal-confirm-user-deletion-label">
                    Excluir conta?
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <p class="text-secondary small mb-0">
                    Esta ação é <strong class="text-body">irreversível</strong>. Confirme com a sua senha.
                </p>

                <div class="mt-3">
                    <x-input-label for="password" value="Senha" class="visually-hidden" />

                    <x-text-input
                        id="password"
                        name="password"
                        type="password"
                        class="mt-1 rounded-3"
                        placeholder="Senha atual"
                        autocomplete="current-password"
                    />

                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>
            </div>

            <div class="modal-footer">
                <x-secondary-button type="button" data-bs-dismiss="modal" class="rounded-pill px-4">
                    Cancelar
                </x-secondary-button>

                <x-danger-button class="rounded-pill px-4">
                    Excluir definitivamente
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
