<section class="card border-0 shadow-sm profile-section-card mb-4">
    <div class="profile-section-head profile-section-head--account">
        <h2 class="h5 mb-1 fw-semibold">Dados do perfil</h2>
        <p class="small text-secondary mb-0">Nome, e-mail e verificação de e-mail quando estiver ativa na aplicação.</p>
    </div>

    <div class="card-body p-4">
        <form id="send-verification" method="post" action="{{ route('verification.send') }}">
            @csrf
        </form>

        <form method="post" action="{{ route('profile.update') }}" class="vstack gap-3">
            @csrf
            @method('patch')

            <div>
                <x-input-label for="name" value="Nome" />
                <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" value="E-mail" />
                <x-text-input id="email" name="email" type="email" class="mt-1" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-3 p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <p class="small mb-2 text-secondary">
                            O seu e-mail ainda não foi verificado.
                        </p>
                        <button form="send-verification" type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">
                            Reenviar e-mail de verificação
                        </button>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 mb-0 small text-success fw-medium">
                                Foi enviado um novo link para o seu e-mail.
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap pt-1">
                <x-primary-button class="rounded-pill px-4">Salvar</x-primary-button>

                @if (session('status') === 'profile-updated')
                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-2">
                        Alterações salvas
                    </span>
                @endif
            </div>
        </form>
    </div>
</section>
