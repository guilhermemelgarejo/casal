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
        <div>
            <h1 class="dz-page-title">Meu Perfil</h1>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Dados cadastrais, alteração de senha e preferências da conta
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        @if ($profileCouple)
            <a href="{{ route('couple.index') }}" class="dz-btn dz-btn-outline">
                Ver Casal ↗
            </a>
        @endif
    </x-slot>

    <div class="container-xxl py-3 py-sm-4 px-2 px-sm-3 px-lg-4 profile-page">
        @if (session('success'))
            <x-alert type="success" class="mb-3 mb-sm-4" :message="session('success')" />
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-3 mb-sm-4" :message="session('error')" />
        @endif

        <!-- TOP KPIS DUOZEN 2.0 -->
        <section class="dz-kpi-grid mb-3 mb-sm-4">
            <!-- Usuário -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Meu Nome</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                        👤
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-truncate" style="font-size: 1.25rem; color: var(--dz-text-title);" title="{{ $profileUser?->name }}">
                        {{ $profileUser?->name }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Perfil ativo</span>
                    </div>
                </div>
            </div>

            <!-- E-mail -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">E-mail Cadastrado</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                        ✉️
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-truncate" style="font-size: 1rem; color: var(--dz-text-title);" title="{{ $profileUser?->email }}">
                        {{ $profileUser?->email }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Login principal</span>
                    </div>
                </div>
            </div>

            <!-- Espaço do Casal -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Espaço Casal</span>
                    <div class="dz-kpi-card__icon-box" style="background: rgba(14, 165, 233, 0.15); color: #0284C7;">
                        👫
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-truncate" style="font-size: 1.2rem; color: var(--dz-text-title);" title="{{ $profileCouple?->name ?? 'Sem casal' }}">
                        {{ $profileCouple?->name ?? 'Sem casal' }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <a href="{{ route('couple.index') }}" style="color: var(--dz-primary); font-weight: 700; text-decoration: none;">Ver configurações ↗</a>
                    </div>
                </div>
            </div>

            <!-- Segurança -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Segurança</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--warning">
                        🔒
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-success" style="font-size: 1.25rem;">
                        Protegida
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Autenticação ativa</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- CARDS PRINCIPAIS: DADOS DO PERFIL & ALTERAR SENHA (MESMA ALTURA) --}}
        <div class="row g-3 g-sm-4 align-items-stretch mb-3 mb-sm-4">
            <div class="col-12 col-lg-6 d-flex flex-column">
                @include('profile.partials.update-profile-information-form')
            </div>
            <div class="col-12 col-lg-6 d-flex flex-column">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        {{-- ZONA DE RISCO: EXCLUIR CONTA --}}
        <div class="row g-3 g-sm-4">
            <div class="col-12">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
