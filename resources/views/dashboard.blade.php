@php
    try {
        $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $period)->locale(app()->getLocale())->translatedFormat('F \d\e Y');
    } catch (\Throwable $e) {
        $periodLabel = $period;
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="dashboard-header-intro">
            <div class="dashboard-header-text">
                <h2 class="h5 mb-0 dashboard-title">Painel</h2>
                <p class="small text-secondary mb-0 mt-1 dashboard-header-period"><span class="text-body fw-medium">{{ $periodLabel }}</span></p>
            </div>

            <form action="{{ route('dashboard') }}" method="GET" class="dashboard-filter-form" id="dashboard-filter-form">
                <div class="dashboard-filter-shell" role="search" aria-label="Filtrar lançamentos do painel">
                    <div class="dashboard-filter-controls">
                        <div class="dashboard-filter-group">
                            <label for="dashboard-period" class="dashboard-filter-tag">Mês</label>
                            <input
                                id="dashboard-period"
                                type="text"
                                name="period"
                                value="{{ $period }}"
                                class="form-control form-control-sm dashboard-filter-month"
                                data-duozen-flatpickr="month"
                                autocomplete="off"
                                title="Mês de referência"
                                aria-label="Mês de referência"
                            >
                        </div>
                        <span class="dashboard-filter-divider d-none d-sm-inline" aria-hidden="true"></span>
                        <div class="dashboard-filter-group dashboard-filter-group--account">
                            <label for="dashboard-account" class="dashboard-filter-tag">Conta</label>
                            <select
                                id="dashboard-account"
                                name="account_id"
                                class="form-select form-select-sm dashboard-filter-select"
                                aria-label="Conta para filtrar"
                            >
                                <option value="" {{ ($filterAccountId ?? null) === null ? 'selected' : '' }}>Todas</option>
                                @foreach($accountsSortedForFilter as $acc)
                                    <option value="{{ $acc->id }}" {{ (int) ($filterAccountId ?? 0) === (int) $acc->id ? 'selected' : '' }}>
                                        @if($acc->isCreditCard())
                                            {{ $acc->name }} (cartão)
                                        @else
                                            {{ $acc->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if(request()->has('period') || request()->has('account_id') || request()->has('focus_transaction'))
                        <div class="dashboard-filter-reset">
                            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Limpar filtros</a>
                        </div>
                    @endif
                </div>
                <noscript>
                    <div class="mt-2">
                        <x-primary-button type="submit" class="btn-sm">Aplicar filtros</x-primary-button>
                    </div>
                </noscript>
            </form>
        </div>
    </x-slot>

    <div class="py-4 dashboard-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <span class="pt-1">{{ session('error') }}</span>
                </div>
            @endif
            @if (! empty($txRecurringPrefillBlockedReason ?? null))
                <div class="alert alert-warning border-0 shadow-sm mb-4" role="status">
                    <p class="small mb-0">{{ $txRecurringPrefillBlockedReason }}</p>
                </div>
            @endif
            @if (! empty($txCofrinhoPrefillBlockedReason ?? null))
                <div class="alert alert-warning border-0 shadow-sm mb-4" role="status">
                    <p class="small mb-0">{{ $txCofrinhoPrefillBlockedReason }}</p>
                </div>
            @endif

            <x-cofrinho-promo variant="hero" class="mb-4" />

            <details class="card border-0 shadow-sm mb-4">
                <summary class="card-body py-3 px-4 small fw-semibold text-secondary" style="cursor: pointer;">
                    Personalizar blocos do painel
                </summary>
                <div class="card-body border-top pt-3 px-4 pb-4">
                    <form method="post" action="{{ route('dashboard.widgets.update') }}" class="vstack gap-2">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period }}">
                        @if($filterAccountId ?? null)
                            <input type="hidden" name="account_id" value="{{ $filterAccountId }}">
                        @endif
                        @foreach($dashboardPanelLabels ?? [] as $key => $label)
                            @php $visible = ! in_array($key, $hiddenDashboardPanels ?? [], true); @endphp
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visible_panels[]" value="{{ $key }}" id="dash-panel-{{ $key }}" @checked($visible)>
                                <label class="form-check-label" for="dash-panel-{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill align-self-start mt-2">Guardar</button>
                    </form>
                </div>
            </details>

            @if ($filteredRegularAccountBalance !== null)
                <div class="mb-3">
                    <span class="tx-balance-pill text-secondary">
                        <span class="small text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.04em;">Saldo da conta</span>
                        <span class="fw-semibold {{ $filteredRegularAccountBalance >= 0 ? 'text-body' : 'text-danger' }}">R$ {{ number_format($filteredRegularAccountBalance, 2, ',', '.') }}</span>
                        <span class="small d-none d-md-inline">(todos os lançamentos)</span>
                    </span>
                </div>
            @endif
            @if(! in_array('reminders', $hiddenDashboardPanels ?? [], true))
                @include('partials.rt-reminder-panel', [
                    'reminders' => $recurringReminders ?? collect(),
                    'invoiceReminders' => $creditCardInvoiceReminders ?? collect(),
                    'month' => $month,
                    'year' => $year,
                ])
            @endif
            @if($showAlert)
                <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <div class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <div class="pt-0">
                        <h3 class="h6 text-danger-emphasis mb-1">Atenção com os gastos</h3>
                        <p class="small mb-0 text-danger">
                            Vocês já atingiram <strong>{{ number_format($thresholdPercentage, 0) }}%</strong> da renda mensal planejada (R$ {{ number_format($thresholdAmount, 2, ',', '.') }}).
                            Atualmente os gastos somam <strong>R$ {{ number_format($totalExpense, 2, ',', '.') }}</strong>.
                        </p>
                    </div>
                </div>
            @endif

            @if(! in_array('kpis', $hiddenDashboardPanels ?? [], true))
            <div class="row g-4 mb-4" id="onboarding-anchor-welcome">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 dashboard-kpi-card dashboard-kpi-card--income">
                        <div class="card-body p-4 d-flex align-items-center gap-3">
                            <div class="dashboard-kpi-icon bg-success-subtle text-success">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="small text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.65rem; letter-spacing: 0.06em;">Receitas</p>
                                <p class="h4 mb-0 fw-semibold">R$ {{ number_format($totalIncome, 2, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 dashboard-kpi-card dashboard-kpi-card--expense">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="dashboard-kpi-icon bg-danger-subtle text-danger">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" /></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="small text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.65rem; letter-spacing: 0.06em;">Despesas</p>
                                    <p class="h4 mb-0 fw-semibold">R$ {{ number_format($totalExpense, 2, ',', '.') }}</p>
                                </div>
                            </div>
                            @php
                                $incomeForProgress = $plannedIncomeResolved ?? 0;
                                $percentage = $incomeForProgress > 0 ? ($totalExpense / $incomeForProgress) * 100 : 0;
                                $threshold = $couple->spending_alert_threshold ?? 80;
                                $isOverThreshold = $percentage >= $threshold;
                            @endphp
                            @if($incomeForProgress > 0)
                                <div class="small">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-uppercase fw-semibold {{ $isOverThreshold ? 'text-warning' : 'text-secondary' }}" style="font-size: 0.65rem; letter-spacing: 0.04em;">Uso da renda informada</span>
                                        <span class="fw-bold {{ $isOverThreshold ? 'text-warning' : 'text-secondary' }}">{{ number_format($percentage, 1, ',', '.') }}%</span>
                                    </div>
                                    <div class="progress rounded-pill" style="height: 10px;">
                                        <div class="progress-bar rounded-pill {{ $isOverThreshold ? 'bg-warning' : 'bg-primary' }}" style="width: {{ number_format(min($percentage, 100), 2, '.', '') }}%"></div>
                                    </div>
                                </div>
                            @else
                                <p class="small text-secondary bg-body-tertiary rounded-3 border border-secondary-subtle px-3 py-2 mb-0 text-center">Configure a renda mensal em Casal para ver o progresso.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 dashboard-kpi-card {{ $balance >= 0 ? 'dashboard-kpi-card--balance' : 'dashboard-kpi-card--balance-negative' }}">
                        <div class="card-body p-4 d-flex align-items-center gap-3">
                            <div class="dashboard-kpi-icon {{ $balance >= 0 ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="small text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.65rem; letter-spacing: 0.06em;">Saldo do período</p>
                                <p class="h4 mb-0 fw-semibold {{ $balance >= 0 ? 'text-primary' : 'text-danger' }}">R$ {{ number_format($balance, 2, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(! in_array('liquidity', $hiddenDashboardPanels ?? [], true))
            <div class="row g-4 mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <p class="small text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.65rem; letter-spacing: 0.06em;">Fluxo em contas correntes</p>
                            <p class="small text-secondary mb-2">Período: referência {{ sprintf('%02d/%04d', $month, $year) }}</p>
                            <div class="d-flex justify-content-between small mb-1"><span>Entradas</span><span class="fw-semibold text-success">R$ {{ number_format($regularCashFlow['in'], 2, ',', '.') }}</span></div>
                            <div class="d-flex justify-content-between small"><span>Saídas</span><span class="fw-semibold text-danger">R$ {{ number_format($regularCashFlow['out'], 2, ',', '.') }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <p class="small text-secondary text-uppercase fw-semibold mb-1 d-flex align-items-center gap-1" style="font-size: 0.65rem; letter-spacing: 0.06em;">
                                Gasto no cartão (ciclo {{ sprintf('%02d/%04d', $cycleRef['month'], $cycleRef['year']) }})
                                <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="O KPI «Despesas» do painel não inclui compras no cartão. Este valor soma despesas no crédito no ciclo de referência (mês do filtro + 1). Pode não coincidir com a lista ao lado, que usa a data da compra no mês do filtro.">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="text-secondary" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </span>
                            </p>
                            <p class="h4 mb-0 fw-semibold">R$ {{ number_format($creditCardKpiTotal, 2, ',', '.') }}</p>
                            @if($cycleReportAccount ?? null)
                                <p class="small mb-0 mt-2">
                                    <a class="fw-semibold" href="{{ route('reports.credit-card-cycle', ['account' => $cycleReportAccount->id, 'referenceYear' => $cycleRef['year'], 'referenceMonth' => $cycleRef['month']]) }}">Relatório do ciclo — {{ $cycleReportAccount->name }}</a>
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <p class="small text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.65rem; letter-spacing: 0.06em;">Renda planejada vs receitas</p>
                            <p class="small mb-2">Planejada (vigente): <strong>R$ {{ number_format($plannedIncomeResolved, 2, ',', '.') }}</strong> · Realizado: <strong>R$ {{ number_format($totalIncome, 2, ',', '.') }}</strong></p>
                            <p class="small mb-0 {{ $deltaPlannedVsRealized >= 0 ? 'text-success' : 'text-danger' }}">Δ realizado − planejada: <strong>R$ {{ number_format($deltaPlannedVsRealized, 2, ',', '.') }}</strong>
                                @if($momIncomePct !== null)
                                    · Receitas vs mês anterior: {{ number_format($momIncomePct, 1, ',', '.') }}%
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(! in_array('mom_burn', $hiddenDashboardPanels ?? [], true))
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-semibold mb-3">Comparativo com mês anterior</h3>
                            <ul class="list-unstyled small mb-0">
                                <li class="d-flex justify-content-between py-1 border-bottom border-secondary-subtle"><span>Receitas</span><span>{{ $momIncomeDelta >= 0 ? '+' : '' }}R$ {{ number_format($momIncomeDelta, 2, ',', '.') }}</span></li>
                                <li class="d-flex justify-content-between py-1 border-bottom border-secondary-subtle"><span>Despesas (caixa)</span><span>{{ $momExpenseDelta >= 0 ? '+' : '' }}R$ {{ number_format($momExpenseDelta, 2, ',', '.') }}</span></li>
                                <li class="d-flex justify-content-between py-1 border-bottom border-secondary-subtle"><span>Saldo do período</span><span>{{ $momBalanceDelta >= 0 ? '+' : '' }}R$ {{ number_format($momBalanceDelta, 2, ',', '.') }}</span></li>
                                <li class="d-flex justify-content-between py-1"><span>Gasto cartão (ciclo m+1)</span><span>{{ $momCardDelta >= 0 ? '+' : '' }}R$ {{ number_format($momCardDelta, 2, ',', '.') }}</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <h3 class="h6 fw-semibold mb-0">Ritmo diário (burn rate)</h3>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Base do cálculo">
                                    @php
                                        $burnQuery = array_filter([
                                            'period' => $period,
                                            'account_id' => $filterAccountId,
                                            'focus_transaction' => $focusTransactionId ?? null,
                                        ]);
                                    @endphp
                                    <a href="{{ route('dashboard', array_merge($burnQuery, ['burn_base' => 'income'])) }}" class="btn btn-outline-secondary {{ ($burnBase ?? 'income') === 'income' ? 'active' : '' }}">Renda</a>
                                    <a href="{{ route('dashboard', array_merge($burnQuery, ['burn_base' => 'budget'])) }}" class="btn btn-outline-secondary {{ ($burnBase ?? 'income') === 'budget' ? 'active' : '' }}">Orçamento</a>
                                </div>
                            </div>
                            <p class="small text-secondary mb-2">Dias corridos restantes no mês: <strong>{{ $burnDaysRemaining }}</strong></p>
                            @if($showBurnSetupCta ?? false)
                                <p class="small text-secondary mb-0">Defina <a href="{{ route('couple.index') }}" class="fw-semibold">renda em Casal</a> ou <a href="{{ route('categories.index') }}#orcamento" class="fw-semibold">orçamentos</a> para ver o ritmo sugerido.</p>
                            @else
                                <p class="small mb-1">Restante ({{ ($burnBase ?? 'income') === 'budget' ? 'orçamento global' : 'renda − despesas caixa' }}): <strong>R$ {{ number_format($burnRemaining, 2, ',', '.') }}</strong></p>
                                @if(($burnPerDay ?? null) !== null)
                                    <p class="h5 mb-0 fw-semibold">~ R$ {{ number_format($burnPerDay, 2, ',', '.') }} / dia</p>
                                @else
                                    <p class="small text-secondary mb-0">Último dia do período ou sem dias restantes.</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(! in_array('top_cats', $hiddenDashboardPanels ?? [], true))
            @if($topCategoriesCurrent->isNotEmpty())
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h3 class="h6 fw-semibold mb-3">Top categorias de despesa (mês atual vs anterior)</h3>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Categoria</th><th class="text-end">{{ sprintf('%02d/%04d', $month, $year) }}</th><th class="text-end">{{ sprintf('%02d/%04d', $prevMonth, $prevYear) }}</th></tr></thead>
                                <tbody>
                                    @php
                                        $prevMap = $topCategoriesPrev->keyBy('category_id');
                                    @endphp
                                    @foreach($topCategoriesCurrent as $row)
                                        @php $pv = $prevMap[$row->category_id]->total ?? 0; @endphp
                                        <tr>
                                            <td>{{ $row->name }}</td>
                                            <td class="text-end">R$ {{ number_format($row->total, 2, ',', '.') }}</td>
                                            <td class="text-end">R$ {{ number_format($pv, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
            @endif

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden dashboard-tx-list-card">
                <div class="dashboard-tx-list-head px-4 py-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div class="min-w-0">
                            <h3 class="h5 mb-1 fw-semibold">Lançamentos do período</h3>
                            <p class="small text-secondary mb-0">No cartão, o mês do painel filtra pela <strong class="fw-medium text-body">data da compra</strong>; parcelas aparecem numa linha com o total.</p>
                            @if (! empty($focusTransactionId))
                                <p class="small text-primary mb-0 mt-2">
                                    A mostrar apenas o lançamento aberto a partir da fatura.
                                    <a href="{{ route('dashboard', array_filter(['period' => $period, 'account_id' => $filterAccountId])) }}" class="fw-semibold">Ver todos os lançamentos deste filtro</a>.
                                </p>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-end flex-shrink-0" id="onboarding-tx-actions" role="group" aria-label="Ações">
                            @if (($canCreateAccountTransfer ?? false) === true)
                                <button
                                    type="button"
                                    class="btn btn-outline-primary rounded-pill px-3"
                                    title="Registrar transferência entre contas correntes (despesa na origem e receita no destino)"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalAccountTransfer"
                                >
                                    + Transferência
                                </button>
                            @endif
                            <button
                                type="button"
                                class="btn btn-outline-success rounded-pill px-3"
                                title="Registrar uma receita no período selecionado"
                                data-bs-toggle="modal"
                                data-bs-target="#modalNewTransaction"
                                data-tx-open-preset="income"
                            >
                                + Receita
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-danger rounded-pill px-3"
                                title="Registrar uma despesa no período selecionado"
                                data-bs-toggle="modal"
                                data-bs-target="#modalNewTransaction"
                                data-tx-open-preset="expense"
                            >
                                + Despesa
                            </button>
                        </div>
                    </div>
                </div>

                <div class="list-group list-group-flush" role="list">
                    @include('transactions.partials.transaction-list-rows', [
                        'emptyTitle' => 'Nenhum lançamento neste período',
                        'emptyHint' => 'Altere o mês no filtro do painel ou registe com <strong class="fw-medium text-body">+ Receita</strong> ou <strong class="fw-medium text-body">+ Despesa</strong>.',
                    ])
                </div>
            </div>
        </div>
    </div>

    @include('transactions.partials.transaction-modals')

    @if (($canCreateAccountTransfer ?? false) === true)
        @php
            $transferModalOpen = $errors->any() && old('_form') === 'account-transfer';
        @endphp
        <div
            class="modal fade"
            id="modalAccountTransfer"
            tabindex="-1"
            aria-labelledby="modalAccountTransferLabel"
            aria-hidden="true"
            data-open-on-load="{{ $transferModalOpen ? '1' : '0' }}"
        >
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form action="{{ route('accounts.transfer') }}" method="POST" class="d-flex flex-column">
                        @csrf
                        <input type="hidden" name="_form" value="account-transfer">

                        <div class="modal-header align-items-start tx-modal-head">
                            <div class="pe-3">
                                <h2 class="modal-title h5 mb-1" id="modalAccountTransferLabel">Transferir entre contas</h2>
                                <p class="small text-secondary mb-0 fw-normal">
                                    Registra uma <strong>despesa</strong> na origem e uma <strong>receita</strong> no destino. Apenas contas correntes (não cartão de crédito).
                                </p>
                            </div>
                            <button type="button" class="btn-close flex-shrink-0 mt-1" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>

                        <div class="modal-body vstack gap-3">
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
                                <x-text-input
                                    id="transfer_amount_dash"
                                    name="amount"
                                    type="text"
                                    inputmode="decimal"
                                    class="mt-1"
                                    required
                                    placeholder="0,00"
                                    value="{{ old('_form') === 'account-transfer' ? old('amount') : '' }}"
                                />
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_date_dash" value="Data" />
                                <x-text-input
                                    id="transfer_date_dash"
                                    name="date"
                                    type="text"
                                    data-duozen-flatpickr="date"
                                    class="mt-1"
                                    required
                                    autocomplete="off"
                                    value="{{ old('_form') === 'account-transfer' ? old('date', now()->toDateString()) : now()->toDateString() }}"
                                />
                                <x-input-error :messages="$errors->get('date')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_pm_dash" value="Forma de pagamento (registro)" />
                                <select id="transfer_pm_dash" name="payment_method" class="form-select mt-1" required>
                                    @foreach ($transferPaymentMethods as $pm)
                                        <option value="{{ $pm }}" @selected(old('_form') === 'account-transfer' ? old('payment_method') === $pm : $loop->first)>
                                            {{ $pm }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_desc_dash" value="Descrição (opcional)" />
                                <x-text-input
                                    id="transfer_desc_dash"
                                    name="description"
                                    type="text"
                                    class="mt-1"
                                    maxlength="255"
                                    placeholder="Ex.: Ajuste entre contas"
                                    value="{{ old('_form') === 'account-transfer' ? old('description') : '' }}"
                                />
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                        </div>

                        <div class="modal-footer flex-wrap gap-2 border-top">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar sem transferir" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Registrar a transferência entre as contas escolhidas">Confirmar transferência</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                (function () {
                    const transferModal = document.getElementById('modalAccountTransfer');
                    if (transferModal && transferModal.dataset.openOnLoad === '1') {
                        bootstrap.Modal.getOrCreateInstance(transferModal).show();
                    }
                })();
            </script>
        @endpush
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
