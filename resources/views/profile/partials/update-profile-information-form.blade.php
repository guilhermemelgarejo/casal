<div class="dz-card p-3 p-sm-4 h-100 d-flex flex-column">
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <span class="dz-kpi-card__label d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Identidade</span>
            <h2 class="h5 fw-bold mb-1" style="color: var(--dz-text-title);">Dados do perfil</h2>
            <p class="small text-secondary mb-0" style="font-size: 0.82rem;">Mantenha seu nome e e-mail atualizados.</p>
        </div>
        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary flex-shrink-0" style="width: 38px; height: 38px; border-radius: var(--dz-radius-md); font-size: 1.1rem;">
            👤
        </div>
    </div>

    @if (! $user->hasLocalPassword())
        @include('profile.partials.google-confirmation', ['purpose' => 'email', 'label' => 'Confirmar Google para alterar e-mail'])
    @endif

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
