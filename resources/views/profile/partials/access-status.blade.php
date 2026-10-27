<section id="profile-access" class="dz-card profile-access h-100" aria-labelledby="profile-access-title">
    <div class="profile-access__header">
        <div class="profile-access__heading">
            <span class="profile-access__header-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3 4 6v6c0 4 4.5 7.3 8 9 3.5-1.7 8-5 8-9V6l-8-3Z"/><path d="m8.5 12 2.3 2.3 4.7-4.7"/>
                </svg>
            </span>
            <div>
                <h2 id="profile-access-title">Verificação e acesso</h2>
                <p>Acompanhe a confirmação do seu e-mail e a conexão com Google.</p>
            </div>
        </div>
    </div>

    <div class="profile-access__row">
        <span class="profile-access__provider-icon profile-access__provider-icon--email" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>
            </svg>
        </span>
        <div class="profile-access__content">
            <div class="profile-access__label">
                <h3>E-mail da conta</h3>
                <span class="profile-access__badge {{ $user->hasVerifiedEmail() ? 'profile-access__badge--confirmed' : 'profile-access__badge--pending' }}">
                    <span class="profile-access__status-dot" aria-hidden="true"></span>
                    {{ $user->hasVerifiedEmail() ? 'E-mail confirmado' : 'Aguardando confirmação' }}
                </span>
            </div>
            <p class="profile-access__email">{{ $user->email }}</p>
            @if ($user->hasVerifiedEmail())
                <p class="profile-access__hint">Confirmado em {{ $user->email_verified_at->format('d/m/Y') }}. Tudo certo com seu e-mail.</p>
            @else
                <p class="profile-access__hint">Confirme este endereço pelo link enviado para você.</p>
            @endif
            @if (session('status') === 'verification-link-sent' && ! $user->hasVerifiedEmail())
                <p class="profile-access__feedback" role="status">Link enviado. Confira sua caixa de entrada e a pasta de spam.</p>
            @endif
        </div>
        @unless ($user->hasVerifiedEmail())
            <form method="post" action="{{ route('verification.send') }}" class="profile-access__action">
                @csrf
                <button type="submit" class="dz-btn dz-btn-outline">Reenviar confirmação</button>
            </form>
        @endunless
    </div>

    @include('profile.partials.google-account')
</section>
