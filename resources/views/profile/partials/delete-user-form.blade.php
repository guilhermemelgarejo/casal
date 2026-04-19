<section class="card border-0 shadow-sm profile-section-card mb-4">
    <div class="profile-section-head profile-section-head--danger">
        <h2 class="h5 mb-1 fw-semibold text-danger">Zona de risco</h2>
        <p class="small text-secondary mb-0">Excluir a conta remove seus dados de usuário de forma permanente. O casal e os dados compartilhados podem continuar a existir para o outro membro.</p>
    </div>

    <div class="card-body p-4">
        <x-danger-button type="button" class="rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modal-confirm-user-deletion">
            Excluir a minha conta
        </x-danger-button>
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
                        class="mt-1"
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
