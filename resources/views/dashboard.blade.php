@php
    try {
        $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $period)->locale(app()->getLocale())->translatedFormat('F \d\e Y');
    } catch (\Throwable $e) {
        $periodLabel = $period;
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
            <div>
                <h2 class="h5 mb-0 dashboard-title">Painel</h2>
                <p class="small text-secondary mb-0 mt-1"><span class="text-body fw-medium">{{ $periodLabel }}</span></p>
            </div>

            <form action="{{ route('dashboard') }}" method="GET" class="dashboard-toolbar ms-lg-auto">
                <label class="small text-secondary mb-0 align-middle flex-shrink-0 d-none d-sm-inline me-sm-1" for="dashboard-period">Período</label>
                <input id="dashboard-period" type="month" name="period" value="{{ $period }}" class="form-control form-control-sm dashboard-toolbar-month" title="Mês de referência" aria-label="Mês de referência">

                <x-primary-button type="submit" class="btn-sm rounded-pill px-3 flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="me-1" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    Filtrar
                </x-primary-button>

                @if(request()->has('period'))
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 flex-shrink-0" title="Voltar ao mês atual">Limpar</a>
                @endif
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
            @include('partials.rt-reminder-panel', [
                'reminders' => $recurringReminders ?? collect(),
                'invoiceReminders' => $creditCardInvoiceReminders ?? collect(),
                'month' => $month,
                'year' => $year,
            ])
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
                                $incomeForProgress = $couple->monthly_income ?? 0;
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

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden dashboard-tx-list-card">
                <div class="dashboard-tx-list-head px-4 py-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div class="min-w-0">
                            <h3 class="h5 mb-1 fw-semibold">Lançamentos do período</h3>
                            <p class="small text-secondary mb-2 mb-lg-1">Mesma regra que em <strong class="fw-medium text-body">Lançamentos</strong>: no cartão, o mês do painel filtra pela <strong class="fw-medium text-body">data da compra</strong>; parcelas aparecem numa linha com o total.</p>
                            <a href="{{ route('transactions.index', ['month' => $month, 'year' => $year]) }}" class="small fw-semibold text-decoration-none">Lista completa em Lançamentos →</a>
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-end flex-shrink-0" role="group" aria-label="Novo lançamento">
                            <button
                                type="button"
                                class="btn btn-outline-success rounded-pill px-3"
                                data-bs-toggle="modal"
                                data-bs-target="#modalNewTransaction"
                                data-tx-open-preset="income"
                            >
                                + Receita
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-danger rounded-pill px-3"
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
                <div class="tx-pagination-wrap px-3 py-3 border-top border-light-subtle">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
    </div>

    @include('transactions.partials.transaction-modals')
</x-app-layout>
