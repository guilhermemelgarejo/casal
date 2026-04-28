@php
    $profileUser = auth()->user();
    $profileCouple = $profileUser?->couple;
    $initials = collect(explode(' ', trim((string) ($profileUser?->name ?? ''))))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('') ?: 'DZ';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <p class="small text-secondary mb-1">Conta pessoal</p>
                <h2 class="h5 mb-0 profile-page-title">Perfil</h2>
                <p class="small text-secondary mb-0 mt-1">Dados da conta, senha e opção de excluir o usuário.</p>
            </div>
            @if ($profileCouple)
                <a href="{{ route('couple.index') }}" class="btn btn-outline-primary rounded-pill px-4 align-self-start align-self-md-center">
                    Ver casal
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-4 profile-page">
        <div class="container-xxl px-3 px-lg-4 d-grid gap-4">
            <section class="profile-hero card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5">
                            <div class="d-flex align-items-center gap-3">
                                <span class="profile-hero__avatar" aria-hidden="true">{{ $initials }}</span>
                                <div class="min-w-0">
                                    <span class="profile-hero__badge">Perfil</span>
                                    <h3 class="profile-hero__title h4 mt-2 mb-1 text-truncate" title="{{ $profileUser?->name }}">{{ $profileUser?->name }}</h3>
                                    <p class="text-secondary mb-0 text-truncate">{{ $profileUser?->email }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="profile-summary-grid">
                                <div class="profile-summary-card profile-summary-card--primary">
                                    <span class="profile-summary-card__label">Conta</span>
                                    <strong class="profile-summary-card__value">Ativa</strong>
                                    <span class="profile-summary-card__hint">acesso autenticado</span>
                                </div>
                                <div class="profile-summary-card">
                                    <span class="profile-summary-card__label">Casal</span>
                                    <strong class="profile-summary-card__value">{{ $profileCouple ? 'Vinculado' : 'Sem casal' }}</strong>
                                    <span class="profile-summary-card__hint">{{ $profileCouple?->name ?? 'crie ou entre em um espaço' }}</span>
                                </div>
                                <div class="profile-summary-card profile-summary-card--warning">
                                    <span class="profile-summary-card__label">Segurança</span>
                                    <strong class="profile-summary-card__value">Senha</strong>
                                    <span class="profile-summary-card__hint">mantenha uma senha única</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-4 align-items-start">
                <div class="col-xl-7">
                    @include('profile.partials.update-profile-information-form')
                </div>
                <div class="col-xl-5">
                    @include('profile.partials.update-password-form')
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
