<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'DuoZen') }}</title>

    <!-- Google Fonts / Figtree -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">

    <!-- CSS Base & Assets -->
    @include('layouts.partials.assets')

    <!-- CSS Design System DuoZen 2.0 -->
    <link rel="stylesheet" href="{{ asset('css/concept.css') }}?v={{ file_exists(public_path('css/concept.css')) ? filemtime(public_path('css/concept.css')) : 1 }}">

    <!-- Script de Inicialização Rápida (Tema Escuro & Privacidade para evitar FOUC) -->
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('duozen_concept_theme') || localStorage.getItem('duozen_theme') || 'light';
                document.documentElement.setAttribute('data-theme', savedTheme);

                if (localStorage.getItem('duozen_privacy_mode') === 'true') {
                    document.documentElement.classList.add('duozen-privacy-active', 'dz-privacy-active');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="dz-concept-body">
    <!-- BARRA GLOBAL DE CONTROLE SUPERIOR (TEMA, PRIVACIDADE E MENU MOBILE) -->
    <header class="dz-demo-bar">
        <div class="dz-demo-bar__inner">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="dz-btn-icon js-dz-open-drawer d-lg-none" title="Abrir menu de navegação" aria-label="Abrir menu" style="width: 34px; height: 34px; border-radius: var(--dz-radius-md);">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="{{ route('dashboard') }}" class="d-inline-flex align-items-center gap-2 text-decoration-none" title="{{ config('app.name', 'DuoZen') }}">
                    <img src="{{ asset('images/duozen-logo.png') }}" alt="{{ config('app.name', 'DuoZen') }}" style="height: 48px; max-height: 48px; width: auto; max-width: 195px; object-fit: contain; display: block; transform: scale(1.18); transform-origin: left center;">
                </a>
            </div>

            <div class="d-flex align-items-center gap-2 flex-nowrap ms-auto">
                <!-- Alternador de Tema -->
                <div class="dz-control-group" title="Alternar entre modo Claro e Escuro">
                    <button type="button" class="dz-btn-pill" data-dz-theme="light">
                        ☀️ <span class="d-none d-md-inline">Claro</span>
                    </button>
                    <button type="button" class="dz-btn-pill" data-dz-theme="dark">
                        🌙 <span class="d-none d-md-inline">Escuro</span>
                    </button>
                </div>

                <!-- Modo Privacidade -->
                <div class="dz-control-group" title="Ocultar ou exibir valores">
                    <button type="button" class="dz-btn-pill" data-dz-privacy="false">
                        👁️ <span class="d-none d-md-inline">Aberto</span>
                    </button>
                    <button type="button" class="dz-btn-pill" data-dz-privacy="true">
                        🔒 <span class="d-none d-md-inline">Oculto</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div class="dz-app-layout">
        
        <!-- SIDEBAR LATERAL FIXA DESKTOP -->
        <aside class="dz-sidebar" aria-label="Menu principal de navegação">
            <!-- Status do Casal Conectado -->
            @if(isset($couple) && $couple)
                <div class="dz-couple-status mb-3">
                    <div class="dz-avatar-duo">
                        <div class="dz-avatar dz-avatar--user1" title="{{ $user1Name }}">{{ substr($user1Name, 0, 1) }}</div>
                        @if ($user2)
                            <div class="dz-avatar dz-avatar--user2" title="{{ $user2Name }}">{{ substr($user2Name, 0, 1) }}</div>
                        @endif
                    </div>
                    <div class="dz-couple-status__info">
                        <div class="dz-couple-status__name">{{ $couple->name ?? ($user1Short . ($user2 ? ' & ' . $user2Short : '')) }}</div>
                        <div class="dz-couple-status__label">
                            <span class="dz-couple-status__sync-dot"></span> Sincronizado
                        </div>
                    </div>
                </div>
            @endif

            <!-- Menu de Navegação Desktop -->
            <div class="dz-sidebar-nav-wrap" style="flex: 1; overflow-y: auto;">
                <ul class="dz-nav-menu">
                    <li class="dz-nav-item">
                        <a href="{{ route('dashboard') }}" class="dz-nav-link {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            </span>
                            <span>Painel</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('transactions.index') }}" class="dz-nav-link {{ request()->routeIs('transactions*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            </span>
                            <span>Lançamentos</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('reports.index') }}" class="dz-nav-link {{ request()->routeIs('reports*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </span>
                            <span>Relatórios</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('accounts.index') }}" class="dz-nav-link {{ request()->routeIs('accounts*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                            </span>
                            <span>Contas & Cartões</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('credit-card-statements.index') }}" class="dz-nav-link {{ request()->routeIs('credit-card-statements*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </span>
                            <span>Faturas de Cartão</span>
                            @if(count($sidebarInvoiceReminders ?? []) > 0)
                                <span class="dz-nav-link__badge">{{ count($sidebarInvoiceReminders) }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('cofrinhos.index') }}" class="dz-nav-link {{ request()->routeIs('cofrinhos*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <span>Cofrinhos & Metas</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('recurring-transactions.index') }}" class="dz-nav-link {{ request()->routeIs('recurring-transactions*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </span>
                            <span>Recorrentes</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('categories.index') }}" class="dz-nav-link {{ request()->routeIs('categories*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            </span>
                            <span>Categorias</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('couple.index') }}" class="dz-nav-link {{ request()->routeIs('couple*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </span>
                            <span>Casal</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('contact.show') }}" class="dz-nav-link {{ request()->routeIs('contact*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </span>
                            <span>Contato</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Footer Sidebar -->
            <div class="dz-sidebar-footer">
                @if(Auth::check())
                    <div class="dz-plan-card">
                        <div class="dz-plan-card__title text-truncate" title="{{ Auth::user()->name }}">
                            {{ Auth::user()->name }}
                        </div>
                        <div style="color: var(--dz-text-secondary); font-size: 0.7rem; margin-top: 0.1rem;" class="text-truncate" title="{{ Auth::user()->email }}">
                            {{ Auth::user()->email }}
                        </div>
                        <div class="d-flex align-items-center gap-1 mt-2 pt-1" style="border-top: 1px solid var(--dz-border-subtle);">
                            @if(Auth::user()->couple_id && Auth::user()->passesCoupleBillingGate())
                                <form action="{{ route('onboarding.restart') }}" method="POST" class="d-inline m-0 p-0">
                                    @csrf
                                    <button type="submit" title="Iniciar tour guiado do DuoZen" style="background: none; border: none; padding: 0; color: var(--dz-primary); font-size: 0.72rem; cursor: pointer; text-decoration: none; font-weight: 600;">
                                        🧭 Tour
                                    </button>
                                </form>
                                <span style="color: var(--dz-text-muted); font-size: 0.7rem;">•</span>
                            @endif
                            <a href="{{ route('profile.edit') }}" style="color: var(--dz-primary); font-size: 0.72rem; text-decoration: none; font-weight: 600;">Perfil</a>
                            @if(Auth::user()->isCasalAdmin())
                                <span style="color: var(--dz-text-muted); font-size: 0.7rem;">•</span>
                                <a href="{{ route('admin.subscriptions.index') }}" style="color: var(--dz-primary); font-size: 0.72rem; text-decoration: none; font-weight: 700;">Admin</a>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="w-100">
                        @csrf
                        <button type="submit" class="dz-btn dz-btn-outline w-100" style="font-size: 0.75rem; padding: 0.3rem 0.5rem; justify-content: center;" title="Encerrar sessão">
                            Sair da Conta 🚪
                        </button>
                    </form>
                @endif
            </div>
        </aside>

        <!-- OFFCANVAS DRAWER MOBILE -->
        <div id="dz-drawer-backdrop" class="dz-drawer-backdrop js-dz-close-drawer"></div>
        <aside id="dz-mobile-drawer" class="dz-mobile-drawer" aria-label="Menu móvel lateral">
            <div class="dz-mobile-drawer__head">
                <a href="{{ route('dashboard') }}" class="dz-brand" style="margin-bottom: 0;">
                    <img src="{{ asset('images/duozen-logo.png') }}" alt="{{ config('app.name', 'DuoZen') }}" style="height: 38px;">
                </a>
                <button type="button" class="dz-btn-icon js-dz-close-drawer" aria-label="Fechar menu">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Status do Casal Conectado -->
            @if(isset($couple) && $couple)
                <div class="dz-couple-status mb-3">
                    <div class="dz-avatar-duo">
                        <div class="dz-avatar dz-avatar--user1" title="{{ $user1Name }}">{{ substr($user1Name, 0, 1) }}</div>
                        @if ($user2)
                            <div class="dz-avatar dz-avatar--user2" title="{{ $user2Name }}">{{ substr($user2Name, 0, 1) }}</div>
                        @endif
                    </div>
                    <div class="dz-couple-status__info">
                        <div class="dz-couple-status__name">{{ $couple->name ?? ($user1Short . ($user2 ? ' & ' . $user2Short : '')) }}</div>
                        <div class="dz-couple-status__label">
                            <span class="dz-couple-status__sync-dot"></span> Sincronizado
                        </div>
                    </div>
                </div>
            @endif

            <!-- Menu de Navegação Completo Mobile -->
            <ul class="dz-nav-menu" style="flex: 1; overflow-y: auto;">
                <li class="dz-nav-item">
                    <a href="{{ route('dashboard') }}" class="dz-nav-link {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
                        <span class="dz-nav-link__icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        </span>
                        <span>Painel</span>
                    </a>
                </li>
                <li class="dz-nav-item">
                    <a href="{{ route('transactions.index') }}" class="dz-nav-link {{ request()->routeIs('transactions*') ? 'active' : '' }}">
                        <span class="dz-nav-link__icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        </span>
                        <span>Lançamentos</span>
                    </a>
                </li>
                <li class="dz-nav-item">
                    <a href="{{ route('reports.index') }}" class="dz-nav-link {{ request()->routeIs('reports*') ? 'active' : '' }}">
                        <span class="dz-nav-link__icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </span>
                        <span>Relatórios</span>
                    </a>
                </li>
                <li class="dz-nav-item">
                    <a href="{{ route('accounts.index') }}" class="dz-nav-link {{ request()->routeIs('accounts*') ? 'active' : '' }}">
                        <span class="dz-nav-link__icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                        </span>
                        <span>Contas & Cartões</span>
                    </a>
                </li>
                <li class="dz-nav-item">
                    <a href="{{ route('credit-card-statements.index') }}" class="dz-nav-link {{ request()->routeIs('credit-card-statements*') ? 'active' : '' }}">
                        <span class="dz-nav-link__icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        </span>
                        <span>Faturas de Cartão</span>
                        @if(count($sidebarInvoiceReminders ?? []) > 0)
                            <span class="dz-nav-link__badge">{{ count($sidebarInvoiceReminders) }}</span>
                        @endif
                    </a>
                </li>
                <li class="dz-nav-item">
                    <a href="{{ route('cofrinhos.index') }}" class="dz-nav-link {{ request()->routeIs('cofrinhos*') ? 'active' : '' }}">
                        <span class="dz-nav-link__icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <span>Cofrinhos & Metas</span>
                    </a>
                </li>
                <li class="dz-nav-item">
                    <a href="{{ route('recurring-transactions.index') }}" class="dz-nav-link {{ request()->routeIs('recurring-transactions*') ? 'active' : '' }}">
                        <span class="dz-nav-link__icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </span>
                        <span>Recorrentes</span>
                    </a>
                </li>
                <li class="dz-nav-item">
                    <a href="{{ route('categories.index') }}" class="dz-nav-link {{ request()->routeIs('categories*') ? 'active' : '' }}">
                        <span class="dz-nav-link__icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        </span>
                        <span>Categorias</span>
                    </a>
                </li>
                <li class="dz-nav-item">
                    <a href="{{ route('couple.index') }}" class="dz-nav-link {{ request()->routeIs('couple*') ? 'active' : '' }}">
                        <span class="dz-nav-link__icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </span>
                        <span>Casal</span>
                    </a>
                </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('contact.show') }}" class="dz-nav-link {{ request()->routeIs('contact*') ? 'active' : '' }}">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </span>
                            <span>Contato</span>
                        </a>
                    </li>
            </ul>

            <!-- Rodapé do Drawer -->
            <div style="padding-top: 1rem; border-top: 1px solid var(--dz-border-subtle); margin-top: auto;">
                @if(Auth::check())
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('profile.edit') }}" style="color: var(--dz-text-body); font-weight: 700; font-size: 0.82rem; text-decoration: none;">⚙️ Perfil</a>
                            @if(Auth::user()->couple_id && Auth::user()->passesCoupleBillingGate())
                                <form action="{{ route('onboarding.restart') }}" method="POST" class="d-inline m-0 p-0">
                                    @csrf
                                    <button type="submit" style="border: none; background: none; color: var(--dz-primary); font-weight: 700; font-size: 0.82rem; cursor: pointer; padding: 0;" title="Iniciar tour guiado do DuoZen">🧭 Tour</button>
                                </form>
                            @endif
                            @if(Auth::user()->isCasalAdmin())
                                <a href="{{ route('admin.subscriptions.index') }}" style="color: var(--dz-primary); font-weight: 700; font-size: 0.82rem; text-decoration: none;">🛡️ Admin</a>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" style="border: none; background: none; color: var(--dz-danger); font-weight: 700; font-size: 0.82rem; cursor: pointer;">Sair 🚪</button>
                        </form>
                    </div>
                @endif
            </div>
        </aside>

        <!-- WORKSPACE PRINCIPAL -->
        <main class="dz-main">
            
            <!-- TOPBAR COM AÇÕES -->
            <div class="dz-topbar">
                <div class="dz-topbar__left">
                    @isset($header)
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            {{ $header }}
                        </div>
                    @else
                        <div>
                            <h1 class="dz-page-title">{{ config('app.name', 'DuoZen') }}</h1>
                        </div>
                    @endisset
                </div>

                <div class="dz-topbar__right">
                    @if(Auth::check() && Auth::user()->couple_id)
                        <div class="d-flex align-items-center gap-2 flex-wrap" id="onboarding-tx-actions">
                            @if (($canCreateAccountTransfer ?? false) === true)
                                <button type="button" class="dz-btn dz-btn-outline" data-bs-toggle="modal" data-bs-target="#modalAccountTransfer" title="Transferência entre contas correntes">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    <span>Transferir</span>
                                </button>
                            @endif
                            <button type="button" class="dz-btn dz-btn-success" data-bs-toggle="modal" data-bs-target="#modalNewTransaction" data-tx-open-preset="income" title="Registrar uma receita">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                <span>Receita</span>
                            </button>
                            <button type="button" class="dz-btn dz-btn-danger" data-bs-toggle="modal" data-bs-target="#modalNewTransaction" data-tx-open-preset="expense" title="Registrar uma despesa">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                <span>Despesa</span>
                            </button>
                        </div>
                    @endif

                    @isset($actions)
                        {{ $actions }}
                    @endisset
                </div>
            </div>

            <!-- CONTEÚDO PRINCIPAL DA PÁGINA -->
            <div class="dz-page-content-wrapper">
                {{ $slot }}
            </div>
        </main>

        <!-- BARRA DE NAVEGAÇÃO INFERIOR FIXA MOBILE -->
        <nav class="dz-bottom-nav" aria-label="Navegação móvel rápida">
            <a href="{{ route('dashboard') }}" class="dz-bottom-nav__item {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Painel</span>
            </a>
            <a href="{{ route('transactions.index') }}" class="dz-bottom-nav__item {{ request()->routeIs('transactions*') ? 'active' : '' }}">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                <span>Lançamentos</span>
            </a>
            <a href="{{ route('accounts.index') }}" class="dz-bottom-nav__item {{ request()->routeIs('accounts*') ? 'active' : '' }}">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                <span>Contas</span>
            </a>
            <a href="{{ route('cofrinhos.index') }}" class="dz-bottom-nav__item {{ request()->routeIs('cofrinhos*') ? 'active' : '' }}">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Cofrinhos</span>
            </a>
            <button type="button" class="dz-bottom-nav__item js-dz-open-drawer" aria-label="Mais opções">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span>Menu</span>
            </button>
        </nav>
    </div>

    <!-- Tour de Onboarding se habilitado -->
    @if (!empty($showOnboardingTour))
        @php
            $onboardingTourJs = public_path('js/onboarding-tour.js');
            $onboardingTourConfig = [
                'dismissUrl' => route('onboarding.dismiss'),
                'csrf' => csrf_token(),
                'route' => optional(request()->route())->getName(),
                'steps' => [
                    [
                        'step' => 1,
                        'total' => 4,
                        'route' => 'dashboard',
                        'selector' => '.dashboard-title',
                        'title' => 'Bem-vindos ao DuoZen',
                        'body' => 'Este é o painel. Nos próximos passos vamos indicar onde cadastrar a primeira conta, criar uma categoria do jeito de vocês e registrar o primeiro lançamento.',
                        'prevUrl' => null,
                        'nextUrl' => route('accounts.index'),
                    ],
                    [
                        'step' => 2,
                        'total' => 4,
                        'route' => 'accounts.index',
                        'selector' => '#btn-new-account',
                        'title' => 'Primeira conta ou cartão',
                        'body' => 'Cadastrem aqui uma conta corrente (Pix, débito, etc.) ou um cartão de crédito. É preciso pelo menos uma conta para lançar movimentos em caixa.',
                        'prevUrl' => route('dashboard'),
                        'nextUrl' => route('categories.index'),
                    ],
                    [
                        'step' => 3,
                        'total' => 4,
                        'route' => 'categories.index',
                        'selector' => '#btn-new-category',
                        'title' => 'Categorias',
                        'body' => 'Já criamos categorias iniciais (Alimentação, Moradia, …). Vocês podem acrescentar as de vocês em Nova categoria ou usar as existentes nos lançamentos.',
                        'prevUrl' => route('accounts.index'),
                        'nextUrl' => route('dashboard') . '?onboarding=tx',
                    ],
                    [
                        'step' => 4,
                        'total' => 4,
                        'route' => 'dashboard',
                        'whenQuery' => ['onboarding' => 'tx'],
                        'selector' => '#onboarding-tx-actions',
                        'title' => 'Primeiro lançamento',
                        'body' => 'Use + Receita ou + Despesa para registrar valores. Escolham conta, categorias e valores — o painel e o orçamento atualizam a partir daqui.',
                        'prevUrl' => route('categories.index'),
                        'nextUrl' => null,
                    ],
                ],
            ];
        @endphp
        <script>
            window.__DUOZEN_ONBOARDING__ = @json($onboardingTourConfig);
        </script>
        <script src="{{ asset('js/onboarding-tour.js') }}?v={{ file_exists($onboardingTourJs) ? filemtime($onboardingTourJs) : 1 }}" defer></script>
    @endif

    <!-- Modais Globais de Lançamentos e Transferências -->
    @if(Auth::check() && Auth::user()->couple_id)
        @include('transactions.partials.transaction-modals')
        @include('accounts.partials.account-transfer-modal')
    @endif

    <!-- Scripts do Sistema DuoZen 2.0 -->
    @include('layouts.partials.scripts')
    <script src="{{ asset('js/concept.js') }}?v={{ file_exists(public_path('js/concept.js')) ? filemtime(public_path('js/concept.js')) : 1 }}"></script>
    @stack('scripts')
</body>
</html>

