@php
    $user = auth()->user();
    $couple = $user?->couple;
    $subscription = $user?->subscription('default');
    
    $isOnTrial = $subscription?->onTrial() ?? false;
    $trialEndsAt = $subscription?->trial_ends_at;
    $endsAt = $subscription?->ends_at;
    
    $daysRemainingInTrial = null;
    if ($isOnTrial && $trialEndsAt) {
        $daysRemainingInTrial = max(0, (int) now()->diffInDays($trialEndsAt, false));
    }
    
    $isCancelled = $subscription?->canceled() ?? false;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="dz-page-title">Assinatura & Plano</h1>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Gerenciamento do plano DuoZen para o casal, período de teste e faturamento seguro via Stripe
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        @if ($coupleHasAccess && $isSubscriber)
            <a href="{{ route('billing.portal') }}" target="_blank" rel="noopener noreferrer" class="dz-btn dz-btn-primary" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Acessar o portal seguro do Stripe em nova aba para gerenciar cartão e faturas">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                Portal Stripe ↗
            </a>
        @elseif ($coupleHasAccess && ! $isSubscriber)
            <a href="{{ route('couple.index') }}" class="dz-btn dz-btn-outline" title="Ver detalhes do espaço do casal">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Espaço do Casal ↗
            </a>
        @elseif ($billingEnforced)
            <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-semibold">
                🎁 {{ $trialDays }} dias de teste grátis
            </span>
        @endif
    </x-slot>

    <div class="container-xxl py-4 px-3 px-lg-4 billing-page">
        {{-- Alertas de feedback --}}
        @if (session('success'))
            <x-alert type="success" class="mb-4" :message="session('success')" />
        @endif
        @if (session('info'))
            <x-alert type="info" class="mb-4" :message="session('info')" />
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-4" :message="session('error')" />
        @endif

        {{-- TOP KPIS DUOZEN 2.0 --}}
        <section class="dz-kpi-grid mb-4">
            <!-- Status do Plano -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Status do Plano</span>
                    <div class="dz-kpi-card__icon-box {{ $coupleHasAccess ? 'dz-kpi-card__icon-box--success' : 'dz-kpi-card__icon-box--primary' }}">
                        💎
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value {{ $coupleHasAccess ? 'text-success' : 'text-primary' }}" style="font-size: 1.35rem;">
                        @if (! $billingEnforced)
                            Isento / Livre
                        @elseif ($coupleHasAccess)
                            @if ($isOnTrial)
                                Período de Teste
                            @elseif ($isSubscriber)
                                Plano Ativo
                            @else
                                Coberto pelo Parceiro
                            @endif
                        @else
                            Aguardando Ativação
                        @endif
                    </div>
                    <div class="dz-kpi-card__footer">
                        @if (! $billingEnforced)
                            <span>Ambiente de desenvolvimento</span>
                        @elseif ($coupleHasAccess)
                            <span class="d-flex align-items-center gap-1">
                                <span class="dz-couple-status__sync-dot"></span> Acesso liberado ao casal
                            </span>
                        @else
                            <span>{{ $trialDays }} dias sem custo inicial</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Vigência & Renovação -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Vigência & Renovação</span>
                    <div class="dz-kpi-card__icon-box" style="background: rgba(14, 165, 233, 0.15); color: #0284C7;">
                        📅
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value" style="font-size: 1.35rem; color: var(--dz-text-title);">
                        @if ($isOnTrial && $trialEndsAt)
                            Até {{ $trialEndsAt->timezone(config('app.timezone', 'America/Sao_Paulo'))->translatedFormat('d/m/Y') }}
                        @elseif ($isCancelled && $endsAt)
                            Cancela em {{ $endsAt->timezone(config('app.timezone', 'America/Sao_Paulo'))->translatedFormat('d/m/Y') }}
                        @elseif ($coupleHasAccess)
                            Mensal Automática
                        @else
                            {{ $trialDays }} Dias Grátis
                        @endif
                    </div>
                    <div class="dz-kpi-card__footer">
                        @if ($isOnTrial)
                            <span>{{ $daysRemainingInTrial > 0 ? $daysRemainingInTrial . ' dias restantes no teste' : 'Último dia de teste' }}</span>
                        @elseif ($coupleHasAccess)
                            <span>Renovação sem fidelidade</span>
                        @else
                            <span>Cobrança somente pós-teste</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Titular Financeiro -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Responsável Financeiro</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--warning">
                        💳
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-truncate" style="font-size: 1.35rem; color: var(--dz-text-title);" title="{{ $isSubscriber ? $user->name : ($billingOwner?->name ?? 'A definir') }}">
                        @if ($isSubscriber)
                            {{ $user->firstGivenName() }} (Você)
                        @elseif ($billingOwner)
                            {{ $billingOwner->firstGivenName() }} (Parceiro)
                        @else
                            A definir
                        @endif
                    </div>
                    <div class="dz-kpi-card__footer">
                        @if ($isSubscriber)
                            <span>Cartão gerenciado no Stripe</span>
                        @elseif ($billingOwner)
                            <span>Assinatura ativa pelo parceiro</span>
                        @else
                            <span>1 membro ativa para ambos</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Espaço do Casal -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Espaço do Casal</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                        👫
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-truncate" style="font-size: 1.35rem; color: var(--dz-text-title);" title="{{ $couple?->name ?? 'Espaço DuoZen' }}">
                        {{ $couple?->name ?? 'Espaço DuoZen' }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>1 assinatura = 2 contas conectadas</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- CONTEÚDO PRINCIPAL (GRID 2 COLUNAS DE MESMA ALTURA) --}}
        <div class="row g-4 align-items-stretch">
            {{-- COLUNA DA ESQUERDA: CARTÃO PRINCIPAL DE STATUS / ATIVAÇÃO --}}
            <div class="col-lg-7 d-flex flex-column">
                <div class="billing-plan-card h-100 d-flex flex-column justify-content-between">
                    @if (! $billingEnforced)
                        {{-- ESTADO: COBRANÇA DESATIVADA --}}
                        <div>
                            <div class="billing-card-head billing-card-head--muted">
                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                    <div>
                                        <h2 class="h5 mb-1 fw-bold" style="color: var(--dz-text-title);">Cobrança Desativada</h2>
                                        <p class="small text-secondary mb-0">Neste ambiente a assinatura não é exigida.</p>
                                    </div>
                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary border px-3 py-2 fw-semibold">
                                        ⚙️ Modo Isento / Dev
                                    </span>
                                </div>
                            </div>
                            <div class="billing-card-body">
                                <p class="text-secondary mb-3">
                                    A cobrança automática está desligada neste ambiente (Stripe incompleto ou <code class="px-2 py-1 rounded bg-body-secondary text-primary font-monospace">DUOZEN_BILLING_DISABLED=true</code>).
                                </p>
                                <div class="p-3 rounded-3 mb-0" style="background: var(--dz-bg-card-subtle); border: 1px solid var(--dz-border);">
                                    <p class="small text-secondary mb-2 fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Chaves de Configuração em Produção</p>
                                    <ul class="small text-secondary mb-0 ps-3 font-monospace" style="font-size: 0.8rem;">
                                        <li>STRIPE_KEY</li>
                                        <li>STRIPE_SECRET</li>
                                        <li>STRIPE_WEBHOOK_SECRET</li>
                                        <li>STRIPE_PRICE_ID</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @elseif ($coupleHasAccess)
                        @if ($isSubscriber)
                            {{-- ESTADO: PLANO ATIVO (TITULAR) --}}
                            <div class="d-flex flex-column flex-grow-1 justify-content-between">
                                <div>
                                    <div class="billing-card-head billing-card-head--success">
                                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <span class="fs-5">✨</span>
                                                    <h2 class="h5 mb-0 fw-bold" style="color: var(--dz-text-title);">Plano DuoZen Casal (Premium)</h2>
                                                </div>
                                                <p class="small text-secondary mb-0">Assinatura ativa e sincronizada com o Stripe.</p>
                                            </div>
                                            @if ($isOnTrial)
                                                <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-semibold">
                                                    🌟 Período de Teste
                                                </span>
                                            @else
                                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold">
                                                    <span class="dz-couple-status__sync-dot d-inline-block me-1"></span> Plano Ativo
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="billing-card-body">
                                        @if ($isOnTrial)
                                            <div class="billing-trial-highlight mb-4">
                                                <div class="d-flex align-items-start gap-3">
                                                    <div class="billing-feature-icon" style="background: var(--dz-primary-subtle); color: var(--dz-primary);">
                                                        🎁
                                                    </div>
                                                    <div>
                                                        <h3 class="h6 fw-bold mb-1" style="color: var(--dz-text-title);">Você está no período de teste gratuito</h3>
                                                        <p class="small text-secondary mb-1">
                                                            Acesso total para você e seu parceiro(a) até <strong class="text-body">{{ $trialEndsAt?->timezone(config('app.timezone', 'America/Sao_Paulo'))->translatedFormat('d \d\e F \d\e Y') }}</strong>.
                                                        </p>
                                                        <p class="small text-secondary mb-0">
                                                            Nenhuma cobrança será realizada durante o teste. A renovação mensal ocorre automaticamente apenas após essa data.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif ($isCancelled)
                                            <div class="alert alert-warning border-warning-subtle mb-4 rounded-4" role="alert">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span>⚠️</span>
                                                    <div>
                                                        <strong>Cancelamento programado:</strong> O acesso continuará garantido até <strong>{{ $endsAt?->timezone(config('app.timezone', 'America/Sao_Paulo'))->translatedFormat('d/m/Y') }}</strong>.
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="billing-trial-highlight mb-4">
                                                <div class="d-flex align-items-start gap-3">
                                                    <div class="billing-feature-icon" style="background: var(--dz-success-subtle); color: var(--dz-success);">
                                                        ✓
                                                    </div>
                                                    <div>
                                                        <h3 class="h6 fw-bold mb-1" style="color: var(--dz-text-title);">Assinatura mensal ativa</h3>
                                                        <p class="small text-secondary mb-0">
                                                            O casal possui acesso contínuo a todas as ferramentas financeiras do DuoZen com renovação mensal sem fidelidade.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-3">
                                            <a href="{{ route('billing.portal') }}" target="_blank" rel="noopener noreferrer" class="dz-btn dz-btn-primary rounded-pill px-4 py-2 text-center" data-bs-toggle="tooltip" data-bs-placement="top" title="Abrir o portal seguro do Stripe em nova aba para cartão, faturas e cancelamento">
                                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                Gerenciar no Stripe Portal ↗
                                            </a>
                                            <a href="{{ route('couple.index') }}" class="dz-btn dz-btn-outline rounded-pill px-4 py-2 text-center" title="Ver membros e regras do casal">
                                                Espaço do Casal ↗
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="px-4 py-3 text-secondary small" style="border-top: 1px solid var(--dz-border-subtle); background: var(--dz-bg-card-subtle);">
                                    <div class="d-flex align-items-center gap-2">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        <span>No portal seguro do Stripe você altera o cartão, consulta notas fiscais ou cancela a qualquer instante com 1 clique.</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- ESTADO: ACESSO COBERTO PELO PARCEIRO --}}
                            <div class="d-flex flex-column flex-grow-1 justify-content-between">
                                <div>
                                    <div class="billing-card-head billing-card-head--info">
                                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <span class="fs-5">👫</span>
                                                    <h2 class="h5 mb-0 fw-bold" style="color: var(--dz-text-title);">Acesso Ativo pelo Parceiro</h2>
                                                </div>
                                                <p class="small text-secondary mb-0">Sua conta já está coberta pela assinatura do casal.</p>
                                            </div>
                                            <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-2 fw-semibold">
                                                Sincronizado
                                            </span>
                                        </div>
                                    </div>
                                    <div class="billing-card-body">
                                        <div class="billing-trial-highlight mb-4">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="billing-feature-icon" style="background: rgba(14, 165, 233, 0.15); color: #0284C7;">
                                                    🔑
                                                </div>
                                                <div>
                                                    <h3 class="h6 fw-bold mb-1" style="color: var(--dz-text-title);">Sem necessidade de novo cartão</h3>
                                                    <p class="small text-secondary mb-0">
                                                        A assinatura do DuoZen é gerenciada por 
                                                        @if (! empty($billingOwner?->name))
                                                            <strong class="text-body">{{ $billingOwner->name }}</strong>
                                                        @else
                                                            <strong>seu parceiro(a)</strong>
                                                        @endif.
                                                        Você possui acesso irrestrito a todos os lançamentos, contas, relatórios e metas do casal.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('couple.index') }}" class="dz-btn dz-btn-primary rounded-pill px-4 py-2" title="Gerenciar membros e configurações do casal">
                                                Ver Espaço do Casal ↗
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @else
                        {{-- ESTADO: ATIVAR TESTE GRÁTIS --}}
                        <div class="d-flex flex-column flex-grow-1 justify-content-between">
                            <div>
                                <div class="billing-card-head billing-card-head--primary">
                                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span class="fs-5">🚀</span>
                                                <h2 class="h5 mb-0 fw-bold" style="color: var(--dz-text-title);">Ative seu Período de Teste Grátis</h2>
                                            </div>
                                            <p class="small text-secondary mb-0">Cadastre o cartão com segurança no Stripe; a cobrança só ocorre após o teste.</p>
                                        </div>
                                        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-bold">
                                            {{ $trialDays }} Dias Grátis
                                        </span>
                                    </div>
                                </div>
                                <div class="billing-card-body">
                                    <div class="billing-trial-highlight mb-4">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="billing-feature-icon" style="background: var(--dz-primary-subtle); color: var(--dz-primary);">
                                                🎁
                                            </div>
                                            <div>
                                                <h3 class="h6 fw-bold mb-1" style="color: var(--dz-text-title);">Experimentem sem compromisso</h3>
                                                <p class="small text-secondary mb-0">
                                                    Vocês terão <strong class="text-body">{{ $trialDays }} dias inteiramente grátis</strong> para explorar todas as funcionalidades do DuoZen.
                                                    Será solicitado um cartão no Stripe Checkout para validação, mas nenhuma cobrança será feita hoje.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <form action="{{ route('billing.checkout') }}" method="POST" target="_blank" class="mb-3">
                                        @csrf
                                        <button type="submit" class="dz-btn dz-btn-primary rounded-pill px-4 py-3 fw-bold w-100 justify-content-center" data-bs-toggle="tooltip" data-bs-placement="top" title="Ir ao Stripe Checkout seguro em nova aba para cadastrar o cartão e iniciar o teste">
                                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Registrar cartão e ativar teste ({{ $trialDays }} dias grátis) ↗
                                        </button>
                                    </form>

                                    <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap mt-3">
                                        <span class="billing-trust-badge">🔒 Checkout Seguro Stripe</span>
                                        <span class="billing-trust-badge">⚡ Acesso Imediato</span>
                                        <span class="billing-trust-badge">❌ Cancele Quando Quiser</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- COLUNA DA DIREITA: RECURSOS INCLUSOS (MESMA ALTURA) --}}
            <div class="col-lg-5 d-flex flex-column">
                <div class="dz-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2" style="color: var(--dz-text-title);">
                            <span>💎</span> O que está incluso no DuoZen Casal
                        </h2>
                        
                        <div class="vstack gap-2">
                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: var(--dz-primary-subtle); color: var(--dz-primary);">
                                    👫
                                </div>
                                <div>
                                    <h3 class="small fw-bold mb-0" style="color: var(--dz-text-title);">Acesso para 2 contas</h3>
                                    <p class="small text-secondary mb-0">1 único plano sincroniza o casal em tempo real.</p>
                                </div>
                            </div>

                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: var(--dz-success-subtle); color: var(--dz-success);">
                                    💳
                                </div>
                                <div>
                                    <h3 class="small fw-bold mb-0" style="color: var(--dz-text-title);">Contas, Cartões & Faturas</h3>
                                    <p class="small text-secondary mb-0">Gestão ilimitada de lançamentos, limites e faturas.</p>
                                </div>
                            </div>

                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: var(--dz-warning-subtle); color: var(--dz-warning);">
                                    🎯
                                </div>
                                <div>
                                    <h3 class="small fw-bold mb-0" style="color: var(--dz-text-title);">Cofrinhos & Metas a Dois</h3>
                                    <p class="small text-secondary mb-0">Poupança conjunta com projeção de rendimentos.</p>
                                </div>
                            </div>

                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: rgba(14, 165, 233, 0.15); color: #0284C7;">
                                    📊
                                </div>
                                <div>
                                    <h3 class="small fw-bold mb-0" style="color: var(--dz-text-title);">Relatórios & Gráficos</h3>
                                    <p class="small text-secondary mb-0">Balanço mensal, fluxo e divisão justa de gastos.</p>
                                </div>
                            </div>

                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: var(--dz-danger-subtle); color: var(--dz-danger);">
                                    🔒
                                </div>
                                <div>
                                    <h3 class="small fw-bold mb-0" style="color: var(--dz-text-title);">Modo Privacidade & Dark Mode</h3>
                                    <p class="small text-secondary mb-0">Oculte saldos em público e personalize sua experiência.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SEÇÃO INFERIOR: DÚVIDAS FREQUENTES (FAQ) --}}
        <div class="mt-4">
            <div class="dz-card p-4">
                <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2" style="color: var(--dz-text-title);">
                    <span>❓</span> Dúvidas Frequentes
                </h2>

                <div class="row g-3 g-lg-4">
                    <div class="col-md-4">
                        <div class="billing-faq-item h-100 mb-0 pb-0 border-0">
                            <h3 class="small fw-bold mb-1.5" style="color: var(--dz-text-title);">Como funciona o período de teste?</h3>
                            <p class="small text-secondary mb-0" style="line-height: 1.45;">
                                Você cadastra o cartão pelo Stripe Checkout seguro e tem {{ $trialDays }} dias para usar o sistema sem nenhuma cobrança. A primeira mensalidade só é processada após o término desse prazo.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="billing-faq-item h-100 mb-0 pb-0 border-0">
                            <h3 class="small fw-bold mb-1.5" style="color: var(--dz-text-title);">Os dois membros precisam assinar?</h3>
                            <p class="small text-secondary mb-0" style="line-height: 1.45;">
                                Não! Uma única assinatura cobre o casal. Assim que um dos parceiros ativa o plano, o outro recebe acesso automático instantâneo.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="billing-faq-item h-100 mb-0 pb-0 border-0">
                            <h3 class="small fw-bold mb-1.5" style="color: var(--dz-text-title);">Como faço para cancelar?</h3>
                            <p class="small text-secondary mb-0" style="line-height: 1.45;">
                                Basta clicar no botão "Gerenciar no Stripe Portal" nesta página a qualquer momento. O cancelamento é 100% automático e sem taxas ou multas.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>



