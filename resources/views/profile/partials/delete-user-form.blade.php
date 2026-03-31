<section class="vstack gap-3">
    <header>
        <h2 class="h5">
            Excluir conta
        </h2>

        <p class="text-secondary small mb-0">
            Ao excluir a conta, todos os dados associados serão removidos de forma permanente. Antes de prosseguir, guarde o que precisar manter.
        </p>
    </header>

    <div>
        <x-danger-button type="button" data-bs-toggle="modal" data-bs-target="#modal-confirm-user-deletion">
            Excluir conta
        </x-danger-button>
    </div>

    <x-modal name="confirm-user-deletion" maxWidth="md" :force-show="$errors->userDeletion->isNotEmpty()">
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-confirm-user-deletion-label">
                    Tem certeza de que deseja excluir sua conta?
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <p class="text-secondary small">
                    Esta ação é irreversível. Digite sua senha para confirmar a exclusão permanente da conta.
                </p>

                <div class="mt-3">
                    <x-input-label for="password" value="Senha" class="visually-hidden" />

                    <x-text-input
                        id="password"
                        name="password"
                        type="password"
                        class="w-75"
                        placeholder="Senha"
                    />

                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>
            </div>

            <div class="modal-footer">
                <x-secondary-button type="button" data-bs-dismiss="modal">
                    Cancelar
                </x-secondary-button>

                <x-danger-button>
                    Excluir conta
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
