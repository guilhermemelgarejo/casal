<section class="card border-0 shadow-sm profile-section-card mb-4">
    <div class="profile-section-head profile-section-head--account">
        <div class="d-flex align-items-start justify-content-between gap-3">
            <div>
                <span class="profile-section-kicker">Identidade</span>
                <h2 class="h5 mb-1 fw-semibold">Dados do perfil</h2>
                <p class="small text-secondary mb-0">Nome, e-mail e verificação de e-mail quando estiver ativa na aplicação.</p>
            </div>
            <span class="profile-section-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a3 3 0 11-6 0 3 3 0 016 0zM6 21a6 6 0 0112 0" /></svg>
            </span>
        </div>
    </div>

    <div class="card-body p-4">
        <form id="send-verification" method="post" action="{{ route('verification.send') }}">
            @csrf
        </form>

        <form method="post" action="{{ route('profile.update') }}" class="vstack gap-3">
            @csrf
            @method('patch')

            <div class="profile-form-panel">
                <x-input-label for="name" value="Nome" />
                <x-text-input id="name" name="name" type="text" class="mt-1 rounded-3" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div class="profile-form-panel">
                <x-input-label for="email" value="E-mail" />
                <x-text-input id="email" name="email" type="email" class="mt-1 rounded-3" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="profile-inline-notice mt-3">
                        <span class="profile-inline-notice__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12H8m8 0a4 4 0 10-8 0m8 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-4.5 7.794" /></svg>
                        </span>
                        <div class="min-w-0">
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
