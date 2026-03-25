<section class="vstack gap-3">
    <header>
        <h2 class="h5">
            {{ __('Delete Account') }}
        </h2>

        <p class="text-secondary small mb-0">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <div>
        <x-danger-button type="button" data-bs-toggle="modal" data-bs-target="#modal-confirm-user-deletion">
            {{ __('Delete Account') }}
        </x-danger-button>
    </div>

    <x-modal name="confirm-user-deletion" maxWidth="md" :force-show="$errors->userDeletion->isNotEmpty()">
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-confirm-user-deletion-label">
                    {{ __('Are you sure you want to delete your account?') }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <p class="text-secondary small">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </p>

                <div class="mt-3">
                    <x-input-label for="password" value="{{ __('Password') }}" class="visually-hidden" />

                    <x-text-input
                        id="password"
                        name="password"
                        type="password"
                        class="w-75"
                        placeholder="{{ __('Password') }}"
                    />

                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>
            </div>

            <div class="modal-footer">
                <x-secondary-button type="button" data-bs-dismiss="modal">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
