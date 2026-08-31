<div class="dz-card p-3 p-sm-4 h-100 d-flex flex-column">
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <span class="dz-kpi-card__label d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Identidade</span>
            <h2 class="h5 fw-bold mb-1" style="color: var(--dz-text-title);">Dados do perfil</h2>
            <p class="small text-secondary mb-0" style="font-size: 0.82rem;">Nome, e-mail e verificação da conta na aplicação.</p>
        </div>
        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary flex-shrink-0" style="width: 38px; height: 38px; border-radius: var(--dz-radius-md); font-size: 1.1rem;">
            👤
        </div>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="d-flex flex-column justify-content-between flex-grow-1">
        @csrf
        @method('patch')

        <div class="d-flex flex-column gap-3 mb-3">
            <div>
                <label for="name" class="form-label small fw-semibold mb-1" style="color: var(--dz-text-title);">Nome</label>
                <input id="name" name="name" type="text" class="form-control rounded-3" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" style="background: var(--dz-bg-card); border-color: var(--dz-border);" />
                <x-input-error class="mt-1" :messages="$errors->get('name')" />
            </div>

            <div>
                <label for="email" class="form-label small fw-semibold mb-1" style="color: var(--dz-text-title);">E-mail</label>
                <input id="email" name="email" type="email" class="form-control rounded-3" value="{{ old('email', $user->email) }}" required autocomplete="username" style="background: var(--dz-bg-card); border-color: var(--dz-border);" />
                <x-input-error class="mt-1" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-2.5 p-2.5 rounded-3 d-flex align-items-start gap-2" style="background: var(--dz-warning-subtle); border: 1px solid rgba(245, 158, 11, 0.3);">
                        <span class="flex-shrink-0" style="font-size: 1rem;">⚠️</span>
                        <div class="min-w-0">
                            <p class="small mb-1 text-secondary" style="font-size: 0.8rem;">
                                O seu e-mail ainda não foi verificado.
                            </p>
                            <button form="send-verification" type="submit" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 0.8rem; color: var(--dz-primary); font-weight: 600;">
                                Reenviar e-mail de verificação
                            </button>

                            @if (session('status') === 'verification-link-sent')
                                <p class="mt-1 mb-0 small text-success fw-medium" style="font-size: 0.78rem;">
                                    Foi enviado um novo link para o seu e-mail.
                                </p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 pt-1 mt-auto">
            <button type="submit" class="dz-btn dz-btn-primary">
                Salvar
            </button>

            @if (session('status') === 'profile-updated')
                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2.5 py-1" style="font-size: 0.75rem;">
                    ✓ Alterações salvas
                </span>
            @endif
        </div>
    </form>
</div>
