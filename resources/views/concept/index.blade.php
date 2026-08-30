<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DuoZen 2.0 • Proposta de Redesign Conceitual</title>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/concept.css') }}?v={{ time() }}">
    
    @php
        $money = fn ($val) => 'R$ ' . number_format((float)$val, 2, ',', '.');
    @endphp
</head>
<body class="dz-concept-body">

    <!-- ==========================================================================
         BARRA DE CONTROLE INTERATIVA DO CONCEITO (Toolbar Flutuante)
         ========================================================================== -->
    <header class="dz-demo-bar">
        <div class="dz-demo-bar__inner">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span class="dz-demo-badge">
                    <span class="dz-demo-badge__pulse"></span>
                    DuoZen 2.0 • Conceito
                </span>
                <span style="font-size: 0.8rem; color: var(--dz-text-secondary); font-weight: 600;" class="d-none d-md-inline">
                    Repaginação Visual & Experiência Casal
                </span>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <!-- Filtro de Visão do Casal -->
                <div class="dz-control-group" title="Filtrar dados pela visão do casal ou individual">
                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--dz-text-muted); padding-left: 0.5rem;">Visão:</span>
                    <button type="button" class="dz-btn-pill active" data-dz-partner="all">
                        💑 Casal
                    </button>
                    <button type="button" class="dz-btn-pill" data-dz-partner="user1">
                        👨‍💻 {{ $user1['short_name'] }}
                    </button>
                    <button type="button" class="dz-btn-pill" data-dz-partner="user2">
                        👩‍🎨 {{ $user2['short_name'] }}
                    </button>
                </div>

                <!-- Alternador de Tema Claro / Escuro -->
                <div class="dz-control-group" title="Alternar entre modo Claro e Escuro">
                    <button type="button" class="dz-btn-pill active" data-dz-theme="light">
                        ☀️ Claro
                    </button>
                    <button type="button" class="dz-btn-pill" data-dz-theme="dark">
                        🌙 Escuro
                    </button>
                </div>

                <!-- Modo Privacidade -->
                <div class="dz-control-group" title="Ocultar ou exibir valores">
                    <button type="button" class="dz-btn-pill active" data-dz-privacy="false">
                        👁️ Aberto
                    </button>
                    <button type="button" class="dz-btn-pill" data-dz-privacy="true">
                        🔒 Oculto
                    </button>
                </div>


                <!-- Botão Voltar ao Painel Real -->
                <a href="{{ route('dashboard') }}" class="dz-btn dz-btn-outline" style="font-size: 0.75rem; padding: 0.35rem 0.85rem;" title="Retornar ao painel atual de produção">
                    Voltar ao Painel ↗
                </a>
            </div>
        </div>
    </header>

    <!-- ==========================================================================
         APP SHELL (Sidebar + Main Workspace)
         ========================================================================== -->
    <div class="dz-app-wrapper">
        
        <!-- SIDEBAR MODERNA -->
        <aside class="dz-sidebar">
            <div>
                <!-- Brand / Logo Centralizada e Maior -->
                <a href="{{ url('/conceito') }}" class="dz-brand" title="DuoZen">
                    <img src="{{ asset('images/duozen-logo.png') }}" alt="DuoZen" class="dz-brand__logo">
                </a>

                <!-- Status do Casal Conectado -->
                <div class="dz-couple-status">
                    <div class="dz-avatar-duo">
                        <div class="dz-avatar dz-avatar--user1" title="{{ $user1['name'] }}">{{ substr($user1['name'], 0, 1) }}</div>
                        <div class="dz-avatar dz-avatar--user2" title="{{ $user2['name'] }}">{{ substr($user2['name'], 0, 1) }}</div>
                    </div>
                    <div class="dz-couple-status__info">
                        <div class="dz-couple-status__name">{{ $user1['short_name'] }} & {{ $user2['short_name'] }}</div>
                        <div class="dz-couple-status__label">
                            <span class="dz-couple-status__sync-dot"></span> Sincronizado
                        </div>
                    </div>
                </div>

                <!-- Menu de Navegação -->
                <ul class="dz-nav-menu">
                    <li class="dz-nav-item">
                        <a href="{{ url('/conceito') }}" class="dz-nav-link active">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            </span>
                            <span>Painel Geral</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('reports.index') }}" class="dz-nav-link">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </span>
                            <span>Relatórios & DRE</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('accounts.index') }}" class="dz-nav-link">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                            </span>
                            <span>Contas & Bancos</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('credit-card-statements.index') }}" class="dz-nav-link">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </span>
                            <span>Faturas de Cartão</span>
                            <span class="dz-nav-link__badge">2</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('cofrinhos.index') }}" class="dz-nav-link">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <span>Cofrinhos & Metas</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('recurring-transactions.index') }}" class="dz-nav-link">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </span>
                            <span>Recorrentes</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('categories.index') }}" class="dz-nav-link">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            </span>
                            <span>Categorias</span>
                        </a>
                    </li>
                    <li class="dz-nav-item">
                        <a href="{{ route('couple.index') }}" class="dz-nav-link">
                            <span class="dz-nav-link__icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </span>
                            <span>Casal & Divisão</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Footer Sidebar: Plano e Atalhos -->
            <div class="dz-sidebar-footer">
                <div class="dz-plan-card">
                    <div class="dz-plan-card__title">
                        <span>DuoZen Premium</span>
                        <span style="color: var(--dz-success); font-weight: 700;">Ativo</span>
                    </div>
                    <div style="color: var(--dz-text-secondary); margin-top: 0.25rem;">
                        Sincronização instantânea e cofrinhos ilimitados.
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: var(--dz-text-muted);">
                    <span>Versão 2.0 Conceito</span>
                    <a href="{{ route('contact.show') }}" style="color: var(--dz-text-secondary); text-decoration: none;">Suporte</a>
                </div>
            </div>
        </aside>

        <!-- WORKSPACE PRINCIPAL -->
        <main class="dz-main">
            
            <!-- TOPBAR -->
            <div class="dz-topbar">
                <div class="dz-topbar__left">
                    <div>
                        <h1 class="dz-page-title">Painel Financeiro</h1>
                        <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                            Visão consolidada de caixa e compromissos do casal.
                        </div>
                    </div>

                    <!-- Navegador de Período -->
                    <div class="dz-period-nav">
                        <button type="button" class="dz-period-nav__btn" title="Mês anterior">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span class="dz-period-nav__label">{{ ucfirst($periodLabel) }}</span>
                        <button type="button" class="dz-period-nav__btn" title="Próximo mês">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>

                <div class="dz-topbar__right">
                    <!-- Busca Rápida -->
                    <div class="dz-search-bar">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="dz-search-tx" placeholder="Buscar lançamentos..." autocomplete="off">
                        <span class="dz-search-bar__kbd">⌘K</span>
                    </div>

                    <!-- Botões de Ação Rápida -->
                    <button type="button" class="dz-btn dz-btn-outline" data-open-concept-modal="transfer" title="Transferência entre contas">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Transferir
                    </button>
                    <button type="button" class="dz-btn dz-btn-success" data-open-concept-modal="income" title="Nova Receita">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        Receita
                    </button>
                    <button type="button" class="dz-btn dz-btn-danger" data-open-concept-modal="expense" title="Nova Despesa">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        Despesa
                    </button>
                </div>
            </div>

            <!-- ==========================================================================
                 1. CARDS DE KPIS & MÉTRICAS PRINCIPAIS
                 ========================================================================== -->
            <section class="dz-kpi-grid">
                <!-- KPI 1: Patrimônio Total / Saldo Consolidado -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Patrimônio Líquido</span>
                        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                            💎
                        </div>
                    </div>
                    <div>
                        <div class="dz-kpi-card__value dz-privacy-blur">{{ $money($kpis['net_worth']) }}</div>
                        <div class="dz-kpi-card__footer">
                            <span class="dz-kpi-card__trend dz-kpi-card__trend--up">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                +{{ $kpis['net_worth_growth_pct'] }}% este mês
                            </span>
                            <span>Bancos + Cofrinhos</span>
                        </div>
                    </div>
                </div>

                <!-- KPI 2: Receitas do Mês -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Entradas Realizadas</span>
                        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                            💰
                        </div>
                    </div>
                    <div>
                        <div class="dz-kpi-card__value text-success dz-privacy-blur" id="dz-kpi-income">{{ $money($kpis['total_income']) }}</div>
                        <div class="dz-progress-bar">
                            <div class="dz-progress-bar__fill dz-progress-bar__fill--success" style="width: {{ $kpis['income_progress'] }}%;"></div>
                        </div>
                        <div class="dz-kpi-card__footer" style="margin-top: 0.5rem;">
                            <span>Planejado: <strong class="dz-privacy-blur">{{ $money($kpis['planned_income']) }}</strong></span>
                            <span style="font-weight: 700; color: var(--dz-success);">{{ $kpis['income_progress'] }}%</span>
                        </div>
                    </div>
                </div>

                <!-- KPI 3: Despesas do Mês -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Saídas & Gastos</span>
                        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--danger">
                            💳
                        </div>
                    </div>
                    <div>
                        <div class="dz-kpi-card__value text-danger dz-privacy-blur" id="dz-kpi-expense">{{ $money($kpis['total_expense']) }}</div>
                        <div class="dz-progress-bar">
                            <div class="dz-progress-bar__fill dz-progress-bar__fill--warning" id="dz-kpi-pressure-bar" style="width: {{ $kpis['spending_pressure_pct'] }}%;"></div>
                        </div>
                        <div class="dz-kpi-card__footer" style="margin-top: 0.5rem;">
                            <span id="dz-kpi-pressure">{{ number_format($kpis['spending_pressure_pct'], 1, ',', '.') }}% da renda</span>
                            <span style="font-size: 0.72rem; color: var(--dz-warning); font-weight: 700;">Alerta em {{ $kpis['threshold_pct'] }}%</span>
                        </div>
                    </div>
                </div>

                <!-- KPI 4: Economia Líquida & Poupança -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Economia Líquida</span>
                        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--warning">
                            🚀
                        </div>
                    </div>
                    <div>
                        <div class="dz-kpi-card__value text-success dz-privacy-blur" id="dz-kpi-result">{{ $money($kpis['net_result']) }}</div>
                        <div class="dz-kpi-card__footer">
                            <span style="font-weight: 700; color: var(--dz-success);">
                                🌟 {{ $kpis['savings_rate_pct'] }}% guardado
                            </span>
                            <span>Taxa de Poupança</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ==========================================================================
                 2. PAINEL DE EQUILÍBRIO & DIVISÃO DO CASAL (Duo First)
                 ========================================================================== -->
            <section class="dz-card dz-couple-balance-card">
                <div class="dz-couple-balance__head">
                    <h2 class="dz-couple-balance__title">
                        <span>⚖️ Equilíbrio Financeiro do Casal</span>
                        <span style="font-size: 0.75rem; font-weight: 600; background: var(--dz-bg-card); padding: 0.2rem 0.6rem; border-radius: var(--dz-radius-pill); border: 1px solid var(--dz-border);">
                            Divisão 50% / 50%
                        </span>
                    </h2>
                    <div style="font-size: 0.8rem; color: var(--dz-text-secondary);">
                        Total gasto em conjunto: <strong class="dz-privacy-blur text-body">{{ $money($kpis['total_expense']) }}</strong>
                    </div>
                </div>

                <div class="dz-split-bar-container">
                    <div class="dz-split-bar">
                        <div class="dz-split-bar__segment--user1" style="width: 54.8%;" title="{{ $user1['name'] }}: 54.8%"></div>
                        <div class="dz-split-bar__segment--user2" style="width: 45.2%;" title="{{ $user2['name'] }}: 45.2%"></div>
                    </div>
                    <div class="dz-split-legend">
                        <div class="dz-split-legend__item">
                            <span class="dz-split-legend__dot" style="background: #7C3AED;"></span>
                            <span>{{ $user1['name'] }}</span>: <strong class="dz-privacy-blur">{{ $money($kpis['user1_expense']) }}</strong> (54,8%)
                        </div>
                        <div class="dz-split-legend__item">
                            <span class="dz-split-legend__dot" style="background: #EC4899;"></span>
                            <span>{{ $user2['name'] }}</span>: <strong class="dz-privacy-blur">{{ $money($kpis['user2_expense']) }}</strong> (45,2%)
                        </div>
                    </div>
                </div>

                <div class="dz-settlement-banner">
                    <div class="dz-settlement-banner__text">
                        💡 <strong>Sugestão de Acerto:</strong> <span class="dz-privacy-blur">{{ $user2['short_name'] }}</span> transfere <strong class="text-primary dz-privacy-blur">{{ $money($kpis['settlement_balance']) }}</strong> para <span class="dz-privacy-blur">{{ $user1['short_name'] }}</span> para equalizar as contas do mês perfeitamente.
                    </div>
                    <button type="button" class="dz-btn dz-btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.9rem;" data-open-concept-modal="transfer">
                        Registrar Compensação
                    </button>
                </div>
            </section>

            <!-- ==========================================================================
                 3. CONTAS BANCÁRIAS & CARTÕES DE CRÉDITO
                 ========================================================================== -->
            <div class="dz-section-head">
                <h3 class="dz-section-title">
                    <span>💳 Contas e Cartões</span>
                </h3>
                <a href="{{ route('accounts.index') }}" style="font-size: 0.82rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                    Gerenciar todos ↗
                </a>
            </div>

            <div class="dz-cards-grid">
                @foreach ($accounts as $acc)
                    @if (!$acc['is_credit_card'])
                        <!-- Card de Conta Bancária -->
                        <div class="dz-card dz-account-card">
                            <div class="dz-account-card__head">
                                <div class="dz-account-card__bank">
                                    <div class="dz-bank-icon" style="background: {{ $acc['color'] }};">
                                        {{ strtoupper(substr($acc['name'], 0, 2)) }}
                                    </div>
                                    <div>
                                        <h4 class="dz-account-card__name">{{ $acc['name'] }}</h4>
                                        <span class="dz-account-card__tag">{{ $acc['type_label'] }} • {{ $acc['owner'] }}</span>
                                    </div>
                                </div>
                                <span class="dz-partner-tag">
                                    <span class="dz-partner-tag__dot" style="background: {{ $acc['color'] }};"></span>
                                    {{ $acc['owner'] }}
                                </span>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: var(--dz-text-secondary); margin-bottom: 0.2rem;">Saldo Atual</div>
                                <div class="dz-account-card__balance dz-privacy-blur">{{ $money($acc['balance']) }}</div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; border-top: 1px solid var(--dz-border-subtle); padding-top: 0.75rem; margin-top: 0.5rem;">
                                <button type="button" class="dz-btn dz-btn-outline" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; width: 100%;" data-open-concept-modal="transfer">
                                    Transferir
                                </button>
                                <button type="button" class="dz-btn dz-btn-outline" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; width: 100%;" data-open-concept-modal="income">
                                    + Extrato
                                </button>
                            </div>
                        </div>
                    @else
                        <!-- Card de Cartão de Crédito (Design Fintech) -->
                        <div class="dz-cc-card" style="background: {{ $acc['color'] }};">
                            <div class="dz-cc-card__top">
                                <div>
                                    <div class="dz-cc-card__chip" style="background: rgba(255, 255, 255, 0.25); color: #ffffff;">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                    </div>
                                    <div class="dz-cc-card__brand">{{ $acc['name'] }}</div>
                                    <div style="font-size: 0.7rem; opacity: 0.8;">Titular: {{ $acc['owner'] }}</div>
                                </div>
                                <span class="dz-due-pill">Vence em {{ $acc['days_to_due'] }} dias</span>
                            </div>

                            <div style="margin: 1.25rem 0 0.75rem;">
                                <span class="dz-cc-card__invoice-label">Fatura Aberta (Fecha dia {{ $acc['closing_day'] }})</span>
                                <div class="dz-cc-card__invoice-value dz-privacy-blur">{{ $money($acc['current_invoice']) }}</div>
                                
                                <div class="dz-progress-bar" style="background: rgba(255, 255, 255, 0.2); height: 4px;">
                                    <div class="dz-progress-bar__fill" style="background: {{ $acc['badge_color'] }}; width: {{ ($acc['current_invoice'] / $acc['credit_limit']) * 100 }}%;"></div>
                                </div>
                            </div>

                            <div class="dz-cc-card__bottom">
                                <span>Limite Disponível: <strong class="dz-privacy-blur">{{ $money($acc['available_limit']) }}</strong></span>
                                <a href="{{ route('credit-card-statements.index') }}" style="color: #FFFFFF; font-weight: 700; text-decoration: none;">
                                    Ver Fatura ↗
                                </a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <!-- ==========================================================================
                 4. COFRINHOS & METAS FINANCEIRAS 2.0
                 ========================================================================== -->
            <div class="dz-section-head">
                <h3 class="dz-section-title">
                    <span>🐷 Cofrinhos & Metas 2.0</span>
                </h3>
                <a href="{{ route('cofrinhos.index') }}" style="font-size: 0.82rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                    Ver todos os projetos ↗
                </a>
            </div>

            <div class="dz-cofrinhos-grid">
                @foreach ($cofrinhos as $cof)
                    <div class="dz-card dz-cofrinho-card">
                        <div class="dz-cofrinho-card__head">
                            <h4 class="dz-cofrinho-card__title">{{ $cof['title'] }}</h4>
                            <span class="dz-cofrinho-card__yield">+{{ $cof['monthly_yield_pct'] }}% m/m</span>
                        </div>

                        <div>
                            <div class="dz-cofrinho-card__values">
                                <span class="dz-cofrinho-card__current dz-privacy-blur">{{ $money($cof['current_amount']) }}</span>
                                <span class="dz-cofrinho-card__target">Meta: <span class="dz-privacy-blur">{{ $money($cof['target_amount']) }}</span></span>
                            </div>

                            <div class="dz-progress-bar">
                                <div class="dz-progress-bar__fill" style="background: {{ $cof['gradient'] }}; width: {{ $cof['progress_pct'] }}%;"></div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: var(--dz-text-secondary); margin-top: 1rem; border-top: 1px solid var(--dz-border-subtle); padding-top: 0.65rem;">
                            <span>📦 {{ $cof['asset_type'] }}</span>
                            <span style="font-weight: 700; color: var(--dz-text-title);">{{ $cof['progress_pct'] }}% atingido</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- ==========================================================================
                 5. TIMELINE DE LANÇAMENTOS VS ANALYTICS & LEMBRETES
                 ========================================================================== -->
            <div class="dz-content-split">
                
                <!-- COLUNA ESQUERDA: Feed de Lançamentos Recentes -->
                <div class="dz-card" style="padding: 1.5rem;">
                    <div class="dz-section-head" style="margin-bottom: 1.25rem;">
                        <div>
                            <h3 class="dz-section-title">
                                <span>📋 Lançamentos do Período</span>
                            </h3>
                            <span style="font-size: 0.8rem; color: var(--dz-text-secondary);">
                                Exibindo movimentos de caixa e faturas em tempo real.
                            </span>
                        </div>
                    </div>

                    <!-- Abas de Filtro de Lançamentos -->
                    <div class="dz-tx-filter-tabs">
                        <button type="button" class="dz-tx-tab active" data-tx-type="all">Todos ({{ count($transactions) }})</button>
                        <button type="button" class="dz-tx-tab" data-tx-type="expense">Despesas</button>
                        <button type="button" class="dz-tx-tab" data-tx-type="income">Receitas</button>
                        <button type="button" class="dz-tx-tab" data-tx-type="credit_card">Cartões</button>
                    </div>

                    <!-- Lista de Lançamentos -->
                    <div class="dz-tx-feed">
                        @foreach ($transactions as $tx)
                            <div class="dz-tx-row" 
                                 data-type="{{ $tx['type'] }}" 
                                 data-payer="{{ $tx['payer']['short_name'] ?? 'Casal' }}"
                                 data-account-type="{{ str_contains(strtolower($tx['payment_method']), 'cartão') ? 'credit_card' : 'checking' }}">
                                
                                <div class="dz-tx-row__left">
                                    <div class="dz-category-icon" style="background: {{ $tx['category_color'] }}18; color: {{ $tx['category_color'] }};">
                                        @if ($tx['type'] === 'income')
                                            🟢
                                        @elseif (str_contains(strtolower($tx['category']), 'alimentação') || str_contains(strtolower($tx['category']), 'mercado'))
                                            🛒
                                        @elseif (str_contains(strtolower($tx['category']), 'lazer') || str_contains(strtolower($tx['category']), 'restaurante'))
                                            🍷
                                        @elseif (str_contains(strtolower($tx['category']), 'transporte'))
                                            🚗
                                        @elseif (str_contains(strtolower($tx['category']), 'viagem'))
                                            ✈️
                                        @else
                                            🏷️
                                        @endif
                                    </div>
                                    <div class="dz-tx-row__info">
                                        <h4 class="dz-tx-row__desc">{{ $tx['description'] }}</h4>
                                        <div class="dz-tx-row__meta">
                                            <span>{{ $tx['formatted_date'] }}</span>
                                            <span>•</span>
                                            <span>{{ $tx['category'] }}</span>
                                            <span>•</span>
                                            <span>{{ $tx['account'] }}</span>
                                            @if ($tx['installments'])
                                                <span class="badge" style="background: var(--dz-primary-subtle); color: var(--dz-primary); font-size: 0.68rem; padding: 0.1rem 0.4rem; border-radius: 4px;">{{ $tx['installments'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="dz-tx-row__right">
                                    <!-- Badge do Parceiro -->
                                    <span class="dz-partner-tag" title="Pago por {{ $tx['payer']['name'] ?? 'Casal' }}">
                                        <span class="dz-partner-tag__dot" style="background: {{ $tx['payer']['avatar_color'] ?? '#7C3AED' }};"></span>
                                        {{ $tx['payer']['short_name'] ?? 'Casal' }}
                                    </span>

                                    <!-- Valor -->
                                    <div class="dz-tx-row__amount {{ $tx['type'] === 'income' ? 'dz-tx-row__amount--income' : 'dz-tx-row__amount--expense' }} dz-privacy-blur">
                                        {{ $tx['type'] === 'income' ? '+' : '-' }} {{ $money($tx['amount']) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- COLUNA DIREITA: Analytics e Lembretes -->
                <div class="dz-side-column">
                    
                    <!-- Lembretes & Faturas Próximas -->
                    <div class="dz-card">
                        <div class="dz-section-head" style="margin-bottom: 0.85rem;">
                            <h3 class="dz-section-title" style="font-size: 1rem;">
                                <span>🔔 Próximos Vencimentos</span>
                            </h3>
                            <span class="badge" style="background: var(--dz-danger-subtle); color: var(--dz-danger); font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                {{ count($reminders) }} pendentes
                            </span>
                        </div>

                        <div>
                            @foreach ($reminders as $rem)
                                <div class="dz-reminder-item">
                                    <div>
                                        <div style="font-weight: 700; font-size: 0.85rem; color: var(--dz-text-title);">{{ $rem['title'] }}</div>
                                        <div style="font-size: 0.72rem; color: var(--dz-text-secondary); margin-top: 0.1rem;">
                                            Vencimento em <strong>{{ $rem['due_date'] }}</strong> ({{ $rem['account'] }})
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 800; font-size: 0.95rem; color: var(--dz-danger);" class="dz-privacy-blur">{{ $money($rem['amount']) }}</div>
                                        <span class="dz-due-pill" style="font-size: 0.65rem;">em {{ $rem['days_left'] }} dias</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Distribuição de Gastos por Categoria -->
                    <div class="dz-card">
                        <div class="dz-section-head" style="margin-bottom: 1rem;">
                            <h3 class="dz-section-title" style="font-size: 1rem;">
                                <span>📊 Distribuição por Categoria</span>
                            </h3>
                        </div>

                        <div>
                            @foreach ($categoryBreakdown as $cat)
                                <div class="dz-cat-bar-item">
                                    <div class="dz-cat-bar-item__head">
                                        <span>{{ $cat['name'] }}</span>
                                        <span class="dz-privacy-blur">{{ $money($cat['amount']) }} ({{ $cat['pct'] }}%)</span>
                                    </div>
                                    <div class="dz-progress-bar" style="height: 5px;">
                                        <div class="dz-progress-bar__fill" style="background: {{ $cat['color'] }}; width: {{ $cat['pct'] }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Fluxo de Caixa Semanal Mini Chart -->
                    <div class="dz-card">
                        <div class="dz-section-head" style="margin-bottom: 0.75rem;">
                            <h3 class="dz-section-title" style="font-size: 1rem;">
                                <span>📈 Ritmo Semanal (Entradas vs Saídas)</span>
                            </h3>
                        </div>

                        <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 110px; padding-top: 1rem;">
                            @foreach ($weeklyFlow as $wf)
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.35rem; flex: 1;">
                                    <div style="display: flex; align-items: flex-end; gap: 3px; height: 75px;">
                                        <!-- Barra Receita -->
                                        <div style="width: 10px; background: var(--dz-success); border-radius: 4px 4px 0 0; height: {{ ($wf['income'] / 9000) * 100 }}%; min-height: 4px;" title="Entrada: {{ $money($wf['income']) }}"></div>
                                        <!-- Barra Despesa -->
                                        <div style="width: 10px; background: var(--dz-danger); border-radius: 4px 4px 0 0; height: {{ ($wf['expense'] / 9000) * 100 }}%; min-height: 4px;" title="Saída: {{ $money($wf['expense']) }}"></div>
                                    </div>
                                    <span style="font-size: 0.7rem; font-weight: 700; color: var(--dz-text-muted);">{{ $wf['week'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 0.75rem; font-size: 0.75rem;">
                            <span style="display: flex; align-items: center; gap: 0.3rem;"><span style="width: 8px; height: 8px; background: var(--dz-success); border-radius: 2px;"></span> Entradas</span>
                            <span style="display: flex; align-items: center; gap: 0.3rem;"><span style="width: 8px; height: 8px; background: var(--dz-danger); border-radius: 2px;"></span> Saídas</span>
                        </div>
                    </div>

                </div>
            </div>

        </main>
    </div>

    <!-- ==========================================================================
         MODAL CONCEITO: NOVO LANÇAMENTO (Interativo)
         ========================================================================== -->
    <div class="dz-modal-overlay" id="dz-concept-modal">
        <div class="dz-modal">
            <div class="dz-modal__head">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.2rem;">✨</span>
                    <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--dz-text-title); margin: 0;">Novo Lançamento Inteligente</h3>
                </div>
                <button type="button" class="dz-btn-pill" data-close-concept-modal style="font-size: 1.1rem; padding: 0.2rem 0.5rem;">✕</button>
            </div>

            <form id="dz-concept-form">
                <div class="dz-modal__body">
                    
                    <!-- Tipo de Lançamento -->
                    <div style="display: flex; background: var(--dz-bg-card-subtle); padding: 0.3rem; border-radius: var(--dz-radius-pill); border: 1px solid var(--dz-border); gap: 0.3rem;">
                        <button type="button" class="dz-btn-pill active" data-modal-type="expense" style="flex: 1; justify-content: center;">🔴 Despesa</button>
                        <button type="button" class="dz-btn-pill" data-modal-type="income" style="flex: 1; justify-content: center;">🟢 Receita</button>
                        <button type="button" class="dz-btn-pill" data-modal-type="transfer" style="flex: 1; justify-content: center;">🔄 Transferência</button>
                    </div>

                    <!-- Valor Principal -->
                    <div class="dz-input-group">
                        <label class="dz-input-label">Valor (R$)</label>
                        <input type="text" class="dz-input" style="font-size: 1.4rem; font-weight: 800; color: var(--dz-primary);" value="R$ 150,00" required>
                    </div>

                    <!-- Descrição -->
                    <div class="dz-input-group">
                        <label class="dz-input-label">Descrição</label>
                        <input type="text" class="dz-input" placeholder="Ex: Supermercado Semanal, Pizza..." value="Cinema & Jantar" required>
                    </div>

                    <!-- Linha Dupla: Categoria e Conta -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="dz-input-group">
                            <label class="dz-input-label">Categoria</label>
                            <select class="dz-input">
                                <option>Lazer & Restaurantes</option>
                                <option>Alimentação & Mercado</option>
                                <option>Moradia & Contas</option>
                                <option>Transporte</option>
                                <option>Saúde</option>
                            </select>
                        </div>
                        <div class="dz-input-group">
                            <label class="dz-input-label">Conta / Cartão</label>
                            <select class="dz-input">
                                <option>Nubank Ultravioleta (Cartão)</option>
                                <option>Itaú Click Visa (Cartão)</option>
                                <option>Nubank Principal (CC)</option>
                                <option>Inter Mariana (CC)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Divisão entre o Casal -->
                    <div class="dz-input-group">
                        <label class="dz-input-label">Como dividir este gasto?</label>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;">
                            <button type="button" class="dz-btn-pill active" data-split-option="5050" style="justify-content: center; border: 1px solid var(--dz-border);">50% / 50%</button>
                            <button type="button" class="dz-btn-pill" data-split-option="user1" style="justify-content: center; border: 1px solid var(--dz-border);">100% {{ $user1['short_name'] }}</button>
                            <button type="button" class="dz-btn-pill" data-split-option="user2" style="justify-content: center; border: 1px solid var(--dz-border);">100% {{ $user2['short_name'] }}</button>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem; margin-top: 0.5rem;">
                        <button type="button" class="dz-btn dz-btn-outline" data-close-concept-modal style="flex: 1;">Cancelar</button>
                        <button type="submit" class="dz-btn dz-btn-primary" style="flex: 1;">Salvar Lançamento</button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- Script de Interatividade -->
    <script src="{{ asset('js/concept.js') }}?v={{ time() }}"></script>
</body>
</html>
