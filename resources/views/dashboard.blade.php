@php
    try {
        $carbonPeriod = \Carbon\Carbon::createFromFormat('Y-m', $period);
        $periodLabel = $carbonPeriod->locale(app()->getLocale())->translatedFormat('F \d\e Y');
        $prevPeriod = $carbonPeriod->copy()->subMonth()->format('Y-m');
        $nextPeriod = $carbonPeriod->copy()->addMonth()->format('Y-m');
    } catch (\Throwable $e) {
        $periodLabel = $period;
        $prevPeriod = date('Y-m', strtotime('-1 month'));
        $nextPeriod = date('Y-m', strtotime('+1 month'));
    }

    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $spendingPressurePct = (float) (($plannedIncomeResolved ?? 0) > 0 ? (((float) $totalExpense / (float) $plannedIncomeResolved) * 100) : 0);
    $spendingPressureBar = $spendingPressurePct > 0 ? max(4, min(100, $spendingPressurePct)) : 0;
    
    $savingsRatePct = $totalIncome > 0 && $netResult > 0 ? round(($netResult / $totalIncome) * 100, 1) : 0;

    $totalAccountsBalance = (float) ($couple->accounts()->where('kind', '!=', 'credit_card')->sum('balance') ?? 0);
    
    // Nomes e dados do casal
    $user1Name = $user1 ? $user1->name : 'Você';
    $user1Short = $user1 ? explode(' ', $user1->name)[0] : 'Você';
    $user2Name = $user2 ? $user2->name : 'Parceiro(a)';
    $user2Short = $user2 ? explode(' ', $user2->name)[0] : 'Parceiro(a)';

    // Divisão percentual de despesas
    $totalUsersExpense = $user1Expense + $user2Expense;
    $user1Pct = $totalUsersExpense > 0 ? round(($user1Expense / $totalUsersExpense) * 100, 1) : 50;
    $user2Pct = $totalUsersExpense > 0 ? (100 - $user1Pct) : 50;
    
    // Sugestão de compensação 50/50
    $halfTarget = $totalUsersExpense / 2;
    $settlementAmount = abs($user1Expense - $halfTarget);
    $settlementDebtor = $user1Expense < $user2Expense ? $user1Short : $user2Short;
    $settlementCreditor = $user1Expense < $user2Expense ? $user2Short : $user1Short;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="dz-page-title dashboard-title">Painel Financeiro</h1>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Visão do período para <span class="fw-semibold text-body">{{ $couple->name ?? 'o casal' }}</span>
            </div>
        </div>

        <!-- Navegador de Período -->
        <div class="dz-period-nav">
            <a href="{{ route('dashboard', array_merge(request()->query(), ['period' => $prevPeriod])) }}" class="dz-period-nav__btn" title="Mês anterior">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="dz-period-nav__label">{{ ucfirst($periodLabel) }}</span>
            <a href="{{ route('dashboard', array_merge(request()->query(), ['period' => $nextPeriod])) }}" class="dz-period-nav__btn" title="Próximo mês">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </x-slot>

    <x-slot name="actions">
        <div class="d-flex align-items-center gap-2 flex-wrap" id="onboarding-tx-actions">
            @if (($canCreateAccountTransfer ?? false) === true)
                <button type="button" class="dz-btn dz-btn-outline" data-bs-toggle="modal" data-bs-target="#modalAccountTransfer" title="Transferência entre contas correntes">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Transferir
                </button>
            @endif
            <button type="button" class="dz-btn dz-btn-success" data-bs-toggle="modal" data-bs-target="#modalNewTransaction" data-tx-open-preset="income" title="Registrar uma receita">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Receita
            </button>
            <button type="button" class="dz-btn dz-btn-danger" data-bs-toggle="modal" data-bs-target="#modalNewTransaction" data-tx-open-preset="expense" title="Registrar uma despesa">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Despesa
            </button>
        </div>
    </x-slot>

    <!-- ALERTAS DO SISTEMA -->
    @if (session('success'))
        <x-alert type="success" class="mb-3" :message="session('success')" />
    @endif
    @if (session('error'))
        <x-alert type="danger" class="mb-3" :message="session('error')" />
    @endif
    @if (! empty($txRecurringPrefillBlockedReason ?? null))
        <x-alert type="warning" class="mb-3">
            <p class="small mb-0">{{ $txRecurringPrefillBlockedReason }}</p>
        </x-alert>
    @endif
    @if (! empty($txCofrinhoPrefillBlockedReason ?? null))
        <x-alert type="warning" class="mb-3">
            <p class="small mb-0">{{ $txCofrinhoPrefillBlockedReason }}</p>
        </x-alert>
    @endif
    @if (! empty($focusTransactionId))
        <div class="alert alert-info border-0 shadow-sm mb-3 d-flex align-items-center justify-content-between gap-2 rounded-4">
            <p class="small mb-0">
                A mostrar apenas o lançamento aberto a partir da fatura.
                <a href="{{ route('dashboard', array_filter(['period' => $period, 'account_id' => $filterAccountId])) }}" class="fw-semibold text-decoration-underline">Ver todos os lançamentos deste filtro</a>.
            </p>
        </div>
    @endif
    @if ($filteredRegularAccountBalance !== null)
        <div class="dz-card p-3 mb-4 d-flex align-items-center gap-3" style="background: var(--dz-bg-card); border-radius: var(--dz-radius-lg); border: 1px solid var(--dz-border);">
            <div style="font-size: 1.5rem;">🏦</div>
            <div class="min-w-0">
                <span class="small text-secondary d-block">Saldo da conta</span>
                <strong class="h5 mb-0 fw-bold dz-privacy-blur {{ $filteredRegularAccountBalance >= 0 ? 'text-success' : 'text-danger' }}">
                    R$ {{ number_format($filteredRegularAccountBalance, 2, ',', '.') }}
                </strong>
                <span class="small text-secondary d-block" style="font-size: 0.75rem;">Considera todos os lançamentos da conta, não apenas o período selecionado.</span>
            </div>
        </div>
    @endif
    @if ($showAlert)
        <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3 rounded-4" role="alert">
            <div class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <h3 class="h6 text-danger-emphasis mb-1 fw-bold">Atenção com os gastos do mês!</h3>
                <p class="small mb-0 text-danger">
                    Vocês atingiram <strong>{{ number_format($thresholdPercentage, 0) }}%</strong> da renda planejada (R$ <span class="duozen-privacy-blur">{{ number_format($thresholdAmount, 2, ',', '.') }}</span>). Gastos atuais somam <strong class="duozen-privacy-blur">R$ {{ number_format($totalExpense, 2, ',', '.') }}</strong>.
                </p>
            </div>
        </div>
    @endif

    <!-- 1. CARDS DE KPIS & MÉTRICAS -->
    <section class="dz-kpi-grid">
        <!-- KPI 1: Saldo em Contas -->
        <div class="dz-card dz-kpi-card">
            <div class="dz-kpi-card__head">
                <span class="dz-kpi-card__label">Saldo em Contas</span>
                <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                    🏦
                </div>
            </div>
            <div>
                <div class="dz-kpi-card__value dz-privacy-blur">{{ $money($totalAccountsBalance) }}</div>
                <div class="dz-kpi-card__footer">
                    <span>Total em Contas Correntes</span>
                    <a href="{{ route('accounts.index') }}" style="color: var(--dz-primary); font-weight: 700; text-decoration: none;">Ver contas ↗</a>
                </div>
            </div>
        </div>

        <!-- KPI 2: Receitas do Mês -->
        <div class="dz-card dz-kpi-card">
            <div class="dz-kpi-card__head">
                <span class="dz-kpi-card__label">Entradas</span>
                <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                    🟢
                </div>
            </div>
            <div>
                <div class="dz-kpi-card__value text-success dz-privacy-blur" id="dz-kpi-income">{{ $money($totalIncome) }}</div>
                @php
                    $incomePct = ($plannedIncomeResolved ?? 0) > 0 ? min(100, round(($totalIncome / $plannedIncomeResolved) * 100, 1)) : 100;
                @endphp
                <div class="dz-progress-bar">
                    <div class="dz-progress-bar__fill dz-progress-bar__fill--success" style="width: {{ $incomePct }}%;"></div>
                </div>
                <div class="dz-kpi-card__footer" style="margin-top: 0.5rem;">
                    <span>Planejado: <strong class="dz-privacy-blur">{{ $money($plannedIncomeResolved ?? 0) }}</strong></span>
                    <span style="font-weight: 700; color: var(--dz-success);">{{ $incomePct }}%</span>
                </div>
            </div>
        </div>

        <!-- KPI 3: Despesas do Mês -->
        <div class="dz-card dz-kpi-card">
            <div class="dz-kpi-card__head">
                <span class="dz-kpi-card__label">Saídas & Gastos</span>
                <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--danger">
                    🔴
                </div>
            </div>
            <div>
                <div class="dz-kpi-card__value text-danger dz-privacy-blur" id="dz-kpi-expense">{{ $money($totalExpense) }}</div>
                <div class="dz-progress-bar">
                    <div class="dz-progress-bar__fill {{ $spendingPressurePct >= $thresholdPercentage ? 'dz-progress-bar__fill--danger' : ($spendingPressurePct >= 60 ? 'dz-progress-bar__fill--warning' : 'dz-progress-bar__fill--success') }}" 
                         id="dz-kpi-pressure-bar" 
                         style="width: {{ $spendingPressureBar }}%;"></div>
                </div>
                <div class="dz-kpi-card__footer" style="margin-top: 0.5rem;">
                    <span id="dz-kpi-pressure">{{ number_format($spendingPressurePct, 1, ',', '.') }}% da renda</span>
                    <span style="font-size: 0.72rem; color: var(--dz-warning); font-weight: 700;">Alerta em {{ number_format($thresholdPercentage, 0) }}%</span>
                </div>
            </div>
        </div>

        <!-- KPI 4: Resultado Líquido -->
        <div class="dz-card dz-kpi-card">
            <div class="dz-kpi-card__head">
                <span class="dz-kpi-card__label">Resultado do Mês</span>
                <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--warning">
                    ✨
                </div>
            </div>
            <div>
                <div class="dz-kpi-card__value {{ $netResult >= 0 ? 'text-success' : 'text-danger' }} dz-privacy-blur" id="dz-kpi-result">
                    {{ $money($netResult) }}
                </div>
                <div class="dz-kpi-card__footer">
                    <span style="font-weight: 700; color: {{ $netResult >= 0 ? 'var(--dz-success)' : 'var(--dz-danger)' }};">
                        {{ $netResult >= 0 ? 'Poupança: ' . $savingsRatePct . '%' : 'Déficit no período' }}
                    </span>
                    <span>{{ $periodTransactionCount }} lançamento(s)</span>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. CARTÕES DE CRÉDITO -->
    @php
        $ccAccountsList = $creditCardAccounts ?? collect(($dashboardAccounts ?? $couple->accounts()->get()))->filter(fn($a) => $a->isCreditCard());
        $regAccountsList = ($regularAccounts ?? collect(($dashboardAccounts ?? $couple->accounts()->get()))->filter(fn($a) => !$a->isCreditCard()))->sortByDesc(fn($a) => (float) $a->balance)->values();
    @endphp

    @if($ccAccountsList->isNotEmpty())
        <div class="dz-section-head">
            <h3 class="dz-section-title">
                <span>💳 Cartões de Crédito</span>
            </h3>
            <a href="{{ route('credit-card-statements.index') }}" style="font-size: 0.82rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                Ver faturas ↗
            </a>
        </div>

        <div class="dz-cards-grid mb-4">
            @foreach ($ccAccountsList as $acc)
                @php
                    $hasLimit = $acc->tracksCreditCardLimit();
                    $limitTot = $hasLimit ? (float) $acc->credit_card_limit_total : 0;
                    $limitAvail = $hasLimit ? (float) ($acc->credit_card_limit_available ?? 0) : 0;
                    $limitUsed = max(0, $limitTot - $limitAvail);
                    $percentUsed = $limitTot > 0 ? min(100, round(($limitUsed / $limitTot) * 100)) : 0;
                    
                    $invoiceSummary = $acc->currentOpenInvoiceSummary();
                    $currentInvoice = $invoiceSummary ? (float) ($invoiceSummary['amount'] ?? 0) : (float) $acc->currentInvoiceAmount();
                    if ($currentInvoice <= 0.001) {
                        $currentInvoice = (float) $transactionsForPeriod->where('account_id', $acc->id)->where('type', 'expense')->sum('amount');
                    }
                    $cardAccent = $acc->color ?: '#7C3AED';
                @endphp
                <div class="dz-cc-card" style="border-top: 3.5px solid {{ $cardAccent }}; --card-accent-soft: {{ $cardAccent }}18;">
                    <div class="dz-cc-card__top">
                        <div>
                            <div class="dz-cc-card__chip" style="background: linear-gradient(135deg, {{ $cardAccent }}, {{ $cardAccent }}dd); box-shadow: 0 2px 6px {{ $cardAccent }}40; display: inline-flex; align-items: center; justify-content: center; color: #ffffff;">
                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="dz-cc-card__brand">{{ $acc->name }}</span>
                            </div>
                            <div style="font-size: 0.7rem; color: var(--dz-text-secondary); margin-top: 2px;">Cartão de Crédito</div>
                        </div>
                        @if($acc->credit_card_invoice_due_day)
                            <span class="dz-due-pill">Vence dia {{ $acc->credit_card_invoice_due_day }}</span>
                        @else
                            <span class="dz-due-pill">Cartão</span>
                        @endif
                    </div>

                    <div class="dz-cc-card__middle">
                        <span class="dz-cc-card__invoice-label">Fatura Atual</span>
                        <div class="dz-cc-card__invoice-value dz-privacy-blur">
                            {{ $money($currentInvoice) }}
                        </div>

                        @if($hasLimit)
                            <div style="font-size: 0.75rem; color: var(--dz-text-secondary); display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.35rem;">
                                <span>Disponível: <strong class="dz-privacy-blur" style="color: var(--dz-text-title);">{{ $money($limitAvail) }}</strong></span>
                                <span style="font-size: 0.72rem; opacity: 0.85;">Limite: {{ $money($limitTot) }}</span>
                            </div>
                            <div class="dz-progress-bar" style="background: var(--dz-border); height: 4px; border-radius: 9999px;">
                                <div class="dz-progress-bar__fill" style="background: {{ $cardAccent }}; width: {{ $percentUsed }}%;"></div>
                            </div>
                        @else
                            <div style="font-size: 0.73rem; color: var(--dz-text-secondary);">
                                Sem limite fixo configurado
                            </div>
                        @endif
                    </div>

                    <div class="dz-cc-card__bottom" style="gap: 0.5rem; flex-wrap: wrap;">
                        @if ((int) ($filterAccountId ?? 0) === (int) $acc->id)
                            <a href="{{ route('dashboard', array_diff_key(request()->query(), ['account_id' => ''])) }}#lancamentos" style="color: var(--dz-primary); font-weight: 700; text-decoration: none; font-size: 0.75rem;">
                                ✓ Filtrado (Limpar) ✕
                            </a>
                        @else
                            <a href="{{ route('dashboard', array_merge(request()->query(), ['account_id' => $acc->id])) }}#lancamentos" style="color: var(--dz-text-secondary); font-weight: 700; text-decoration: none; font-size: 0.75rem;">
                                Filtrar Compras ↘
                            </a>
                        @endif
                        <a href="{{ route('credit-card-statements.index') }}" style="color: var(--dz-primary); font-weight: 700; text-decoration: none; margin-left: auto;">
                            Ver Fatura ↗
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- 3. CONTAS BANCÁRIAS E SALDOS -->
    @if($regAccountsList->isNotEmpty())
        <div class="dz-section-head">
            <h3 class="dz-section-title">
                <span>🏦 Contas Bancárias & Saldos</span>
            </h3>
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-link p-0 text-decoration-none" style="font-size: 0.82rem; font-weight: 700; color: var(--dz-primary);" data-bs-toggle="modal" data-bs-target="#modalAccountTransfer">
                    Transferir ⇄
                </button>
                <a href="{{ route('accounts.index') }}" style="font-size: 0.82rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                    Gerenciar contas ↗
                </a>
            </div>
        </div>

        <div class="dz-cards-grid mb-4">
            @foreach ($regAccountsList as $acc)
                <!-- Card de Conta Corrente Real -->
                <div class="dz-card dz-account-card">
                    <div class="dz-account-card__head">
                        <div class="dz-account-card__bank">
                            <div class="dz-bank-icon" style="background: {{ $acc->color ?: '#7C3AED' }};">
                                {{ strtoupper(substr($acc->name, 0, 2)) }}
                            </div>
                            <div>
                                <h4 class="dz-account-card__name">{{ $acc->name }}</h4>
                                <span class="dz-account-card__tag">
                                    Conta Corrente {{ $acc->yieldsInterest() ? '• 📈 Rende juros' : '' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--dz-text-secondary); margin-bottom: 0.2rem;">Saldo em Conta</div>
                        <div class="dz-account-card__balance dz-privacy-blur {{ (float)$acc->balance >= 0 ? '' : 'text-danger' }}">
                            {{ $money($acc->balance) }}
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; border-top: 1px solid var(--dz-border-subtle); padding-top: 0.75rem; margin-top: 0.5rem;">
                        @if ((int) ($filterAccountId ?? 0) === (int) $acc->id)
                            <a href="{{ route('dashboard', array_diff_key(request()->query(), ['account_id' => ''])) }}#lancamentos" class="dz-btn dz-btn-primary" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; width: 100%;">
                                ✓ Filtrado (Limpar) ✕
                            </a>
                        @else
                            <a href="{{ route('dashboard', array_merge(request()->query(), ['account_id' => $acc->id])) }}#lancamentos" class="dz-btn dz-btn-outline" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; width: 100%;">
                                Filtrar Lançamentos ↘
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($ccAccountsList->isEmpty() && $regAccountsList->isEmpty())
        <div class="dz-section-head">
            <h3 class="dz-section-title">
                <span>💳 Contas e Cartões</span>
            </h3>
        </div>
        <div class="dz-card mb-4" style="text-align: center; padding: 2rem;">
            <p class="text-secondary mb-2">Nenhuma conta ou cartão cadastrado ainda.</p>
            <a href="{{ route('accounts.index') }}" class="dz-btn dz-btn-primary">+ Cadastrar Primeira Conta</a>
        </div>
    @endif

    <!-- 4. COFRINHOS & METAS FINANCEIRAS -->
    @if(count($cofrinhoRows ?? []) > 0)
        <div class="dz-section-head">
            <h3 class="dz-section-title">
                <span>🐷 Cofrinhos & Metas</span>
            </h3>
            <a href="{{ route('cofrinhos.index') }}" style="font-size: 0.82rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                Ver todos ↗
            </a>
        </div>

        <div class="dz-cofrinhos-grid">
            @foreach ($cofrinhoRows as $row)
                @php
                    $p = $row['project'];
                    $isAsset = $row['is_asset'];
                    $saved = (float) $row['saved'];
                    $invested = (float) $row['invested'];
                    $target = $row['target'] !== null ? (float) $row['target'] : null;
                    $pct = $row['pct'];
                    $quote = $row['quote'];
                @endphp
                <div class="dz-card dz-cofrinho-card">
                    <div class="dz-cofrinho-card__head">
                        <div class="d-flex align-items-center gap-2">
                            <span style="font-size: 1.15rem;">
                                @if($p->isBitcoin()) ₿ @elseif($p->asset_type === 'crypto') ⟠ @elseif($p->asset_type === 'stock') 📈 @elseif($p->asset_type === 'fii') 🏢 @else 🐷 @endif
                            </span>
                            <h4 class="dz-cofrinho-card__title">{{ $p->name }}</h4>
                        </div>
                        <span class="dz-cofrinho-card__yield">
                            @if($p->isBitcoin())
                                ₿ BTC
                            @elseif($isAsset)
                                {{ $p->asset_code ?: $p->assetTypeLabel() }}
                            @elseif($target !== null)
                                Com meta
                            @else
                                Livre
                            @endif
                        </span>
                    </div>

                    <div>
                        @if($isAsset)
                            <div style="font-size: 0.75rem; color: var(--dz-text-secondary);">Quantidade Acumulada</div>
                            <div class="dz-cofrinho-card__current dz-privacy-blur" style="font-size: 1.25rem;">
                                {{ rtrim(rtrim(number_format((float) $p->asset_quantity, 8, ',', '.'), '0'), ',') ?: '0' }} <span style="font-size: 0.85rem; font-weight: 600; color: var(--dz-text-secondary);">{{ $p->assetUnitLabel() }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-top: 0.35rem;">
                                <span>Patrimônio: <strong class="dz-privacy-blur text-body">{{ $money($saved) }}</strong></span>
                                <span>Investido: <strong class="dz-privacy-blur text-body">{{ $money($invested) }}</strong></span>
                            </div>
                        @else
                            <div class="dz-cofrinho-card__values">
                                <span class="dz-cofrinho-card__current dz-privacy-blur">{{ $money($saved) }}</span>
                                @if ($target !== null)
                                    <span class="dz-cofrinho-card__target">Meta: <span class="dz-privacy-blur">{{ $money($target) }}</span></span>
                                @else
                                    <span class="dz-cofrinho-card__target">Sem meta fixa</span>
                                @endif
                            </div>

                            <div class="dz-progress-bar">
                                <div class="dz-progress-bar__fill dz-progress-bar__fill--primary" style="width: {{ $pct !== null ? $pct : 100 }}%;"></div>
                            </div>
                        @endif
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: var(--dz-text-secondary); margin-top: 1rem; border-top: 1px solid var(--dz-border-subtle); padding-top: 0.65rem;">
                        <span>
                            @if($target !== null && $pct !== null)
                                {{ number_format($pct, 1, ',', '.') }}% atingido
                            @elseif($isAsset)
                                PM: R$ {{ number_format((float) $p->asset_avg_price, 2, ',', '.') }}
                            @else
                                Cofrinho livre
                            @endif
                        </span>
                        <a href="{{ route('cofrinhos.index') }}" style="color: var(--dz-primary); font-weight: 700; text-decoration: none;">Aportar / Ver ↗</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- 5. LEMBRETES & PRÓXIMOS VENCIMENTOS -->
    <div class="mb-4">
        @include('partials.rt-reminder-panel', [
            'reminders' => $recurringReminders ?? collect(),
            'invoiceReminders' => $creditCardInvoiceReminders ?? collect(),
            'month' => $month,
            'year' => $year,
            'embedded' => true,
        ])
    </div>

    <!-- 6. ÚLTIMOS LANÇAMENTOS (ATÉ 20 REGISTROS) -->
    @php
        $dashList = !empty($focusTransactionId) ? $transactions : ($recentTransactions ?? $transactions->take(20));
    @endphp
    <div id="lancamentos" class="dz-card dz-anchor-target" style="padding: 1.5rem; margin-bottom: 2.5rem;">
        <div class="dz-section-head" style="margin-bottom: 1.25rem;">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h3 class="dz-section-title">
                        <span>📋 {{ !empty($focusTransactionId) ? 'Lançamento Selecionado' : 'Últimos Lançamentos' }}</span>
                    </h3>
                    @if($filterAccountId)
                        @php
                            $activeFilteredAcc = $couple->accounts()->find($filterAccountId);
                        @endphp
                        @if($activeFilteredAcc)
                            <span class="badge d-inline-flex align-items-center gap-1" style="background: var(--dz-primary-subtle); color: var(--dz-primary); font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 9999px; border: 1px solid var(--dz-primary-border);">
                                <span>Conta: <strong>{{ $activeFilteredAcc->name }}</strong></span>
                                <a href="{{ route('dashboard', array_diff_key(request()->query(), ['account_id' => ''])) }}#lancamentos" style="color: var(--dz-primary); text-decoration: none; font-weight: 800; margin-left: 4px;" title="Remover filtro de conta">✕</a>
                            </span>
                        @endif
                    @endif
                </div>
                <span style="font-size: 0.8rem; color: var(--dz-text-secondary); display: block; margin-top: 0.2rem;">
                    {{ count($dashList) }} {{ !empty($focusTransactionId) ? 'lançamento selecionado' : 'último(s) lançamento(s)' }} • {{ ucfirst($periodLabel) }}
                </span>
            </div>
            <a href="{{ route('transactions.index', request()->query()) }}" class="dz-btn dz-btn-primary" style="font-size: 0.82rem; padding: 0.45rem 1rem; text-decoration: none; white-space: nowrap;">
                Ver todos os lançamentos ↗
            </a>
        </div>

        <!-- Lista de Lançamentos -->
        <div class="list-group list-group-flush" role="list">
            @include('transactions.partials.transaction-list-rows', [
                'transactions' => $dashList,
                'emptyTitle' => 'Nenhum lançamento neste período',
                'emptyHint' => 'Registre um novo lançamento com <strong class="fw-medium text-body">+ Receita</strong> ou <strong class="fw-medium text-body">+ Despesa</strong>, ou visualize o mês completo.',
            ])
        </div>
    </div>

    <!-- Modais Reais da Aplicação -->
    @include('transactions.partials.transaction-modals')

    @if (($canCreateAccountTransfer ?? false) === true)
        @php
            $transferModalOpen = $errors->any() && old('_form') === 'account-transfer';
        @endphp
        <div class="modal fade" id="modalAccountTransfer" tabindex="-1" aria-labelledby="modalAccountTransferLabel" aria-hidden="true" data-open-on-load="{{ $transferModalOpen ? '1' : '0' }}">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content dashboard-transfer-modal">
                    <form action="{{ route('accounts.transfer') }}" method="POST" class="d-flex flex-column">
                        @csrf
                        <input type="hidden" name="_form" value="account-transfer">

                        <div class="modal-header align-items-start tx-modal-head dashboard-transfer-modal__head">
                            <div class="pe-3">
                                <h2 class="modal-title h5 mb-1" id="modalAccountTransferLabel">Transferir entre contas</h2>
                                <p class="small text-secondary mb-0 fw-normal">
                                    Registra uma <strong>despesa</strong> na origem e uma <strong>receita</strong> no destino. Apenas contas correntes.
                                </p>
                            </div>
                            <button type="button" class="btn-close flex-shrink-0 mt-1" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>

                        <div class="modal-body vstack gap-3 dashboard-transfer-modal__body">
                            <div>
                                <x-input-label for="transfer_from_dash" value="Conta de origem" />
                                <select id="transfer_from_dash" name="from_account_id" class="form-select mt-1" required>
                                    <option value="" disabled @selected(old('_form') !== 'account-transfer' || ! old('from_account_id'))>Selecione…</option>
                                    @foreach ($regularAccounts as $acc)
                                        <option value="{{ $acc->id }}" @selected(old('_form') === 'account-transfer' && (int) old('from_account_id') === $acc->id)>
                                            {{ $acc->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('from_account_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_to_dash" value="Conta de destino" />
                                <select id="transfer_to_dash" name="to_account_id" class="form-select mt-1" required>
                                    <option value="" disabled @selected(old('_form') !== 'account-transfer' || ! old('to_account_id'))>Selecione…</option>
                                    @foreach ($regularAccounts as $acc)
                                        <option value="{{ $acc->id }}" @selected(old('_form') === 'account-transfer' && (int) old('to_account_id') === $acc->id)>
                                            {{ $acc->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('to_account_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_amount_dash" value="Valor (R$)" />
                                <input id="transfer_amount_dash" name="amount" type="text" class="form-control mt-1" placeholder="0,00" value="{{ old('_form') === 'account-transfer' ? old('amount') : '' }}" required data-duozen-currency="1">
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_date_dash" value="Data" />
                                <input id="transfer_date_dash" name="date" type="text" class="form-control mt-1" value="{{ old('_form') === 'account-transfer' && old('date') ? old('date') : date('Y-m-d') }}" data-duozen-flatpickr="date" required>
                                <x-input-error :messages="$errors->get('date')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_pm_dash" value="Forma de pagamento (registro)" />
                                <select id="transfer_pm_dash" name="payment_method" class="form-select mt-1" required>
                                    @foreach (($transferPaymentMethods ?? \App\Support\PaymentMethods::forRegularAccounts()) as $pm)
                                        <option value="{{ $pm }}" @selected(old('_form') === 'account-transfer' ? old('payment_method') === $pm : $loop->first)>
                                            {{ $pm }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_desc_dash" value="Descrição (opcional)" />
                                <input
                                    id="transfer_desc_dash"
                                    name="description"
                                    type="text"
                                    class="form-control mt-1"
                                    maxlength="255"
                                    placeholder="Ex.: Ajuste entre contas"
                                    value="{{ old('_form') === 'account-transfer' ? old('description') : '' }}"
                                >
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                        </div>

                        <div class="modal-footer dashboard-transfer-modal__foot">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Confirmar Transferência</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (! empty($focusTransactionId))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const row = document.getElementById('dashboard-tx-{{ (int) $focusTransactionId }}');
                    if (row) {
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            </script>
        @endpush
    @endif
</x-app-layout>