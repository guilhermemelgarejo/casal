@php
    $now = \Carbon\Carbon::now();
    $currentPeriodLabel = \Illuminate\Support\Str::ucfirst($agendaPeriodDate->translatedFormat('F \d\e Y'));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="dz-page-title">Dívidas & Vencimentos</h1>
                <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                    Carnês, financiamentos, contas a pagar e controle do saldo devedor
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        <button type="button" class="dz-btn dz-btn-primary" data-bs-toggle="modal" data-bs-target="#modalDebtCreate">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Nova Dívida / Conta
        </button>
    </x-slot>

    <div class="container-xxl py-4 px-3 px-lg-4">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4 d-flex align-items-center" role="alert" style="background: rgba(16, 185, 129, 0.15); color: #065f46;">
                <div class="d-flex align-items-center gap-2">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error') || $errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4 d-flex align-items-center" role="alert" style="background: rgba(239, 68, 68, 0.15); color: #991b1b;">
                <div class="d-flex align-items-center gap-2">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        @if(session('error'))
                            <div>{{ session('error') }}</div>
                        @endif
                        @foreach($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- ABAS PRINCIPAIS: AGENDA (MICRO) vs DÍVIDAS & FINANCIAMENTOS (MACRO) --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 border-bottom pb-3" style="border-color: var(--dz-border) !important;">
            <ul class="nav nav-pills gap-2" id="debtsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <a
                        href="{{ route('debts.index', ['tab' => 'agenda', 'month' => $selectedMonth, 'year' => $selectedYear]) }}"
                        class="nav-link rounded-pill px-3 px-sm-4 py-2 fw-semibold {{ $activeTab === 'agenda' ? 'active' : '' }}"
                        style="{{ $activeTab === 'agenda' ? 'background: var(--dz-primary); color: #fff;' : 'background: var(--dz-bg-card-subtle); color: var(--dz-text-secondary); border: 1px solid var(--dz-border);' }}"
                    >
                        📅 Agenda do Mês
                        @if($overdueCount > 0)
                            <span class="badge rounded-pill bg-danger ms-1" style="font-size: 0.7rem;">{{ $overdueCount }} atrasada{{ $overdueCount > 1 ? 's' : '' }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a
                        href="{{ route('debts.index', ['tab' => 'dividas']) }}"
                        class="nav-link rounded-pill px-3 px-sm-4 py-2 fw-semibold {{ $activeTab === 'dividas' ? 'active' : '' }}"
                        style="{{ $activeTab === 'dividas' ? 'background: var(--dz-primary); color: #fff;' : 'background: var(--dz-bg-card-subtle); color: var(--dz-text-secondary); border: 1px solid var(--dz-border);' }}"
                    >
                        📊 Dívidas & Financiamentos
                        <span class="badge rounded-pill bg-secondary ms-1" style="font-size: 0.7rem;">{{ $activeDebts->count() }}</span>
                    </a>
                </li>
            </ul>

            @if($activeTab === 'agenda')
                {{-- SELETOR DE MÊS / ANO --}}
                <div class="d-flex align-items-center gap-2 bg-body-secondary p-1 rounded-pill border" style="border-color: var(--dz-border) !important;">
                    <a
                        href="{{ route('debts.index', ['tab' => 'agenda', 'month' => $prevPeriod->month, 'year' => $prevPeriod->year, 'status' => $statusFilter]) }}"
                        class="btn btn-sm btn-icon rounded-circle"
                        style="width: 32px; height: 32px; color: var(--dz-text-secondary);"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Mês anterior"
                        aria-label="Mês anterior"
                    >
                        &laquo;
                    </a>
                    <span class="fw-bold px-2 text-capitalize" style="font-size: 0.9rem; color: var(--dz-text-title);">
                        {{ $currentPeriodLabel }}
                    </span>
                    <a
                        href="{{ route('debts.index', ['tab' => 'agenda', 'month' => $nextPeriod->month, 'year' => $nextPeriod->year, 'status' => $statusFilter]) }}"
                        class="btn btn-sm btn-icon rounded-circle"
                        style="width: 32px; height: 32px; color: var(--dz-text-secondary);"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Próximo mês"
                        aria-label="Próximo mês"
                    >
                        &raquo;
                    </a>
                    @if($selectedMonth !== (int)$now->month || $selectedYear !== (int)$now->year)
                        <a
                            href="{{ route('debts.index', ['tab' => 'agenda', 'month' => $now->month, 'year' => $now->year]) }}"
                            class="btn btn-xs rounded-pill px-2 py-1 ms-1"
                            style="font-size: 0.75rem; background: var(--dz-primary-subtle); color: var(--dz-primary);"
                        >
                            Hoje
                        </a>
                    @endif
                </div>
            @endif
        </div>

        @if($activeTab === 'agenda')
            {{-- ========================================================================= --}}
            {{-- VISÃO 1: AGENDA DE VENCIMENTOS (MICRO / OPERACIONAL)                      --}}
            {{-- ========================================================================= --}}

            {{-- KPIS DA AGENDA --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="background: var(--dz-bg-card);">
                        <span class="text-secondary small fw-semibold">
                            {{ $viewAllInstallments ? 'Total do Contrato' : 'Total a Pagar no Mês' }}
                        </span>
                        <div class="h4 mb-0 fw-bold mt-1" style="color: var(--dz-text-title);">
                            R$ {{ number_format($totalMonthAmount, 2, ',', '.') }}
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="background: var(--dz-bg-card);">
                        <span class="text-secondary small fw-semibold">
                            {{ $viewAllInstallments ? 'Já Quitado' : 'Já Pago no Mês' }}
                        </span>
                        <div class="h4 mb-0 fw-bold mt-1 text-success">
                            R$ {{ number_format($totalMonthPaid, 2, ',', '.') }}
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="background: var(--dz-bg-card);">
                        <span class="text-secondary small fw-semibold">
                            {{ $viewAllInstallments ? 'Saldo Restante' : 'Pendente no Mês' }}
                        </span>
                        <div class="h4 mb-0 fw-bold mt-1 text-primary">
                            R$ {{ number_format($totalMonthPending, 2, ',', '.') }}
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="background: {{ $overdueCount > 0 ? 'rgba(239, 68, 68, 0.08)' : 'var(--dz-bg-card)' }}; border: {{ $overdueCount > 0 ? '1px solid rgba(239, 68, 68, 0.3)' : 'none' }} !important;">
                        <span class="{{ $overdueCount > 0 ? 'text-danger fw-bold' : 'text-secondary' }} small">Atrasadas / Pendentes</span>
                        <div class="h4 mb-0 fw-bold mt-1 {{ $overdueCount > 0 ? 'text-danger' : 'text-secondary' }}">
                            @if($overdueCount > 0)
                                R$ {{ number_format($totalOverdueAmount, 2, ',', '.') }}
                                <span style="font-size: 0.75rem;">({{ $overdueCount }})</span>
                            @else
                                Nenhuma atrasada 🎉
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ALERTA QUANDO VISUALIZANDO TODAS AS PARCELAS DO CONTRATO --}}
            @if($viewAllInstallments && $selectedDebt)
                <div class="alert alert-info py-2 px-3 rounded-4 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-0 shadow-sm" style="background: rgba(59, 130, 246, 0.12); color: #1d4ed8; font-size: 0.85rem;">
                    <div class="d-flex align-items-center gap-2">
                        <span>📋</span>
                        <span>Exibindo <strong>todas as {{ $displayedAgendaItems->count() }} parcelas</strong> do contrato <strong>{{ $selectedDebt->name }}</strong> juntas (cronograma completo).</span>
                    </div>
                    <a href="{{ route('debts.index', array_filter(['tab' => 'agenda', 'month' => $selectedMonth, 'year' => $selectedYear, 'status' => $statusFilter, 'debt_id' => $selectedDebtId])) }}" class="btn btn-sm btn-outline-primary rounded-pill py-1 px-3 fw-semibold" style="font-size: 0.75rem;">
                        📅 Voltar para visão do mês
                    </a>
                </div>
            @endif

            {{-- ALERTA DE ATRASADAS EM OUTROS PERÍODOS --}}
            @if(! $viewAllInstallments && ($globalOverdueCount ?? 0) > 0 && $overdueCount === 0)
                <div class="alert alert-warning py-2 px-3 rounded-4 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-0 shadow-sm" style="background: rgba(245, 158, 11, 0.12); color: #92400e; font-size: 0.85rem;">
                    <div class="d-flex align-items-center gap-2">
                        <span>⚠️</span>
                        <span>Você possui <strong>{{ $globalOverdueCount }} parcela{{ $globalOverdueCount > 1 ? 's' : '' }} atrasada{{ $globalOverdueCount > 1 ? 's' : '' }}</strong> em outro(s) período(s) somando R$ {{ number_format($globalOverdueAmount, 2, ',', '.') }}.</span>
                    </div>
                    <a href="{{ route('debts.index', ['tab' => 'agenda', 'month' => $now->month, 'year' => $now->year]) }}" class="btn btn-sm btn-outline-warning rounded-pill py-1 px-3 fw-semibold" style="color: #92400e; border-color: #d97706; font-size: 0.75rem;">
                        Ver mês atual (Hoje)
                    </a>
                </div>
            @endif

            {{-- FILTROS DE STATUS E CONTRATO --}}
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-secondary small fw-semibold me-1">Filtrar:</span>
                    @php
                        $filters = [
                            'all' => 'Todas',
                            'pending' => 'A Vencer',
                            'overdue' => 'Atrasadas' . ($overdueCount > 0 ? " ({$overdueCount})" : ''),
                            'paid' => 'Pagas',
                        ];
                    @endphp
                    @foreach($filters as $fKey => $fLabel)
                        <a
                            href="{{ route('debts.index', array_filter(['tab' => 'agenda', 'month' => $selectedMonth, 'year' => $selectedYear, 'status' => $fKey, 'debt_id' => $selectedDebtId, 'all_installments' => $viewAllInstallments ? 1 : null])) }}"
                            class="badge rounded-pill text-decoration-none px-3 py-2 fw-semibold"
                            style="{{ $statusFilter === $fKey ? 'background: var(--dz-primary); color: #fff;' : 'background: var(--dz-bg-card-subtle); color: var(--dz-text-secondary); border: 1px solid var(--dz-border);' }}"
                        >
                            {{ $fLabel }}
                        </a>
                    @endforeach
                </div>

                {{-- SELETOR DE CONTRATO E BOTÃO VER TODAS AS PARCELAS --}}
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <label class="text-secondary small fw-semibold d-none d-sm-inline">Contrato:</label>
                    <select class="form-select form-select-sm rounded-pill" style="min-width: 170px; max-width: 230px; font-size: 0.8rem;" onchange="location.href=this.value;">
                        <option value="{{ route('debts.index', ['tab' => 'agenda', 'month' => $selectedMonth, 'year' => $selectedYear, 'status' => $statusFilter]) }}">
                            Todos os Contratos
                        </option>
                        @foreach($debts as $d)
                            <option value="{{ route('debts.index', array_filter(['tab' => 'agenda', 'month' => $selectedMonth, 'year' => $selectedYear, 'status' => $statusFilter, 'debt_id' => $d->id, 'all_installments' => $viewAllInstallments ? 1 : null])) }}" {{ $selectedDebtId === $d->id ? 'selected' : '' }}>
                                {{ $d->name }} ({{ $d->isInstallments() ? ($d->total_installments ?: $d->installments->count()) . 'x' : 'Livre' }})
                            </option>
                        @endforeach
                    </select>

                    @if($selectedDebtId)
                        @if($viewAllInstallments)
                            <a href="{{ route('debts.index', array_filter(['tab' => 'agenda', 'month' => $selectedMonth, 'year' => $selectedYear, 'status' => $statusFilter, 'debt_id' => $selectedDebtId])) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1" style="font-size: 0.8rem;">
                                📅 Ver por Mês
                            </a>
                        @else
                            <a href="{{ route('debts.index', ['tab' => 'agenda', 'month' => $selectedMonth, 'year' => $selectedYear, 'status' => $statusFilter, 'debt_id' => $selectedDebtId, 'all_installments' => 1]) }}" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.8rem;">
                                📋 Ver Todas Juntas
                            </a>
                        @endif
                    @endif
                </div>
            </div>

            {{-- LISTA DE CONTAS / PARCELAS DO MÊS --}}
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background: var(--dz-bg-card);">
                @if($displayedAgendaItems->isEmpty())
                    <div class="p-5 text-center">
                        <div class="fs-1 mb-2">📋</div>
                        <h3 class="h5 fw-bold mb-1" style="color: var(--dz-text-title);">Nenhum vencimento encontrado</h3>
                        <p class="text-secondary small mb-3">Não há contas ou parcelas com vencimento para o filtro e período selecionados.</p>
                        <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalDebtCreate">
                            + Cadastrar Nova Dívida ou Parcela
                        </button>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" style="border-color: var(--dz-border);">
                            <thead style="background: var(--dz-bg-card-subtle); color: var(--dz-text-secondary); font-size: 0.8rem; text-transform: uppercase;">
                                <tr>
                                    <th class="ps-3 ps-md-4 py-3" style="min-width: 85px; width: 125px;">Vencimento</th>
                                    <th class="py-3" style="min-width: 140px;">Dívida / Descrição</th>
                                    <th class="py-3 d-none d-md-table-cell" style="width: 130px;">Categoria</th>
                                    <th class="py-3 text-end" style="min-width: 110px;">Valor</th>
                                    <th class="py-3 text-center" style="width: 110px;">Status</th>
                                    <th class="pe-3 pe-md-4 py-3 text-end" style="min-width: 90px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($displayedAgendaItems as $item)
                                    @php
                                        $badge = $item->statusBadgeInfo($now);
                                        $debt = $item->debt;
                                        $isOverdue = $item->isOverdue($now);
                                        $dueDateObj = $item->due_date ? \Carbon\Carbon::parse($item->due_date) : null;
                                        $totalParcels = $debt->total_installments ?: $debt->installments()->count();
                                    @endphp
                                    <tr style="{{ $isOverdue ? 'background: rgba(239, 68, 68, 0.03);' : '' }}">
                                        {{-- VENCIMENTO --}}
                                        <td class="ps-3 ps-md-4 py-3">
                                            @if($dueDateObj)
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-3 text-center p-1 px-2 border flex-shrink-0" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important; min-width: 46px;">
                                                        <div class="fw-bold" style="font-size: 1rem; color: var(--dz-text-title); line-height: 1;">
                                                            {{ $dueDateObj->format('d') }}
                                                        </div>
                                                        <div class="text-uppercase text-secondary" style="font-size: 0.65rem;">
                                                            {{ $dueDateObj->translatedFormat('M') }}
                                                        </div>
                                                    </div>
                                                    <div class="small text-secondary d-none d-sm-block">
                                                        {{ $dueDateObj->translatedFormat('D') }}
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-secondary small">Sem data</span>
                                            @endif
                                        </td>

                                        {{-- DESCRIÇÃO / CONTRATO --}}
                                        <td class="py-3">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <span class="rounded-circle d-inline-block flex-shrink-0" style="width: 10px; height: 10px; background: {{ $debt->color ?: '#f59e0b' }};"></span>
                                                    <button type="button" class="btn btn-link p-0 text-decoration-none fw-bold js-btn-open-schedule text-start" data-debt-id="{{ $debt->id }}" style="color: var(--dz-text-title); font-size: 0.95rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Ver todas as parcelas deste contrato">
                                                        {{ $debt->name }} <span class="small opacity-50" style="font-size: 0.72rem;">📋</span>
                                                    </button>
                                                    @if($debt->isInstallments())
                                                        @if($item->isExtraordinaryAmortization())
                                                            <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.7rem;">
                                                                ⚡ Aporte
                                                            </span>
                                                        @else
                                                            <span class="badge rounded-pill bg-body-secondary text-secondary" style="font-size: 0.7rem; border: 1px solid var(--dz-border);">
                                                                Parcela {{ $item->installment_number }}/{{ $totalParcels }}
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="badge rounded-pill bg-body-secondary text-secondary" style="font-size: 0.7rem;">
                                                            Amortização Livre
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                                    @if($debt->creditor)
                                                        <span class="small text-secondary" style="font-size: 0.78rem;">
                                                            Credor: {{ $debt->creditor }}
                                                        </span>
                                                    @endif
                                                    @if($debt->defaultCategory)
                                                        <span class="badge rounded-pill d-md-none" style="background: var(--dz-bg-card-subtle); color: var(--dz-text-secondary); border: 1px solid var(--dz-border); font-size: 0.68rem;">
                                                            {{ $debt->defaultCategory->name }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        {{-- CATEGORIA --}}
                                        <td class="py-3 d-none d-md-table-cell">
                                            @if($debt->defaultCategory)
                                                <span class="badge rounded-pill" style="background: var(--dz-bg-card-subtle); color: var(--dz-text-secondary); border: 1px solid var(--dz-border); font-size: 0.75rem;">
                                                    {{ $debt->defaultCategory->name }}
                                                </span>
                                            @else
                                                <span class="text-secondary small">—</span>
                                            @endif
                                        </td>

                                        {{-- VALOR --}}
                                        <td class="py-3 text-end text-nowrap">
                                            @if($item->isExtraordinaryAmortization())
                                                <div class="d-flex flex-column align-items-end">
                                                    <span class="fw-bold text-primary" style="font-size: 0.95rem;">
                                                        R$ {{ number_format((float)($item->paid_amount ?? $item->amount), 2, ',', '.') }}
                                                    </span>
                                                    <div class="small text-secondary" style="font-size: 0.72rem;">
                                                        Aporte avulso
                                                    </div>
                                                </div>
                                            @elseif($item->isPaid())
                                                @php
                                                    $paidAmt = $item->paid_amount !== null ? (float)$item->paid_amount : (float)$item->amount;
                                                    $origAmt = (float)($item->original_amount ?? $item->amount);
                                                    $diff = $origAmt - $paidAmt;
                                                @endphp
                                                @if($item->original_amount !== null && $diff > 0.01)
                                                    {{-- Pago com desconto --}}
                                                    <div class="d-flex flex-column align-items-end">
                                                        <span class="fw-bold text-success" style="font-size: 0.95rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor efetivamente pago">
                                                            R$ {{ number_format($paidAmt, 2, ',', '.') }}
                                                        </span>
                                                        <div class="small text-secondary" style="font-size: 0.72rem;">
                                                            <span class="text-decoration-line-through text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor original do contrato">R$ {{ number_format($origAmt, 2, ',', '.') }}</span>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-1 ms-1 fw-semibold" style="font-size: 0.68rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Desconto obtido">
                                                                -R$ {{ number_format($diff, 2, ',', '.') }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                @elseif($item->original_amount !== null && $diff < -0.01)
                                                    {{-- Pago com juros/acréscimo --}}
                                                    <div class="d-flex flex-column align-items-end">
                                                        <span class="fw-bold text-danger" style="font-size: 0.95rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor efetivamente pago com juros">
                                                            R$ {{ number_format($paidAmt, 2, ',', '.') }}
                                                        </span>
                                                        <div class="small text-secondary" style="font-size: 0.72rem;">
                                                            <span class="text-decoration-line-through text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor original do contrato">R$ {{ number_format($origAmt, 2, ',', '.') }}</span>
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-1 ms-1 fw-semibold" style="font-size: 0.68rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Acréscimo de juros">
                                                                +R$ {{ number_format(abs($diff), 2, ',', '.') }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                @else
                                                    {{-- Pago com valor original --}}
                                                    <div class="d-flex flex-column align-items-end">
                                                        <span class="fw-bold text-secondary text-decoration-line-through" style="font-size: 0.95rem;">
                                                            R$ {{ number_format($paidAmt, 2, ',', '.') }}
                                                        </span>
                                                    </div>
                                                @endif
                                            @else
                                                @php
                                                    $curAmt = (float)$item->amount;
                                                    $origAmt = (float)($item->original_amount ?? $item->amount);
                                                    $diff = $origAmt - $curAmt;
                                                @endphp
                                                @if($item->original_amount !== null && $diff > 0.01)
                                                    {{-- Pendente reduzida por amortização --}}
                                                    <div class="d-flex flex-column align-items-end">
                                                        <span class="fw-bold text-primary" style="font-size: 0.95rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor atual reduzido por amortização">
                                                            R$ {{ number_format($curAmt, 2, ',', '.') }}
                                                        </span>
                                                        <div class="small text-secondary" style="font-size: 0.72rem;">
                                                            <span class="text-decoration-line-through text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor original do contrato">R$ {{ number_format($origAmt, 2, ',', '.') }}</span>
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-1 ms-1 fw-semibold" style="font-size: 0.68rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Redução obtida">
                                                                -R$ {{ number_format($diff, 2, ',', '.') }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="fw-bold" style="color: var(--dz-text-title); font-size: 1rem;">
                                                        R$ {{ number_format($curAmt, 2, ',', '.') }}
                                                    </span>
                                                @endif
                                            @endif
                                        </td>

                                        {{-- STATUS --}}
                                        <td class="py-3 text-center">
                                            <span class="badge rounded-pill px-3 py-1 fw-semibold" style="background: {{ $badge['color'] }}20; color: {{ $badge['color'] }}; font-size: 0.75rem; border: 1px solid {{ $badge['color'] }}40;">
                                                {{ $badge['label'] }}
                                            </span>
                                        </td>

                                        {{-- AÇÕES --}}
                                        <td class="pe-3 pe-md-4 py-3 text-end text-nowrap">
                                            @if($item->isPaid())
                                                <div class="d-flex align-items-center justify-content-end gap-1">
                                                    <span class="small text-success d-none d-lg-inline me-1" style="font-size: 0.75rem;">
                                                        Pago em {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('d/m') : '' }}
                                                    </span>
                                                    @php
                                                        $unpayMsg = $item->is_extraordinary
                                                            ? 'Deseja desfazer esta amortização extraordinária? Isso excluirá o lançamento bancário associado e restaurará o saldo da conta.'
                                                            : 'Deseja desfazer o pagamento desta parcela? Isso excluirá o lançamento bancário associado e restaurará o saldo da conta.';
                                                        $unpayTitle = $item->is_extraordinary ? 'Desfazer Amortização?' : 'Desfazer Pagamento?';
                                                    @endphp
                                                    <form method="POST" action="{{ route('debts.installments.unpay', $item) }}"
                                                        data-confirm="{{ $unpayMsg }}"
                                                        data-confirm-title="{{ $unpayTitle }}"
                                                        data-confirm-accept="Sim, desfazer"
                                                        data-confirm-cancel="Cancelar"
                                                        data-confirm-icon="warning"
                                                        data-confirm-btn-class="btn btn-danger rounded-pill px-4"
                                                        data-confirm-cancel-class="btn btn-outline-secondary rounded-pill px-4">
                                                        @csrf
                                                        <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1" style="font-size: 0.75rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Desfazer pagamento e reabrir parcela">
                                                            Desfazer
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                @php
                                                    $origAmt = (float)($item->original_amount ?? $item->amount);
                                                    $curAmt = (float)$item->amount;
                                                    $hasModifiedAmount = $item->original_amount !== null && abs($origAmt - $curAmt) > 0.01;
                                                @endphp
                                                <div class="d-flex align-items-center justify-content-end gap-1">
                                                    @if($hasModifiedAmount)
                                                        <form method="POST" action="{{ route('debts.installments.reset-amount', $item) }}"
                                                            class="m-0"
                                                            data-confirm="Deseja restaurar a parcela #{{ $item->installment_number }} para o valor original do contrato de R$ {{ number_format($origAmt, 2, ',', '.') }}?"
                                                            data-confirm-title="Restaurar Valor Original?"
                                                            data-confirm-accept="Sim, restaurar"
                                                            data-confirm-cancel="Cancelar"
                                                            data-confirm-icon="question">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                                                            <button type="submit" class="btn btn-sm btn-icon rounded-circle text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Restaurar valor original (R$ {{ number_format($origAmt, 2, ',', '.') }})" aria-label="Restaurar valor original">
                                                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-semibold js-btn-pay-installment"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalPayInstallment"
                                                        data-id="{{ $item->id }}"
                                                        data-debt-name="{{ $debt->name }}"
                                                        data-installment-number="{{ $item->installment_number }}"
                                                        data-total-installments="{{ $totalParcels }}"
                                                        data-amount="{{ number_format((float)$item->amount, 2, ',', '.') }}"
                                                        data-original-amount="{{ number_format((float)($item->original_amount ?? $item->amount), 2, ',', '.') }}"
                                                        data-due-date="{{ $item->due_date ? $item->due_date->format('Y-m-d') : date('Y-m-d') }}"
                                                        data-default-account="{{ $debt->default_account_id }}"
                                                        data-default-category="{{ $debt->default_category_id }}"
                                                        style="background: #10b981; border: none;"
                                                    >
                                                        Pagar
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        @else
            {{-- ========================================================================= --}}
            {{-- VISÃO 2: DÍVIDAS & FINANCIAMENTOS (MACRO / ESTRATÉGICO)                   --}}
            {{-- ========================================================================= --}}

            {{-- KPIS DE DÍVIDAS --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="background: var(--dz-bg-card);">
                        <span class="text-secondary small fw-semibold">Saldo Devedor Restante</span>
                        <div class="h4 mb-0 fw-bold mt-1 text-danger">
                            R$ {{ number_format($totalRemainingDebt, 2, ',', '.') }}
                        </div>
                        <div class="small text-secondary mt-1">
                            Total contratado: R$ {{ number_format($totalOriginalDebt, 2, ',', '.') }}
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="background: var(--dz-bg-card);">
                        <span class="text-secondary small fw-semibold">Total Já Quitado</span>
                        <div class="h4 mb-0 fw-bold mt-1 text-success">
                            R$ {{ number_format($totalPaidDebt, 2, ',', '.') }}
                        </div>
                        <div class="small text-secondary mt-1">
                            Em amortizações e parcelas
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="background: var(--dz-bg-card);">
                        <span class="text-secondary small fw-semibold">Progresso Global de Quitação</span>
                        <div class="h4 mb-0 fw-bold mt-1" style="color: var(--dz-primary);">
                            {{ $totalProgressPct }}%
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $totalProgressPct }}%;" aria-valuenow="{{ $totalProgressPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="background: var(--dz-bg-card);">
                        <span class="text-secondary small fw-semibold">Contratos Ativos</span>
                        <div class="h4 mb-0 fw-bold mt-1" style="color: var(--dz-text-title);">
                            {{ $activeDebts->count() }}
                        </div>
                        <div class="small text-secondary mt-1">
                            {{ $paidOffDebts->count() }} arquivado{{ $paidOffDebts->count() > 1 ? 's' : '' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- GRID DE CARDS DE DÍVIDAS ATIVAS --}}
            <div class="row g-4 mb-5">
                @forelse($activeDebts as $debt)
                    @php
                        $paid = $debt->totalPaid();
                        $remaining = $debt->remainingBalance();
                        $pct = $debt->progressPercentage();
                        $nextInstallment = $debt->nextPendingInstallment();
                        $totalParcels = $debt->total_installments ?: $debt->installments()->count();
                        $paidCount = $debt->paidCount();
                    @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden d-flex flex-column position-relative" style="background: var(--dz-bg-card); border-top: 5px solid {{ $debt->color ?: '#f59e0b' }} !important;">
                            <div class="p-4 flex-grow-1">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div>
                                        <h2 class="h5 fw-bold mb-1" style="color: var(--dz-text-title);">{{ $debt->name }}</h2>
                                        @if($debt->creditor)
                                            <div class="small text-secondary">
                                                Credor: <strong>{{ $debt->creditor }}</strong>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        @if($debt->isInstallments())
                                            <span class="badge rounded-pill bg-body-secondary text-secondary" style="font-size: 0.7rem; border: 1px solid var(--dz-border);">
                                                {{ $totalParcels }}x parcelas
                                            </span>
                                        @else
                                            <span class="badge rounded-pill" style="background: rgba(59, 130, 246, 0.15); color: #2563eb; font-size: 0.7rem;">
                                                Livre / Amortização
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- SALDO DEVEDOR EM DESTAQUE --}}
                                <div class="my-3 p-3 rounded-3" style="background: var(--dz-bg-card-subtle); border: 1px solid var(--dz-border);">
                                    <div class="small text-secondary mb-1">Saldo devedor restante:</div>
                                    <div class="h3 fw-bold mb-0 text-danger">
                                        R$ {{ number_format($remaining, 2, ',', '.') }}
                                    </div>
                                    <div class="d-flex justify-content-between small text-secondary mt-2">
                                        <span>Total: R$ {{ number_format((float)$debt->total_amount, 2, ',', '.') }}</span>
                                        <span class="text-success fw-semibold">Pago: R$ {{ number_format($paid, 2, ',', '.') }}</span>
                                    </div>
                                </div>

                                {{-- BARRA DE PROGRESSO DE QUITAÇÃO --}}
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                                        <span style="color: var(--dz-text-secondary);">Progresso de quitação:</span>
                                        <span style="color: var(--dz-primary);">{{ $pct }}%</span>
                                    </div>
                                    <div class="progress rounded-pill" style="height: 8px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pct }}%;" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>

                                {{-- DETALHES DE PARCELAS OU PRÓXIMO VENCIMENTO --}}
                                <div class="small text-secondary vstack gap-1">
                                    @if($debt->isInstallments())
                                        <div class="d-flex justify-content-between">
                                            <span>Parcelas pagas:</span>
                                            <strong class="text-body">
                                                {{ $paidCount }} de {{ $totalParcels }}
                                            </strong>
                                        </div>
                                        @if($nextInstallment)
                                            <div class="d-flex justify-content-between">
                                                <span>Próximo vencimento:</span>
                                                <strong class="{{ $nextInstallment->isOverdue($now) ? 'text-danger' : 'text-body' }}">
                                                    {{ $nextInstallment->due_date ? $nextInstallment->due_date->format('d/m/Y') : '—' }} (R$ {{ number_format((float)$nextInstallment->amount, 2, ',', '.') }})
                                                </strong>
                                            </div>
                                        @endif
                                    @else
                                        <div class="d-flex justify-content-between">
                                            <span>Amortizações feitas:</span>
                                            <strong class="text-body">{{ $debt->installments()->count() }} pagamentos</strong>
                                        </div>
                                    @endif

                                    @if($debt->defaultAccount)
                                        <div class="d-flex justify-content-between">
                                            <span>Conta sugerida:</span>
                                            <strong class="text-body">{{ $debt->defaultAccount->name }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- BOTÕES DE AÇÃO NO RODAPÉ DO CARD --}}
                            <div class="p-2 p-sm-3 border-top d-flex align-items-center justify-content-between gap-1 gap-sm-2 flex-nowrap" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                <div class="d-flex align-items-center gap-1 gap-sm-2 flex-nowrap">
                                    @if($debt->isFree())
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary rounded-pill px-2 px-sm-3 py-1 fw-semibold text-nowrap js-btn-amortize"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalAmortizeDebt"
                                            data-id="{{ $debt->id }}"
                                            data-debt-type="free"
                                            data-debt-name="{{ $debt->name }}"
                                            data-default-account="{{ $debt->default_account_id }}"
                                            data-default-category="{{ $debt->default_category_id }}"
                                            style="font-size: 0.8rem;"
                                        >
                                            💵 Amortizar
                                        </button>
                                        @if($debt->installments()->count() > 0)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary rounded-pill px-2 px-sm-3 py-1 fw-semibold text-nowrap js-btn-open-schedule"
                                                data-debt-id="{{ $debt->id }}"
                                                style="font-size: 0.8rem;"
                                            >
                                                📋 Ver Histórico
                                            </button>
                                        @endif
                                    @else
                                        @php
                                            $pendingList = $debt->pendingInstallments()->orderBy('installment_number')->get()->map(function($pi) {
                                                return [
                                                    'id' => $pi->id,
                                                    'installment_number' => $pi->installment_number,
                                                    'amount' => (float)$pi->amount,
                                                    'due_date' => $pi->due_date?->format('d/m/Y') ?? '',
                                                ];
                                            })->values();
                                        @endphp
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-success rounded-pill px-2 px-sm-3 py-1 fw-semibold text-nowrap js-btn-amortize"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalAmortizeDebt"
                                            data-id="{{ $debt->id }}"
                                            data-debt-type="installments"
                                            data-debt-name="{{ $debt->name }}"
                                            data-default-account="{{ $debt->default_account_id }}"
                                            data-default-category="{{ $debt->default_category_id }}"
                                            data-installments='@json($pendingList)'
                                            style="font-size: 0.8rem;"
                                        >
                                            💵 Amortizar
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary rounded-pill px-2 px-sm-3 py-1 fw-semibold text-nowrap js-btn-open-schedule"
                                            data-debt-id="{{ $debt->id }}"
                                            style="font-size: 0.8rem;"
                                        >
                                            📋 Ver Parcelas
                                        </button>
                                    @endif
                                </div>

                                <div class="d-flex align-items-center gap-1 flex-shrink-0 ms-auto">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-icon rounded-circle js-btn-edit-debt"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Editar dívida"
                                        aria-label="Editar dívida"
                                        data-id="{{ $debt->id }}"
                                        data-type="{{ $debt->type }}"
                                        data-has-pending="{{ $debt->pendingCount() > 0 ? '1' : '0' }}"
                                        data-name="{{ $debt->name }}"
                                        data-creditor="{{ $debt->creditor }}"
                                        data-color="{{ $debt->color }}"
                                        data-notes="{{ $debt->notes }}"
                                        data-default-account="{{ $debt->default_account_id }}"
                                        data-default-category="{{ $debt->default_category_id }}"
                                        data-user-id="{{ $debt->user_id }}"
                                        data-total-amount="{{ number_format((float)$debt->total_amount, 2, ',', '.') }}"
                                        data-is-active="{{ $debt->is_active ? '1' : '0' }}"
                                        style="color: var(--dz-text-secondary); width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;"
                                    >
                                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>

                                    <form method="POST" action="{{ route('debts.toggle-active', $debt) }}"
                                        class="m-0 d-inline-flex align-items-center"
                                        data-confirm="Deseja arquivar a dívida {{ $debt->name }}? Ela sairá da agenda de parcelas ativas e será movida para a seção de dívidas arquivadas."
                                        data-confirm-title="Arquivar dívida?"
                                        data-confirm-accept="Sim, arquivar"
                                        data-confirm-cancel="Cancelar"
                                        data-confirm-icon="question"
                                        data-confirm-btn-class="btn btn-warning rounded-pill px-4"
                                        data-confirm-cancel-class="btn btn-outline-secondary rounded-pill px-4">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="btn btn-sm btn-icon rounded-circle"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="Arquivar dívida"
                                            aria-label="Arquivar dívida"
                                            data-loading-spinner-only="true"
                                            style="color: var(--dz-text-secondary);">
                                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('debts.destroy', $debt) }}"
                                        class="m-0 d-inline-flex align-items-center"
                                        data-confirm="Tem certeza que deseja excluir esta dívida e todo o histórico de parcelas dela?"
                                        data-confirm-title="Excluir Dívida?"
                                        data-confirm-accept="Sim, excluir"
                                        data-confirm-cancel="Cancelar"
                                        data-confirm-icon="warning"
                                        data-confirm-btn-class="btn btn-danger rounded-pill px-4"
                                        data-confirm-cancel-class="btn btn-outline-secondary rounded-pill px-4">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="btn btn-sm btn-icon rounded-circle text-danger"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="Excluir dívida"
                                            aria-label="Excluir dívida"
                                            data-loading-spinner-only="true">
                                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-4 p-5 text-center" style="background: var(--dz-bg-card);">
                            <div class="fs-1 mb-2">🎉</div>
                            <h3 class="h5 fw-bold mb-1" style="color: var(--dz-text-title);">Nenhuma dívida ou financiamento ativo!</h3>
                            <p class="text-secondary small mb-3">Parabéns! Vocês não possuem nenhuma dívida ativa cadastrada no momento.</p>
                            <div>
                                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalDebtCreate">
                                    + Cadastrar Dívida / Financiamento
                                </button>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- DÍVIDAS ARQUIVADAS --}}
            @if($paidOffDebts->isNotEmpty())
                <div class="card border-0 shadow-sm rounded-4 p-4" style="background: var(--dz-bg-card);">
                    <h3 class="h6 fw-bold mb-3 text-secondary text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                        Dívidas Arquivadas ({{ $paidOffDebts->count() }})
                    </h3>
                    <div class="row g-3">
                        @foreach($paidOffDebts as $pDebt)
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 rounded-3 border d-flex align-items-center justify-content-between gap-2" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                    <div>
                                        <div class="fw-bold text-decoration-line-through text-secondary">{{ $pDebt->name }}</div>
                                        <div class="small text-secondary">Total: R$ {{ number_format((float)$pDebt->total_amount, 2, ',', '.') }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('debts.toggle-active', $pDebt) }}"
                                        class="m-0"
                                        data-confirm="Deseja reativar a dívida {{ $pDebt->name }}? Ela voltará a aparecer nos compromissos ativos e na agenda de parcelas."
                                        data-confirm-title="Reativar dívida?"
                                        data-confirm-accept="Sim, reativar"
                                        data-confirm-cancel="Cancelar"
                                        data-confirm-icon="question"
                                        data-confirm-btn-class="btn btn-primary rounded-pill px-4"
                                        data-confirm-cancel-class="btn btn-outline-secondary rounded-pill px-4">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1" style="font-size: 0.75rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Reativar esta dívida no painel">
                                            Reativar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 1: CADASTRAR NOVA DÍVIDA / COMPROMISSO                              --}}
    {{-- ========================================================================= --}}
    <div class="modal fade" id="modalDebtCreate" tabindex="-1" aria-labelledby="modalDebtCreateLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background: var(--dz-bg-card);">
                <div class="modal-header border-0 pb-0">
                    <h2 class="modal-title h5 fw-bold" id="modalDebtCreateLabel" style="color: var(--dz-text-title);">Nova Dívida / Financiamento</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="{{ route('debts.store') }}">
                    @csrf
                    <div class="modal-body vstack gap-3 pt-3">
                        {{-- TIPO DE DÍVIDA (PARCELADA vs LIVRE) --}}
                        <div>
                            <label class="form-label fw-semibold small text-secondary mb-1">Tipo de Pagamento</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label
                                        id="label_type_installments"
                                        class="debt-type-option d-block p-3 rounded-3 text-start h-100"
                                        style="cursor: pointer; transition: all 0.2s; border: 2px solid var(--dz-primary) !important; background: var(--dz-primary-subtle, rgba(79, 70, 229, 0.08));"
                                        onclick="selectDebtType('installments')"
                                    >
                                        <input type="radio" name="type" id="type_installments" value="installments" checked class="d-none">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="fw-bold" style="color: var(--dz-text-title); font-size: 0.92rem;">📅 Parcelada</span>
                                            <span id="radio_dot_installments" class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 20px; height: 20px; background: var(--dz-primary); color: #fff; font-size: 11px; font-weight: bold;">✓</span>
                                        </div>
                                        <div class="small text-secondary" style="font-size: 0.75rem; line-height: 1.3;">
                                            Carnê, financiamento, boleto parcelado
                                        </div>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <label
                                        id="label_type_free"
                                        class="debt-type-option d-block p-3 rounded-3 text-start h-100"
                                        style="cursor: pointer; transition: all 0.2s; border: 1px solid var(--dz-border) !important; background: var(--dz-bg-card-subtle);"
                                        onclick="selectDebtType('free')"
                                    >
                                        <input type="radio" name="type" id="type_free" value="free" class="d-none">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="fw-bold" style="color: var(--dz-text-title); font-size: 0.92rem;">💵 Livre</span>
                                            <span id="radio_dot_free" class="rounded-circle border d-inline-flex align-items-center justify-content-center" style="width: 20px; height: 20px; border-color: var(--dz-border) !important; color: transparent; font-size: 11px; font-weight: bold;"></span>
                                        </div>
                                        <div class="small text-secondary" style="font-size: 0.75rem; line-height: 1.3;">
                                            Empréstimo familiar, amortização flexível
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- NOME E CREDOR --}}
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label for="create_debt_name" class="form-label fw-semibold small text-secondary mb-1">Nome da Dívida / Compra *</label>
                                <input type="text" class="form-control rounded-3" id="create_debt_name" name="name" placeholder="Ex: Financiamento Argo, Geladeira Magazine Luiza" required>
                            </div>
                            <div class="col-md-5">
                                <label for="create_debt_creditor" class="form-label fw-semibold small text-secondary mb-1">Credor / Beneficiário</label>
                                <input type="text" class="form-control rounded-3" id="create_debt_creditor" name="creditor" placeholder="Ex: Santander, Casas Bahia, Tio Carlos">
                            </div>
                        </div>

                        {{-- VALOR TOTAL --}}
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="create_debt_total_amount" class="form-label fw-semibold small text-secondary mb-1">Valor Total da Dívida (R$) *</label>
                                <input type="text" class="form-control rounded-3 fw-bold @error('total_amount') is-invalid @enderror" id="create_debt_total_amount" name="total_amount" placeholder="0,00" value="{{ old('total_amount') }}" required oninput="recalcInstallmentAmount('total')">
                                @error('total_amount')
                                    <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="create_debt_color" class="form-label fw-semibold small text-secondary mb-1">Cor do Card</label>
                                <input type="color" class="form-control form-control-color w-100 rounded-3" id="create_debt_color" name="color" value="{{ old('color', '#f59e0b') }}" style="height: 38px;">
                            </div>
                        </div>

                        {{-- CAMPOS EXCLUSIVOS DE DÍVIDA PARCELADA --}}
                        <div id="installments_specific_fields" class="p-3 rounded-3 border vstack gap-3" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label for="create_debt_installments" class="form-label fw-semibold small text-secondary mb-1">Qtd. de Parcelas *</label>
                                    <input type="number" class="form-control rounded-3 @error('total_installments') is-invalid @enderror" id="create_debt_installments" name="total_installments" min="1" max="360" value="{{ old('total_installments', 12) }}" oninput="recalcInstallmentAmount('count')">
                                    @error('total_installments')
                                        <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="create_debt_installment_amount" class="form-label fw-semibold small text-secondary mb-1">Valor da Parcela (R$)</label>
                                    <input type="text" class="form-control rounded-3 @error('installment_amount') is-invalid @enderror" id="create_debt_installment_amount" name="installment_amount" placeholder="Calculado automaticamente" value="{{ old('installment_amount') }}" oninput="recalcInstallmentAmount('installment')">
                                    @error('installment_amount')
                                        <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="create_debt_start_date" class="form-label fw-semibold small text-secondary mb-1">1º Vencimento *</label>
                                    <input type="date" class="form-control rounded-3 @error('start_date') is-invalid @enderror" id="create_debt_start_date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}">
                                    @error('start_date')
                                        <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="small text-secondary">
                                💡 As parcelas serão geradas automaticamente mês a mês e exibidas na <strong>Agenda de Vencimentos</strong>.
                            </div>
                        </div>

                        {{-- CONTA E CATEGORIA SUGERIDAS --}}
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="create_debt_account" class="form-label fw-semibold small text-secondary mb-1">Conta Bancária Sugerida</label>
                                <select class="form-select rounded-3" id="create_debt_account" name="default_account_id">
                                    <option value="">(Selecionar ao pagar)</option>
                                    @foreach($regularAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="create_debt_category" class="form-label fw-semibold small text-secondary mb-1">Categoria de Despesa Padrão</label>
                                <select class="form-select rounded-3" id="create_debt_category" name="default_category_id">
                                    <option value="">(Selecionar ao pagar)</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- MEMBRO / RESPONSÁVEL (OPCIONAL) --}}
                        @if($members->count() > 1)
                            <div>
                                <label for="create_debt_user_id" class="form-label fw-semibold small text-secondary mb-1">Responsável</label>
                                <select class="form-select rounded-3" id="create_debt_user_id" name="user_id">
                                    <option value="">Dívida do Casal (Ambos)</option>
                                    @foreach($members as $m)
                                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- OBSERVAÇÕES --}}
                        <div>
                            <label for="create_debt_notes" class="form-label fw-semibold small text-secondary mb-1">Observações / Nº de Contrato</label>
                            <textarea class="form-control rounded-3" id="create_debt_notes" name="notes" rows="2" placeholder="Informações extras, link do carnê, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Cadastrar Dívida</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 2: PAGAR PARCELA AGENDADA                                           --}}
    {{-- ========================================================================= --}}
    <div class="modal fade" id="modalPayInstallment" tabindex="-1" aria-labelledby="modalPayInstallmentLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background: var(--dz-bg-card);">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h2 class="modal-title h5 fw-bold mb-0" id="modalPayInstallmentLabel" style="color: var(--dz-text-title);">Pagar Parcela</h2>
                        <div id="pay_installment_subtitle" class="small text-secondary mt-1"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="formPayInstallment" method="POST" action="">
                    @csrf
                    <input type="hidden" name="redirect_to" id="pay_redirect_to" value="">
                    <input type="hidden" name="schedule_debt_id" id="pay_schedule_debt_id" value="">
                    <div class="modal-body vstack gap-3 pt-3">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label for="pay_amount" class="form-label fw-semibold small text-secondary mb-0">Valor Efetivamente Pago (R$) *</label>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-primary fw-semibold" id="btn_pay_restore_original" style="display: none; font-size: 0.75rem;">
                                    Restaurar original
                                </button>
                            </div>
                            <input type="text" class="form-control rounded-3 fw-bold fs-5 text-success" id="pay_amount" name="amount" required>
                            <div class="small text-secondary mt-1">Você pode ajustar caso tenha ocorrido juros ou desconto.</div>
                        </div>

                        <div>
                            <label for="pay_date" class="form-label fw-semibold small text-secondary mb-1">Data do Pagamento *</label>
                            <input type="date" class="form-control rounded-3" id="pay_date" name="paid_at" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div>
                            <label for="pay_account_id" class="form-label fw-semibold small text-secondary mb-1">Conta de Onde Saiu o Dinheiro *</label>
                            <select class="form-select rounded-3" id="pay_account_id" name="account_id" required>
                                <option value="">Selecione a conta corrente...</option>
                                @foreach($regularAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }} (Saldo: R$ {{ number_format((float)$acc->balance, 2, ',', '.') }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="pay_category_id" class="form-label fw-semibold small text-secondary mb-1">Categoria de Despesa</label>
                            <select class="form-select rounded-3" id="pay_category_id" name="category_id">
                                <option value="">(Padrão da dívida)</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="pay_notes" class="form-label fw-semibold small text-secondary mb-1">Observações da Baixa</label>
                            <input type="text" class="form-control rounded-3" id="pay_notes" name="notes" placeholder="Ex: Pago com Pix, comprovante salvo">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold" style="background: #10b981; border: none;">Confirmar Pagamento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 3: AMORTIZAR DÍVIDA (LIVRE OU PARCELADA)                            --}}
    {{-- ========================================================================= --}}
    <div class="modal fade" id="modalAmortizeDebt" tabindex="-1" aria-labelledby="modalAmortizeDebtLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background: var(--dz-bg-card);">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h2 class="modal-title h5 fw-bold mb-0" id="modalAmortizeDebtLabel" style="color: var(--dz-text-title);">Amortizar Dívida</h2>
                        <div id="amortize_debt_subtitle" class="small text-secondary mt-1"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="formAmortizeDebt" method="POST" action="">
                    @csrf
                    <input type="hidden" name="redirect_to" id="amortize_redirect_to" value="">
                    <input type="hidden" name="schedule_debt_id" id="amortize_schedule_debt_id" value="">
                    <input type="hidden" name="strategy" id="amortize_strategy" value="free">

                    <div class="modal-body vstack gap-3 pt-3">
                        {{-- BLOCO SELETOR DE MODALIDADE (APENAS DÍVIDA PARCELADA) --}}
                        <div id="amortize_installments_strategies" style="display: none;">
                            <label class="form-label fw-semibold small text-secondary mb-2">Modalidade de Amortização</label>
                            <div class="row g-2 mb-3">
                                <div class="col-12 col-md-4">
                                    <button type="button" class="btn btn-primary w-100 p-2 text-start rounded-3 h-100 js-amortize-tab-btn active" data-strategy="reduce_term">
                                        <div class="fw-bold small">⏱️ Reduzir Prazo</div>
                                        <div class="small opacity-75" style="font-size: 0.75rem;">Quitar parcelas do final do contrato</div>
                                    </button>
                                </div>
                                <div class="col-12 col-md-4">
                                    <button type="button" class="btn btn-outline-primary w-100 p-2 text-start rounded-3 h-100 js-amortize-tab-btn" data-strategy="reduce_amount">
                                        <div class="fw-bold small">📉 Reduzir Parcela</div>
                                        <div class="small opacity-75" style="font-size: 0.75rem;">Diminuir o valor das parcelas futuras</div>
                                    </button>
                                </div>
                                <div class="col-12 col-md-4">
                                    <button type="button" class="btn btn-outline-primary w-100 p-2 text-start rounded-3 h-100 js-amortize-tab-btn" data-strategy="select_installments">
                                        <div class="fw-bold small">☑️ Escolher Parcelas</div>
                                        <div class="small opacity-75" style="font-size: 0.75rem;">Adiantar parcelas específicas</div>
                                    </button>
                                </div>
                            </div>

                            {{-- PAINEL 1: REDUZIR PRAZO --}}
                            <div id="amortize_panel_reduce_term" class="p-3 rounded-3 border mb-2" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                <div class="row g-3 align-items-center">
                                    <div class="col-sm-6">
                                        <label for="amortize_term_count" class="form-label fw-semibold small text-secondary mb-1">Quantas parcelas do final quitar?</label>
                                        <select class="form-select rounded-3" id="amortize_term_count" name="term_installments_count">
                                            <!-- Dinâmico via JS -->
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="small text-secondary mb-1">Parcelas selecionadas do final:</div>
                                        <div id="amortize_term_preview_tags" class="d-flex flex-wrap gap-1 mb-1"></div>
                                        <div class="small text-muted" id="amortize_term_original_total"></div>
                                    </div>
                                </div>

                                {{-- AJUSTE RESIDUAL (OPCIONAL) --}}
                                <div class="mt-3 pt-3 border-top" style="border-color: var(--dz-border) !important;">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="amortize_toggle_residual">
                                        <label class="form-check-label small fw-semibold" for="amortize_toggle_residual">
                                            Ajustar valor da parcela anterior (residual)?
                                        </label>
                                    </div>
                                    <div id="amortize_residual_fields" style="display: none;">
                                        <div class="row g-2">
                                            <div class="col-sm-6">
                                                <label class="small text-secondary mb-1">Parcela residual:</label>
                                                <select class="form-select form-select-sm rounded-3" id="amortize_residual_inst_select" name="residual_installment_id"></select>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="small text-secondary mb-1">Novo valor que esta parcela ficará (R$):</label>
                                                <input type="text" class="form-control form-control-sm rounded-3" id="amortize_residual_amount" name="residual_new_amount" placeholder="0,00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- PAINEL 2: REDUZIR PARCELA --}}
                            <div id="amortize_panel_reduce_amount" class="p-3 rounded-3 border mb-2" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important; display: none;">
                                <div class="small text-secondary mb-2" id="amortize_reduce_amount_info"></div>
                                <div class="row g-3">
                                    <div class="col-sm-12">
                                        <label for="amortize_new_installment_amount" class="form-label fw-semibold small text-secondary mb-1">
                                            Novo valor em que as parcelas restantes ficarão (R$)
                                        </label>
                                        <input type="text" class="form-control rounded-3 fw-bold text-success" id="amortize_new_installment_amount" name="new_installment_amount" placeholder="0,00">
                                        <div class="form-text small" id="amortize_new_amount_hint">
                                            Calculado automaticamente com base no valor amortizado, mas você pode editar para definir o valor exato desejado.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- PAINEL 3: ESCOLHER PARCELAS --}}
                            <div id="amortize_panel_select_installments" class="p-3 rounded-3 border mb-2" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important; display: none;">
                                <div class="small text-secondary mb-2">
                                    Selecione as parcelas que deseja adiantar. Você pode alterar o valor a pagar de cada uma (ex: abatimento/desconto) e se haverá valor residual:
                                </div>
                                <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                                    <table class="table table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                        <thead>
                                            <tr class="text-secondary">
                                                <th style="width: 40px;"></th>
                                                <th>Parcela</th>
                                                <th>Vencimento</th>
                                                <th>Valor Original</th>
                                                <th style="width: 140px;">Valor a Pagar</th>
                                                <th style="width: 140px;">Saldo Restante</th>
                                            </tr>
                                        </thead>
                                        <tbody id="amortize_select_table_body">
                                            <!-- Preenchido via JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- DADOS DE PAGAMENTO (COMUNS A TODAS AS MODALIDADES) --}}
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="amortize_amount" class="form-label fw-semibold small text-secondary mb-1">Valor Total a Amortizar / Pagar (R$) *</label>
                                <input type="text" class="form-control rounded-3 fw-bold fs-5 text-primary" id="amortize_amount" name="amount" placeholder="0,00" required>
                                <div id="amortize_amount_discount_badge" class="small mt-1 text-success fw-semibold" style="display: none;"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="amortize_date" class="form-label fw-semibold small text-secondary mb-1">Data do Pagamento *</label>
                                <input type="date" class="form-control rounded-3" id="amortize_date" name="paid_at" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="amortize_account_id" class="form-label fw-semibold small text-secondary mb-1">Conta de Onde Saiu o Dinheiro *</label>
                                <select class="form-select rounded-3" id="amortize_account_id" name="account_id" required>
                                    <option value="">Selecione a conta corrente...</option>
                                    @foreach($regularAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }} (Saldo: R$ {{ number_format((float)$acc->balance, 2, ',', '.') }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="amortize_category_id" class="form-label fw-semibold small text-secondary mb-1">Categoria de Despesa</label>
                                <select class="form-select rounded-3" id="amortize_category_id" name="category_id">
                                    <option value="">(Padrão da dívida)</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="amortize_notes" class="form-label fw-semibold small text-secondary mb-1">Observações</label>
                            <input type="text" class="form-control rounded-3" id="amortize_notes" name="notes" placeholder="Ex: Amortização extraordinária com desconto">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">Confirmar Amortização</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 4: EDITAR METADADOS DA DÍVIDA                                       --}}
    {{-- ========================================================================= --}}
    <div class="modal fade" id="modalDebtEdit" tabindex="-1" aria-labelledby="modalDebtEditLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background: var(--dz-bg-card);">
                <div class="modal-header border-0 pb-0">
                    <h2 class="modal-title h5 fw-bold" id="modalDebtEditLabel" style="color: var(--dz-text-title);">Editar Dados da Dívida</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="formDebtEdit" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-body vstack gap-3 pt-3">
                        <div>
                            <label for="edit_debt_name" class="form-label fw-semibold small text-secondary mb-1">Nome da Dívida *</label>
                            <input type="text" class="form-control rounded-3" id="edit_debt_name" name="name" required>
                        </div>

                        <div>
                            <label for="edit_debt_creditor" class="form-label fw-semibold small text-secondary mb-1">Credor / Beneficiário</label>
                            <input type="text" class="form-control rounded-3" id="edit_debt_creditor" name="creditor">
                        </div>

                        <div class="row g-2">
                            <div class="col-md-7">
                                <label for="edit_debt_total_amount" class="form-label fw-semibold small text-secondary mb-1">Valor Total da Dívida (R$)</label>
                                <input type="text" class="form-control rounded-3 fw-bold" id="edit_debt_total_amount" name="total_amount" placeholder="0,00">
                                <div class="form-text" style="font-size: 0.72rem;">Não pode ser menor que o total já pago e comprometido em parcelas.</div>
                            </div>
                            <div class="col-md-5">
                                <label for="edit_debt_color" class="form-label fw-semibold small text-secondary mb-1">Cor</label>
                                <input type="color" class="form-control form-control-color w-100 rounded-3" id="edit_debt_color" name="color" style="height: 38px;">
                            </div>
                        </div>

                        {{-- BANNER DE ATALHO PARA AJUSTAR PARCELAS RESTANTES --}}
                        <div class="p-2 p-sm-3 rounded-3 border d-flex align-items-center justify-content-between gap-2 flex-wrap" id="edit_debt_adjust_banner" style="display: none; background: var(--dz-primary-subtle, rgba(79, 70, 229, 0.08)); border-color: rgba(79, 70, 229, 0.25) !important;">
                            <div>
                                <div class="fw-semibold small" style="color: var(--dz-primary);">Ajustar parcelas restantes?</div>
                                <div class="text-secondary small" style="font-size: 0.72rem;">Altere valores, vencimentos ou diminua a quantidade restante.</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 text-nowrap fw-semibold" id="btn_edit_debt_trigger_adjust" style="font-size: 0.78rem;">
                                Ajustar Parcelas ↗
                            </button>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="edit_debt_account" class="form-label fw-semibold small text-secondary mb-1">Conta Sugerida</label>
                                <select class="form-select rounded-3" id="edit_debt_account" name="default_account_id">
                                    <option value="">(Nenhuma)</option>
                                    @foreach($regularAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_debt_category" class="form-label fw-semibold small text-secondary mb-1">Categoria de Despesa Padrão</label>
                                <select class="form-select rounded-3" id="edit_debt_category" name="default_category_id">
                                    <option value="">(Nenhuma)</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        @if($members->count() > 1)
                            <div>
                                <label for="edit_debt_user_id" class="form-label fw-semibold small text-secondary mb-1">Responsável</label>
                                <select class="form-select rounded-3" id="edit_debt_user_id" name="user_id">
                                    <option value="">Dívida do Casal (Ambos)</option>
                                    @foreach($members as $m)
                                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div>
                            <label for="edit_debt_notes" class="form-label fw-semibold small text-secondary mb-1">Observações</label>
                            <textarea class="form-control rounded-3" id="edit_debt_notes" name="notes" rows="2"></textarea>
                        </div>

                        <div class="form-check form-switch pt-1">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_debt_is_active" value="1">
                            <label class="form-check-label fw-semibold" for="edit_debt_is_active">Dívida ativa (em andamento)</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 5: CRONOGRAMA COMPLETO DE PARCELAS DA DÍVIDA                       --}}
    {{-- ========================================================================= --}}
    <div class="modal fade" id="modalDebtSchedule" tabindex="-1" aria-labelledby="modalDebtScheduleLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background: var(--dz-bg-card);">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <span id="schedule_debt_dot" class="rounded-circle d-inline-block flex-shrink-0" style="width: 14px; height: 14px; background: #f59e0b;"></span>
                        <div>
                            <h2 class="modal-title h5 fw-bold mb-0" id="modalDebtScheduleLabel" style="color: var(--dz-text-title);">Todas as Parcelas</h2>
                            <div id="schedule_debt_subtitle" class="small text-secondary mt-1"></div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-3">
                    {{-- CARDS DE RESUMO DO CONTRATO --}}
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="p-2 p-sm-3 rounded-3 border h-100" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                <div class="small text-secondary">Total Contratado</div>
                                <div class="fw-bold fs-6 mt-1" id="schedule_stat_total" style="color: var(--dz-text-title);">R$ 0,00</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 p-sm-3 rounded-3 border h-100" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                <div class="small text-secondary">Já Quitado</div>
                                <div class="fw-bold fs-6 mt-1 text-success" id="schedule_stat_paid">R$ 0,00</div>
                                <div class="small text-secondary" id="schedule_stat_paid_count" style="font-size: 0.75rem;"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 p-sm-3 rounded-3 border h-100" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                <div class="small text-secondary">Saldo Devedor</div>
                                <div class="fw-bold fs-6 mt-1 text-danger" id="schedule_stat_remaining">R$ 0,00</div>
                                <div class="small text-secondary" id="schedule_stat_remaining_count" style="font-size: 0.75rem;"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 p-sm-3 rounded-3 border h-100" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                <div class="d-flex justify-content-between small text-secondary">
                                    <span>Progresso</span>
                                    <strong id="schedule_stat_progress">0%</strong>
                                </div>
                                <div class="progress rounded-pill mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" id="schedule_stat_progressbar" role="progressbar" style="width: 0%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- FILTROS RÁPIDOS NA MODAL --}}
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">
                        <div class="d-flex align-items-center gap-1 overflow-x-auto pb-1" id="schedule_filter_group" style="max-width: 100%; white-space: nowrap;">
                            <button type="button" class="btn btn-sm btn-primary js-schedule-filter-btn rounded-pill px-3 py-1 active" data-filter="all">Todas (<span id="schedule_count_all">0</span>)</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-schedule-filter-btn rounded-pill px-3 py-1" data-filter="pending">Pendentes (<span id="schedule_count_pending">0</span>)</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-schedule-filter-btn rounded-pill px-3 py-1" data-filter="overdue">Atrasadas (<span id="schedule_count_overdue">0</span>)</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-schedule-filter-btn rounded-pill px-3 py-1" data-filter="paid">Pagas (<span id="schedule_count_paid">0</span>)</button>
                        </div>
                        <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                            <div id="schedule_reset_all_wrapper" style="display: none;">
                                <form id="form_schedule_reset_all" method="POST" action=""
                                    data-confirm="Deseja restaurar todas as parcelas pendentes com valor alterado para o valor original de contrato?"
                                    data-confirm-title="Restaurar Todas as Parcelas?"
                                    data-confirm-accept="Sim, restaurar todas"
                                    data-confirm-cancel="Cancelar"
                                    data-confirm-icon="question">
                                    @csrf
                                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                                    <input type="hidden" name="schedule_debt_id" id="schedule_reset_all_debt_id" value="">
                                    <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Restaura todas as parcelas pendentes para os valores originais do contrato">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        <span>Resetar valores originais</span>
                                    </button>
                                </form>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1" id="schedule_btn_add_inst" style="font-size: 0.78rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Adicionar uma nova parcela avulsa ao cronograma">
                                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                <span>Nova Parcela</span>
                            </button>
                            <div id="schedule_bulk_edit_wrapper" style="display: none;">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1" id="schedule_btn_bulk_edit" style="font-size: 0.78rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Alterar valor e vencimento de todas as parcelas restantes">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    <span>Ajustar parcelas restantes</span>
                                </button>
                            </div>
                            <a id="schedule_btn_view_page" href="#" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1" style="font-size: 0.78rem;">
                                Abrir na Agenda ↗
                            </a>
                        </div>
                    </div>

                    {{-- TABELA DE PARCELAS --}}
                    <div class="table-responsive rounded-3 border" style="border-color: var(--dz-border) !important;">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="sticky-top" style="background: var(--dz-bg-card-subtle); color: var(--dz-text-secondary); font-size: 0.78rem; text-transform: uppercase;">
                                <tr>
                                    <th class="ps-3 py-2" style="width: 55px;">#</th>
                                    <th class="py-2" style="min-width: 105px;">Vencimento</th>
                                    <th class="py-2 text-end" style="min-width: 110px;">Valor</th>
                                    <th class="py-2 text-center" style="width: 95px;">Status</th>
                                    <th class="pe-3 py-2 text-end text-nowrap" style="min-width: 175px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="schedule_table_body">
                                <!-- Preenchido via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 d-flex justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold" id="schedule_btn_amortize">
                            💵 Amortizar esta Dívida
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold" id="schedule_btn_bulk_edit_footer">
                            ✏️ Ajustar Parcelas Restantes
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold" id="schedule_btn_add_inst_footer">
                            ➕ Nova Parcela
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-4" data-bs-dismiss="modal">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 6: AJUSTAR TODAS AS PARCELAS RESTANTES (EM LOTE)                    --}}
    {{-- ========================================================================= --}}
    <div class="modal fade" id="modalAdjustRemainingInstallments" tabindex="-1" aria-labelledby="modalAdjustRemainingInstallmentsLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background: var(--dz-bg-card);">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h2 class="modal-title h5 fw-bold mb-0" id="modalAdjustRemainingInstallmentsLabel" style="color: var(--dz-text-title);">Ajustar Parcelas Restantes</h2>
                        <div id="adjust_debt_subtitle" class="small text-secondary mt-1"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="formAdjustRemainingInstallments" method="POST" action="">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                    <input type="hidden" name="schedule_debt_id" id="adjust_schedule_debt_id" value="">
                    <input type="hidden" name="reduce_installments_count" id="adjust_reduce_installments_count" value="">
                    <input type="hidden" name="adjust_last_installment" id="adjust_adjust_last_installment" value="0">

                    <div class="modal-body vstack gap-3 pt-3">
                        {{-- CARDS DE RESUMO --}}
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="p-2 p-sm-3 rounded-3 border h-100" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                    <div class="small text-secondary text-truncate" style="font-size: 0.72rem;" title="Parcelas Restantes">Parcelas</div>
                                    <div class="fw-bold mt-1 text-body text-truncate" style="font-size: 0.95rem;" id="adjust_stat_pending_count">0</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 p-sm-3 rounded-3 border h-100" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                    <div class="small text-secondary text-truncate" style="font-size: 0.72rem;" title="Saldo Devedor Restante">Saldo Restante</div>
                                    <div class="fw-bold mt-1 text-danger text-truncate" style="font-size: 0.95rem;" id="adjust_stat_remaining">R$ 0,00</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 p-sm-3 rounded-3 border h-100" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                    <div class="small text-secondary text-truncate" style="font-size: 0.72rem;" title="Total Contratado da Dívida">Total Dívida</div>
                                    <div class="fw-bold mt-1 text-body text-truncate" style="font-size: 0.95rem;" id="adjust_stat_total">R$ 0,00</div>
                                </div>
                            </div>
                        </div>

                        {{-- QUANTIDADE DE PARCELAS E NOVO VALOR --}}
                        <div class="row g-2">
                            <div class="col-md-5">
                                <div class="p-3 rounded-3 border h-100" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                    <label for="adjust_target_installments_count" class="form-label fw-semibold small text-secondary mb-1">
                                        Qtd. de Parcelas Restantes *
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control form-control-lg rounded-start-3 fw-bold" id="adjust_target_installments_count" min="1" max="100" placeholder="Qtd">
                                        <span class="input-group-text rounded-end-3 text-secondary small">parcelas</span>
                                    </div>
                                    <div class="form-text mt-1" style="font-size: 0.72rem;">
                                        Altere para diminuir a quantidade de parcelas.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="p-3 rounded-3 border h-100" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                                    <label for="adjust_new_installment_amount" class="form-label fw-semibold small text-secondary mb-1">
                                        Novo Valor da Parcela (R$) *
                                    </label>
                                    <input type="text" class="form-control form-control-lg rounded-3 fw-bold" id="adjust_new_installment_amount" name="new_installment_amount" placeholder="0,00" required>
                                    <div class="form-text mt-1" style="font-size: 0.72rem;">
                                        Valor aplicado às parcelas restantes.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- VENCIMENTOS --}}
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="adjust_due_day" class="form-label fw-semibold small text-secondary mb-1">Novo Dia de Vencimento (Fixo)</label>
                                <input type="number" class="form-control rounded-3" id="adjust_due_day" name="due_day" min="1" max="31" placeholder="Ex: 10, 15, 25">
                                <div class="form-text" style="font-size: 0.72rem;">Define o dia fixo do mês em que cada parcela vencerá.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="adjust_first_due_date" class="form-label fw-semibold small text-secondary mb-1">Data da 1ª Parcela Restante</label>
                                <input type="date" class="form-control rounded-3" id="adjust_first_due_date" name="first_due_date">
                                <div class="form-text" style="font-size: 0.72rem;">Recalcula a sequência mês a mês a partir desta data.</div>
                            </div>
                        </div>

                        {{-- OPÇÃO: INFLUENCIAR OU NÃO O SALDO TOTAL DA DÍVIDA --}}
                        <div class="p-3 rounded-3 border" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border) !important;">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="adjust_update_total_amount" name="update_total_amount" value="1">
                                <label class="form-check-label fw-semibold" for="adjust_update_total_amount" style="color: var(--dz-text-title);">
                                    Atualizar o valor total da dívida para a nova soma das parcelas
                                </label>
                            </div>
                            <div class="small text-secondary" id="adjust_update_total_help" style="font-size: 0.75rem;">
                                <strong>Desmarcado (Padrão):</strong> Mantém o valor total da dívida contratada inalterado. As parcelas abertas não podem exceder o saldo restante.
                            </div>
                        </div>

                        {{-- PAINEL DE FEEDBACK / ALERTA DE EXCESSO / SUGESTÃO INTELIGENTE DE REDUÇÃO --}}
                        <div id="adjust_feedback_panel" style="display: none;">
                            {{-- Preenchido dinamicamente via JS --}}
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" id="adjust_btn_submit">
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 7: ADICIONAR PARCELA AVULSA AO CRONOGRAMA                           --}}
    {{-- ========================================================================= --}}
    <div class="modal fade" id="modalAddSingleInstallment" tabindex="-1" aria-labelledby="modalAddSingleInstallmentLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background: var(--dz-bg-card);">
                <form id="formAddSingleInstallment" method="POST" action="">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                    <input type="hidden" name="schedule_debt_id" id="add_inst_schedule_debt_id" value="">
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <h2 class="modal-title h5 fw-bold mb-0" id="modalAddSingleInstallmentLabel" style="color: var(--dz-text-title);">Nova Parcela</h2>
                            <div id="add_inst_debt_subtitle" class="small text-secondary mt-1"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar" onclick="if(activeScheduleDebtId) openDebtSchedule(activeScheduleDebtId);"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label for="add_inst_amount" class="form-label small fw-semibold" style="color: var(--dz-text-title);">Valor da Parcela <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text border-end-0 fw-semibold text-secondary" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border);">R$</span>
                                    <input type="text" class="form-control border-start-0 ps-1 fw-bold" id="add_inst_amount" name="amount" placeholder="0,00" required style="background: var(--dz-bg-card); border-color: var(--dz-border); color: var(--dz-text-title);">
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="add_inst_due_date" class="form-label small fw-semibold" style="color: var(--dz-text-title);">Data de Vencimento <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="add_inst_due_date" name="due_date" required style="background: var(--dz-bg-card); border-color: var(--dz-border); color: var(--dz-text-title);">
                            </div>
                            <div class="col-12">
                                <label for="add_inst_notes" class="form-label small fw-semibold" style="color: var(--dz-text-title);">Observações / Descrição</label>
                                <input type="text" class="form-control" id="add_inst_notes" name="notes" placeholder="Ex: Parcela renegociada, taxa adicional, etc." style="background: var(--dz-bg-card); border-color: var(--dz-border); color: var(--dz-text-title);">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="add_inst_update_total" name="update_total_amount" value="1">
                                    <label class="form-check-label small fw-semibold" for="add_inst_update_total" style="color: var(--dz-text-title);">
                                        Atualizar o valor total da dívida
                                    </label>
                                </div>
                                <div class="small text-secondary mt-1" style="font-size: 0.75rem;">
                                    Se a nova parcela fizer a soma ultrapassar o saldo contratado, marque esta opção para estender o teto total da dívida.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal" onclick="if(activeScheduleDebtId) openDebtSchedule(activeScheduleDebtId);">Cancelar</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 d-inline-flex align-items-center gap-1" id="btn_submit_add_inst" style="background: #10b981; border: none;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                            <span>Adicionar Parcela</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 8: EDITAR PARCELA AVULSA                                            --}}
    {{-- ========================================================================= --}}
    <div class="modal fade" id="modalEditSingleInstallment" tabindex="-1" aria-labelledby="modalEditSingleInstallmentLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background: var(--dz-bg-card);">
                <form id="formEditSingleInstallment" method="POST" action="">
                    @csrf
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                    <input type="hidden" name="schedule_debt_id" id="edit_inst_schedule_debt_id" value="">
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <h2 class="modal-title h5 fw-bold mb-0" id="modalEditSingleInstallmentLabel" style="color: var(--dz-text-title);">Editar Parcela</h2>
                            <div id="edit_inst_debt_subtitle" class="small text-secondary mt-1"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar" onclick="if(activeScheduleDebtId) openDebtSchedule(activeScheduleDebtId);"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label for="edit_inst_amount" class="form-label small fw-semibold" style="color: var(--dz-text-title);">Valor da Parcela <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text border-end-0 fw-semibold text-secondary" style="background: var(--dz-bg-card-subtle); border-color: var(--dz-border);">R$</span>
                                    <input type="text" class="form-control border-start-0 ps-1 fw-bold" id="edit_inst_amount" name="amount" placeholder="0,00" required style="background: var(--dz-bg-card); border-color: var(--dz-border); color: var(--dz-text-title);">
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="edit_inst_due_date" class="form-label small fw-semibold" style="color: var(--dz-text-title);">Data de Vencimento <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_inst_due_date" name="due_date" required style="background: var(--dz-bg-card); border-color: var(--dz-border); color: var(--dz-text-title);">
                            </div>
                            <div class="col-12">
                                <label for="edit_inst_notes" class="form-label small fw-semibold" style="color: var(--dz-text-title);">Observações / Descrição</label>
                                <input type="text" class="form-control" id="edit_inst_notes" name="notes" placeholder="Ex: Negociação pontual de juros, etc." style="background: var(--dz-bg-card); border-color: var(--dz-border); color: var(--dz-text-title);">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="edit_inst_update_total" name="update_total_amount" value="1">
                                    <label class="form-check-label small fw-semibold" for="edit_inst_update_total" style="color: var(--dz-text-title);">
                                        Atualizar o valor total da dívida
                                    </label>
                                </div>
                                <div class="small text-secondary mt-1" style="font-size: 0.75rem;">
                                    Se o novo valor fizer a soma ultrapassar o saldo contratado, marque esta opção para estender o teto total da dívida.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal" onclick="if(activeScheduleDebtId) openDebtSchedule(activeScheduleDebtId);">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" id="btn_submit_edit_inst">
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    @php
        $debtsScheduleMap = $debts->map(function($d) {
            $now = \Carbon\Carbon::now();
            $paidCount = $d->paidCount();
            $totalCount = $d->total_installments ?: $d->installments->count();
            $extraCount = $d->extraordinaryAmortizationsCount();
            $pendingCount = $d->pendingCount();
            return [
                'id' => $d->id,
                'name' => $d->name,
                'creditor' => $d->creditor,
                'color' => $d->color ?: '#f59e0b',
                'type' => $d->type,
                'total_amount' => (float)$d->total_amount,
                'total_amount_formatted' => number_format((float)$d->total_amount, 2, ',', '.'),
                'remaining' => $d->remainingBalance(),
                'remaining_formatted' => number_format($d->remainingBalance(), 2, ',', '.'),
                'paid' => $d->totalPaid(),
                'paid_formatted' => number_format($d->totalPaid(), 2, ',', '.'),
                'progress' => $d->progressPercentage(),
                'default_account' => $d->default_account_id,
                'default_category' => $d->default_category_id,
                'paid_count' => $paidCount,
                'total_count' => $totalCount,
                'extra_count' => $extraCount,
                'pending_count' => $pendingCount,
                'due_day' => $d->due_day,
                'installments' => $d->installments->sortBy('installment_number')->map(function($inst) use ($d, $now) {
                    $isOverdue = $inst->isOverdue($now);
                    $isExtraordinary = $inst->isExtraordinaryAmortization();
                    return [
                        'id' => $inst->id,
                        'installment_number' => $inst->installment_number,
                        'is_extraordinary' => $isExtraordinary,
                        'amount' => (float)$inst->amount,
                        'amount_formatted' => number_format((float)$inst->amount, 2, ',', '.'),
                        'original_amount' => (float)($inst->original_amount ?? $inst->amount),
                        'original_amount_formatted' => number_format((float)($inst->original_amount ?? $inst->amount), 2, ',', '.'),
                        'paid_amount' => $inst->paid_amount !== null ? (float)$inst->paid_amount : null,
                        'paid_amount_formatted' => $inst->paid_amount !== null ? number_format((float)$inst->paid_amount, 2, ',', '.') : null,
                        'due_date' => $inst->due_date ? $inst->due_date->format('d/m/Y') : '—',
                        'due_date_raw' => $inst->due_date ? $inst->due_date->format('Y-m-d') : '',
                        'day_week' => $inst->due_date ? $inst->due_date->translatedFormat('D') : '',
                        'status' => $inst->status,
                        'is_overdue' => $isOverdue,
                        'paid_at' => $inst->paid_at ? \Carbon\Carbon::parse($inst->paid_at)->format('d/m/Y') : null,
                        'account_name' => $inst->transaction?->accountModel?->name ?? null,
                        'notes' => $inst->notes,
                        'debt_name' => $d->name,
                        'total_parcels' => $d->total_installments ?: $d->installments->count(),
                        'default_account' => $d->default_account_id,
                        'default_category' => $d->default_category_id,
                    ];
                })->values(),
            ];
        })->keyBy('id');
    @endphp
    <script>
        const debtsScheduleData = @json($debtsScheduleMap);
        let activeScheduleDebtId = null;
        let activeScheduleFilter = 'all';

        function selectDebtType(type) {
            const radioInstallments = document.getElementById('type_installments');
            const radioFree = document.getElementById('type_free');
            const labelInstallments = document.getElementById('label_type_installments');
            const labelFree = document.getElementById('label_type_free');
            const dotInstallments = document.getElementById('radio_dot_installments');
            const dotFree = document.getElementById('radio_dot_free');
            const fields = document.getElementById('installments_specific_fields');

            if (type === 'installments') {
                if (radioInstallments) radioInstallments.checked = true;
                if (labelInstallments) {
                    labelInstallments.style.setProperty('border', '2px solid var(--dz-primary)', 'important');
                    labelInstallments.style.background = 'var(--dz-primary-subtle, rgba(79, 70, 229, 0.08))';
                }
                if (dotInstallments) {
                    dotInstallments.style.background = 'var(--dz-primary)';
                    dotInstallments.style.borderColor = 'var(--dz-primary)';
                    dotInstallments.style.color = '#fff';
                    dotInstallments.textContent = '✓';
                }

                if (labelFree) {
                    labelFree.style.setProperty('border', '1px solid var(--dz-border)', 'important');
                    labelFree.style.background = 'var(--dz-bg-card-subtle)';
                }
                if (dotFree) {
                    dotFree.style.background = 'transparent';
                    dotFree.style.borderColor = 'var(--dz-border)';
                    dotFree.style.color = 'transparent';
                    dotFree.textContent = '';
                }

                if (fields) fields.classList.remove('d-none');
            } else {
                if (radioFree) radioFree.checked = true;
                if (labelFree) {
                    labelFree.style.setProperty('border', '2px solid var(--dz-primary)', 'important');
                    labelFree.style.background = 'var(--dz-primary-subtle, rgba(79, 70, 229, 0.08))';
                }
                if (dotFree) {
                    dotFree.style.background = 'var(--dz-primary)';
                    dotFree.style.borderColor = 'var(--dz-primary)';
                    dotFree.style.color = '#fff';
                    dotFree.textContent = '✓';
                }

                if (labelInstallments) {
                    labelInstallments.style.setProperty('border', '1px solid var(--dz-border)', 'important');
                    labelInstallments.style.background = 'var(--dz-bg-card-subtle)';
                }
                if (dotInstallments) {
                    dotInstallments.style.background = 'transparent';
                    dotInstallments.style.borderColor = 'var(--dz-border)';
                    dotInstallments.style.color = 'transparent';
                    dotInstallments.textContent = '';
                }

                if (fields) fields.classList.add('d-none');
            }
        }

        function toggleDebtTypeFields(type) {
            selectDebtType(type);
        }

        function parseMoneyNumber(val) {
            if (!val) return 0;
            let clean = val.toString().trim().replace(/[^\d,\.]/g, '');
            if (!clean) return 0;

            const hasComma = clean.includes(',');
            const hasDot = clean.includes('.');

            if (hasComma && hasDot) {
                const lastComma = clean.lastIndexOf(',');
                const lastDot = clean.lastIndexOf('.');
                if (lastComma > lastDot) {
                    clean = clean.replace(/\./g, '').replace(',', '.');
                } else {
                    clean = clean.replace(/,/g, '');
                }
            } else if (hasComma) {
                clean = clean.replace(',', '.');
            } else if (hasDot) {
                const dotCount = (clean.match(/\./g) || []).length;
                if (dotCount > 1) {
                    clean = clean.replace(/\./g, '');
                } else {
                    if (/^\d{1,3}\.\d{3}$/.test(clean)) {
                        clean = clean.replace('.', '');
                    }
                }
            }

            const num = parseFloat(clean);
            return isNaN(num) ? 0 : num;
        }

        function formatMoneyNumber(num) {
            const val = parseFloat(num);
            return (isNaN(val) ? 0 : val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function recalcInstallmentAmount(origin = 'total') {
            const totalInput = document.getElementById('create_debt_total_amount');
            const installmentsInput = document.getElementById('create_debt_installments');
            const installmentAmountInput = document.getElementById('create_debt_installment_amount');
            if (!totalInput || !installmentsInput || !installmentAmountInput) return;

            const count = parseInt(installmentsInput.value, 10) || 1;

            if (origin === 'installment') {
                // Usuário digitou o valor unitário da parcela -> sugere valor total apenas se estiver vazio
                const instVal = parseMoneyNumber(installmentAmountInput.value);
                const currentTotal = parseMoneyNumber(totalInput.value);
                if (instVal > 0 && count > 0 && currentTotal <= 0) {
                    const total = instVal * count;
                    totalInput.value = formatMoneyNumber(total);
                }
            } else if (origin === 'total') {
                // Usuário digitou o valor total -> se parcela estiver vazia, sugere a divisão
                const totalVal = parseMoneyNumber(totalInput.value);
                const currentInst = parseMoneyNumber(installmentAmountInput.value);
                if (totalVal > 0 && count > 0 && currentInst <= 0) {
                    const perMonth = totalVal / count;
                    installmentAmountInput.value = formatMoneyNumber(perMonth);
                }
            } else if (origin === 'count') {
                // Usuário alterou a quantidade de parcelas -> calcula o valor sugerido da parcela
                const totalVal = parseMoneyNumber(totalInput.value);
                if (totalVal > 0 && count > 0) {
                    const perMonth = totalVal / count;
                    installmentAmountInput.value = formatMoneyNumber(perMonth);
                }
            }
        }

        let currentInstallments = [];
        let currentStrategy = 'reduce_term';

        function setAmortizeStrategy(strat) {
            currentStrategy = strat;
            const stratInput = document.getElementById('amortize_strategy');
            if (stratInput) stratInput.value = strat;

            document.querySelectorAll('.js-amortize-tab-btn').forEach(btn => {
                if (btn.dataset.strategy === strat) {
                    btn.classList.add('active');
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('active');
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                }
            });

            const panelTerm = document.getElementById('amortize_panel_reduce_term');
            const panelAmount = document.getElementById('amortize_panel_reduce_amount');
            const panelSelect = document.getElementById('amortize_panel_select_installments');

            if (panelTerm) panelTerm.style.display = (strat === 'reduce_term') ? 'block' : 'none';
            if (panelAmount) panelAmount.style.display = (strat === 'reduce_amount') ? 'block' : 'none';
            if (panelSelect) panelSelect.style.display = (strat === 'select_installments') ? 'block' : 'none';

            if (strat === 'reduce_term') {
                updateReduceTerm();
            } else if (strat === 'reduce_amount') {
                updateReduceAmount();
            } else if (strat === 'select_installments') {
                updateSelectInstallmentsTotal();
            }
        }

        function updateReduceTerm() {
            const countSelect = document.getElementById('amortize_term_count');
            if (!countSelect || currentInstallments.length === 0) return;

            const k = parseInt(countSelect.value, 10) || 1;
            const toSettle = currentInstallments.slice(-k);
            const originalSum = toSettle.reduce((sum, item) => sum + item.amount, 0);

            const tagsWrap = document.getElementById('amortize_term_preview_tags');
            if (tagsWrap) {
                tagsWrap.innerHTML = toSettle.map(item =>
                    `<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1">#${item.installment_number} (R$ ${formatMoneyNumber(item.amount)})</span>`
                ).join('');
            }

            const origTotalEl = document.getElementById('amortize_term_original_total');
            if (origTotalEl) {
                origTotalEl.textContent = `Total original destas parcelas: R$ ${formatMoneyNumber(originalSum)}`;
            }

            const amountInput = document.getElementById('amortize_amount');
            if (amountInput) {
                amountInput.value = formatMoneyNumber(originalSum);
            }

            const badge = document.getElementById('amortize_amount_discount_badge');
            if (badge) badge.style.display = 'none';

            const resSelect = document.getElementById('amortize_residual_inst_select');
            const toggleRes = document.getElementById('amortize_toggle_residual');
            const resWrap = document.getElementById('amortize_residual_fields');
            const resAmtInput = document.getElementById('amortize_residual_amount');
            if (resSelect && toggleRes) {
                if (currentInstallments.length > k) {
                    toggleRes.disabled = false;
                    const residualInst = currentInstallments[currentInstallments.length - k - 1];
                    resSelect.innerHTML = `<option value="${residualInst.id}">Parcela #${residualInst.installment_number} (Venc: ${residualInst.due_date} • Atual: R$ ${formatMoneyNumber(residualInst.amount)})</option>`;
                    if (toggleRes.checked && resAmtInput && (!resAmtInput.value || resAmtInput.value === '0,00')) {
                        resAmtInput.value = formatMoneyNumber(residualInst.amount);
                    }
                } else {
                    toggleRes.checked = false;
                    toggleRes.disabled = true;
                    if (resWrap) resWrap.style.display = 'none';
                    resSelect.innerHTML = '';
                    if (resAmtInput) resAmtInput.value = '';
                }
            }
        }

        function updateReduceAmount() {
            const infoEl = document.getElementById('amortize_reduce_amount_info');
            const totalPending = currentInstallments.reduce((sum, item) => sum + item.amount, 0);
            const count = currentInstallments.length;
            const avg = count > 0 ? (totalPending / count) : 0;

            if (infoEl) {
                infoEl.innerHTML = `Contrato possui <strong>${count}</strong> parcelas pendentes somando <strong>R$ ${formatMoneyNumber(totalPending)}</strong> (média de R$ ${formatMoneyNumber(avg)} / parcela).`;
            }

            const amountInput = document.getElementById('amortize_amount');
            const newAmountInput = document.getElementById('amortize_new_installment_amount');
            const aporte = parseMoneyNumber(amountInput?.value || '0');

            if (count > 0 && newAmountInput) {
                const remainingDebt = Math.max(0, totalPending - aporte);
                const calcNewPerMonth = remainingDebt / count;
                newAmountInput.value = formatMoneyNumber(calcNewPerMonth);
            }
        }

        function updateFromNewInstallmentAmount() {
            const totalPending = currentInstallments.reduce((sum, item) => sum + item.amount, 0);
            const count = currentInstallments.length;
            const amountInput = document.getElementById('amortize_amount');
            const newAmountInput = document.getElementById('amortize_new_installment_amount');
            if (!newAmountInput || count === 0) return;

            const targetInstallment = parseMoneyNumber(newAmountInput.value || '0');
            const newRemainingDebt = targetInstallment * count;
            const requiredAporte = Math.max(0, totalPending - newRemainingDebt);
            if (amountInput) {
                amountInput.value = formatMoneyNumber(requiredAporte);
            }
        }

        function renderSelectInstallments() {
            const tbody = document.getElementById('amortize_select_table_body');
            if (!tbody) return;

            if (currentInstallments.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhuma parcela pendente encontrada.</td></tr>';
                return;
            }

            tbody.innerHTML = currentInstallments.map((item, idx) => `
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input js-select-inst-check" data-idx="${idx}" name="selected_installments[${idx}][id]" value="${item.id}">
                    </td>
                    <td class="fw-semibold">#${item.installment_number}</td>
                    <td>${item.due_date}</td>
                    <td>R$ ${formatMoneyNumber(item.amount)}</td>
                    <td>
                        <input type="text" class="form-control form-control-sm js-select-inst-paid" name="selected_installments[${idx}][paid_amount]" value="${formatMoneyNumber(item.amount)}" disabled style="max-width: 120px;">
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <div class="form-check form-check-inline m-0">
                                <input type="checkbox" class="form-check-input js-select-inst-partial" data-idx="${idx}" name="selected_installments[${idx}][is_fully_paid]" value="0" disabled>
                                <label class="form-check-label text-muted" style="font-size: 0.72rem;">Parcial</label>
                            </div>
                            <div class="js-select-inst-residual-wrap" style="display: none;">
                                <input type="text" class="form-control form-control-sm js-select-inst-remaining" name="selected_installments[${idx}][new_remaining_amount]" placeholder="Novo saldo (R$)" style="max-width: 110px;">
                            </div>
                        </div>
                    </td>
                </tr>
            `).join('');

            tbody.querySelectorAll('.js-select-inst-check').forEach(chk => {
                chk.addEventListener('change', function() {
                    const row = this.closest('tr');
                    const paidInput = row.querySelector('.js-select-inst-paid');
                    const partialChk = row.querySelector('.js-select-inst-partial');
                    const residualWrap = row.querySelector('.js-select-inst-residual-wrap');
                    const isChecked = this.checked;

                    paidInput.disabled = !isChecked;
                    partialChk.disabled = !isChecked;
                    if (!isChecked) {
                        partialChk.checked = false;
                        residualWrap.style.display = 'none';
                    }
                    updateSelectInstallmentsTotal();
                });
            });

            tbody.querySelectorAll('.js-select-inst-paid').forEach(inp => {
                inp.addEventListener('input', function() {
                    updateSelectInstallmentsTotal();
                });
                inp.addEventListener('blur', function() {
                    const val = parseMoneyNumber(this.value);
                    if (val >= 0) {
                        this.value = formatMoneyNumber(val);
                    }
                });
            });

            tbody.querySelectorAll('.js-select-inst-remaining').forEach(inp => {
                inp.addEventListener('blur', function() {
                    const val = parseMoneyNumber(this.value);
                    if (val >= 0) {
                        this.value = formatMoneyNumber(val);
                    }
                });
            });

            tbody.querySelectorAll('.js-select-inst-partial').forEach(partialChk => {
                partialChk.addEventListener('change', function() {
                    const row = this.closest('tr');
                    const residualWrap = row.querySelector('.js-select-inst-residual-wrap');
                    residualWrap.style.display = this.checked ? 'block' : 'none';
                });
            });
        }

        function updateSelectInstallmentsTotal() {
            const tbody = document.getElementById('amortize_select_table_body');
            if (!tbody) return;

            let total = 0;
            tbody.querySelectorAll('tr').forEach(row => {
                const chk = row.querySelector('.js-select-inst-check');
                if (chk && chk.checked) {
                    const paidInput = row.querySelector('.js-select-inst-paid');
                    total += parseMoneyNumber(paidInput?.value || '0');
                }
            });

            const amountInput = document.getElementById('amortize_amount');
            if (amountInput) {
                amountInput.value = formatMoneyNumber(total);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Modal de Pagamento de Parcela
            document.querySelectorAll('.js-btn-pay-installment').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const debtName = this.dataset.debtName;
                    const installmentNum = this.dataset.installmentNumber;
                    const totalInstallments = this.dataset.totalInstallments;
                    const amount = this.dataset.amount;
                    const dueDate = this.dataset.dueDate;
                    const defaultAccount = this.dataset.defaultAccount;
                    const defaultCategory = this.dataset.defaultCategory;

                    const origAmount = this.dataset.originalAmount;

                    const form = document.getElementById('formPayInstallment');
                    form.action = `/dividas/parcelas/${id}/pagamento`;

                    const redirectInput = document.getElementById('pay_redirect_to');
                    if (redirectInput) redirectInput.value = window.location.href;

                    const scheduleDebtInput = document.getElementById('pay_schedule_debt_id');
                    if (scheduleDebtInput) scheduleDebtInput.value = '';

                    let subText = `${debtName} • Parcela ${installmentNum} de ${totalInstallments}`;
                    if (origAmount && origAmount !== amount) {
                        subText += ` (Original: R$ ${origAmount})`;
                    }
                    document.getElementById('pay_installment_subtitle').textContent = subText;
                    document.getElementById('pay_amount').value = amount;
                    document.getElementById('pay_date').value = dueDate || '{{ date('Y-m-d') }}';

                    if (defaultAccount) {
                        document.getElementById('pay_account_id').value = defaultAccount;
                    }
                    if (defaultCategory) {
                        document.getElementById('pay_category_id').value = defaultCategory;
                    }

                    const btnRestore = document.getElementById('btn_pay_restore_original');
                    if (btnRestore) {
                        if (origAmount && origAmount !== amount) {
                            btnRestore.style.display = 'inline-block';
                            btnRestore.textContent = `Restaurar original (R$ ${origAmount})`;
                            btnRestore.onclick = function() {
                                document.getElementById('pay_amount').value = origAmount;
                            };
                        } else {
                            btnRestore.style.display = 'none';
                        }
                    }
                });
            });

            // Função de configuração do Modal de Amortização
            function setupAmortizeModal(btn) {
                if (!btn) return;
                const btnEl = (typeof btn.closest === 'function') ? (btn.closest('.js-btn-amortize') || btn) : btn;
                const id = btnEl.dataset.id;
                const debtName = btnEl.dataset.debtName;
                const debtType = btnEl.dataset.debtType || 'free';
                const defaultAccount = btnEl.dataset.defaultAccount;
                const defaultCategory = btnEl.dataset.defaultCategory;

                const form = document.getElementById('formAmortizeDebt');
                if (form) form.action = `/dividas/${id}/amortize`;

                const redirectInput = document.getElementById('amortize_redirect_to');
                if (redirectInput) redirectInput.value = window.location.href;
                const scheduleDebtInput = document.getElementById('amortize_schedule_debt_id');
                if (scheduleDebtInput) scheduleDebtInput.value = '';

                const subtitleEl = document.getElementById('amortize_debt_subtitle');
                if (subtitleEl) subtitleEl.textContent = `Dívida: ${debtName}`;

                if (defaultAccount) {
                    const accInput = document.getElementById('amortize_account_id');
                    if (accInput) accInput.value = defaultAccount;
                }
                if (defaultCategory) {
                    const catInput = document.getElementById('amortize_category_id');
                    if (catInput) catInput.value = defaultCategory;
                }

                const stratWrapper = document.getElementById('amortize_installments_strategies');
                const amountBadge = document.getElementById('amortize_amount_discount_badge');
                if (amountBadge) amountBadge.style.display = 'none';

                if (debtType === 'installments') {
                    if (stratWrapper) stratWrapper.style.display = 'block';
                    try {
                        currentInstallments = JSON.parse(btnEl.dataset.installments || '[]');
                    } catch (e) {
                        console.error('Erro ao ler parcelas:', e);
                        currentInstallments = [];
                    }

                    const termCountSelect = document.getElementById('amortize_term_count');
                    if (termCountSelect) {
                        termCountSelect.innerHTML = '';
                        for (let i = 1; i <= currentInstallments.length; i++) {
                            const opt = document.createElement('option');
                            opt.value = i;
                            opt.textContent = `${i} parcela${i > 1 ? 's' : ''} do final`;
                            termCountSelect.appendChild(opt);
                        }
                        termCountSelect.onchange = updateReduceTerm;
                    }

                    renderSelectInstallments();
                    setAmortizeStrategy('reduce_term');
                } else {
                    if (stratWrapper) stratWrapper.style.display = 'none';
                    const stratInput = document.getElementById('amortize_strategy');
                    if (stratInput) stratInput.value = 'free';
                    const amountInput = document.getElementById('amortize_amount');
                    if (amountInput) amountInput.value = '';
                    currentInstallments = [];
                }
            }

            // Abre o modal de amortização para a dívida selecionada no cronograma
            function openAmortizeForDebt(debtId) {
                const debt = debtsScheduleData[debtId];
                if (!debt) return;

                const existingBtn = document.querySelector(`.js-btn-amortize[data-id="${debtId}"]`);
                if (existingBtn) {
                    setupAmortizeModal(existingBtn);
                } else {
                    const pendingInsts = (debt.installments || [])
                        .filter(i => i.status === 'pending')
                        .map(i => ({
                            id: i.id,
                            installment_number: i.installment_number,
                            amount: i.amount,
                            due_date: i.due_date,
                        }));

                    const mockBtn = {
                        dataset: {
                            id: debt.id,
                            debtName: debt.name,
                            debtType: debt.type,
                            remaining: debt.remaining,
                            defaultAccount: debt.default_account || '',
                            defaultCategory: debt.default_category || '',
                            installments: JSON.stringify(pendingInsts),
                        }
                    };
                    setupAmortizeModal(mockBtn);
                }

                const scheduleDebtInput = document.getElementById('amortize_schedule_debt_id');
                if (scheduleDebtInput) scheduleDebtInput.value = debtId;

                const modalAmortizeEl = document.getElementById('modalAmortizeDebt');
                if (modalAmortizeEl && typeof bootstrap !== 'undefined') {
                    new bootstrap.Modal(modalAmortizeEl).show();
                }
            }

            // Abre o modal de cronograma completo de parcelas
            function openDebtSchedule(debtId) {
                activeScheduleDebtId = debtId;
                activeScheduleFilter = 'all';
                const debt = debtsScheduleData[debtId];
                if (!debt) return;

                // Header
                const dot = document.getElementById('schedule_debt_dot');
                if (dot) dot.style.background = debt.color || '#f59e0b';

                const title = document.getElementById('modalDebtScheduleLabel');
                if (title) title.textContent = debt.name;

                const subtitle = document.getElementById('schedule_debt_subtitle');
                if (subtitle) {
                    const parts = [];
                    if (debt.creditor) parts.push(`Credor: ${debt.creditor}`);
                    if (debt.type === 'installments') {
                        parts.push(`${debt.total_count} parcelas`);
                    } else {
                        parts.push('Amortização Livre');
                    }
                    subtitle.textContent = parts.join(' • ');
                }

                // KPIs
                const elTotal = document.getElementById('schedule_stat_total');
                if (elTotal) elTotal.textContent = `R$ ${debt.total_amount_formatted}`;

                const elPaid = document.getElementById('schedule_stat_paid');
                if (elPaid) elPaid.textContent = `R$ ${debt.paid_formatted}`;

                const elPaidCount = document.getElementById('schedule_stat_paid_count');
                if (elPaidCount) {
                    elPaidCount.textContent = `${debt.paid_count} de ${debt.total_count} pagas`;
                }

                const elRemaining = document.getElementById('schedule_stat_remaining');
                if (elRemaining) elRemaining.textContent = `R$ ${debt.remaining_formatted}`;

                const elRemainingCount = document.getElementById('schedule_stat_remaining_count');
                if (elRemainingCount) elRemainingCount.textContent = `${debt.pending_count} parcelas pendentes`;

                const elProgress = document.getElementById('schedule_stat_progress');
                if (elProgress) elProgress.textContent = `${debt.progress}%`;

                const elProgressBar = document.getElementById('schedule_stat_progressbar');
                if (elProgressBar) elProgressBar.style.width = `${debt.progress}%`;

                // Contadores nos botões de filtro
                const countAll = debt.installments.length;
                const countPending = debt.installments.filter(i => i.status === 'pending').length;
                const countOverdue = debt.installments.filter(i => i.is_overdue).length;
                const countPaid = debt.installments.filter(i => i.status === 'paid').length;

                const elCountAll = document.getElementById('schedule_count_all');
                if (elCountAll) elCountAll.textContent = countAll;
                const elCountPending = document.getElementById('schedule_count_pending');
                if (elCountPending) elCountPending.textContent = countPending;
                const elCountOverdue = document.getElementById('schedule_count_overdue');
                if (elCountOverdue) elCountOverdue.textContent = countOverdue;
                const elCountPaid = document.getElementById('schedule_count_paid');
                if (elCountPaid) elCountPaid.textContent = countPaid;

                // Resetar botões de filtro
                document.querySelectorAll('.js-schedule-filter-btn').forEach(btn => {
                    if (btn.dataset.filter === 'all') {
                        btn.classList.add('active', 'btn-primary');
                        btn.classList.remove('btn-outline-secondary');
                    } else {
                        btn.classList.remove('active', 'btn-primary');
                        btn.classList.add('btn-outline-secondary');
                    }
                });

                // Link Abrir na Agenda
                const btnViewPage = document.getElementById('schedule_btn_view_page');
                if (btnViewPage) {
                    btnViewPage.href = `/dividas?view_mode=agenda&debt_id=${debt.id}&all_installments=1`;
                }

                // Botão de amortizar no rodapé
                const btnAmortize = document.getElementById('schedule_btn_amortize');
                if (btnAmortize) {
                    btnAmortize.style.display = (debt.remaining <= 0) ? 'none' : 'inline-block';
                }

                // Botão de resetar todas as parcelas ao valor original
                const modifiedPendingCount = (debt.installments || []).filter(i => i.status === 'pending' && i.original_amount !== null && Math.abs(i.original_amount - i.amount) > 0.01).length;
                const resetAllWrapper = document.getElementById('schedule_reset_all_wrapper');
                if (resetAllWrapper) {
                    if (modifiedPendingCount > 0) {
                        resetAllWrapper.style.display = 'block';
                        const formReset = document.getElementById('form_schedule_reset_all');
                        if (formReset) formReset.action = `/dividas/${debt.id}/resetar-parcelas`;
                        const resetDebtInput = document.getElementById('schedule_reset_all_debt_id');
                        if (resetDebtInput) resetDebtInput.value = debt.id;
                    } else {
                        resetAllWrapper.style.display = 'none';
                    }
                }

                // Botão de ajustar parcelas restantes (em lote)
                const bulkEditWrapper = document.getElementById('schedule_bulk_edit_wrapper');
                const bulkEditFooterBtn = document.getElementById('schedule_btn_bulk_edit_footer');
                const hasPending = (debt.pending_count || 0) > 0 && debt.type === 'installments';

                if (bulkEditWrapper) {
                    bulkEditWrapper.style.display = hasPending ? 'block' : 'none';
                }
                if (bulkEditFooterBtn) {
                    bulkEditFooterBtn.style.display = hasPending ? 'inline-block' : 'none';
                }

                renderScheduleTable();

                const scheduleModalEl = document.getElementById('modalDebtSchedule');
                if (scheduleModalEl && typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getInstance(scheduleModalEl) || new bootstrap.Modal(scheduleModalEl);
                    modal.show();
                }
            }
            window.openDebtSchedule = openDebtSchedule;

            // Abre o modal de ajuste de parcelas restantes
            function openAdjustRemainingModal(debtId) {
                activeScheduleDebtId = debtId;
                const debt = debtsScheduleData[debtId];
                if (!debt) return;

                // Fecha o modal de cronograma se aberto
                const scheduleModalEl = document.getElementById('modalDebtSchedule');
                if (scheduleModalEl && typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getInstance(scheduleModalEl);
                    if (modal) modal.hide();
                }

                const form = document.getElementById('formAdjustRemainingInstallments');
                if (form) form.action = `/dividas/${debt.id}/ajustar-parcelas-restantes`;

                const scheduleDebtInput = document.getElementById('adjust_schedule_debt_id');
                if (scheduleDebtInput) scheduleDebtInput.value = debt.id;

                const subtitle = document.getElementById('adjust_debt_subtitle');
                if (subtitle) {
                    subtitle.textContent = `Dívida: ${debt.name} • ${debt.pending_count} parcelas pendentes • Saldo restante: R$ ${debt.remaining_formatted}`;
                }

                const statCount = document.getElementById('adjust_stat_pending_count');
                if (statCount) statCount.textContent = debt.pending_count;

                const statRem = document.getElementById('adjust_stat_remaining');
                if (statRem) statRem.textContent = `R$ ${debt.remaining_formatted}`;

                const statTotal = document.getElementById('adjust_stat_total');
                if (statTotal) statTotal.textContent = `R$ ${debt.total_amount_formatted}`;

                const targetCountInput = document.getElementById('adjust_target_installments_count');
                if (targetCountInput) {
                    targetCountInput.value = debt.pending_count;
                    targetCountInput.max = debt.pending_count;
                }

                // Próxima parcela pendente para preencher o valor sugerido e vencimento
                const nextPending = (debt.installments || []).find(i => i.status === 'pending');
                const amountInput = document.getElementById('adjust_new_installment_amount');
                if (amountInput) {
                    amountInput.value = nextPending ? nextPending.amount_formatted : '';
                }

                const dueDayInput = document.getElementById('adjust_due_day');
                if (dueDayInput) {
                    dueDayInput.value = debt.due_day || '';
                }

                const firstDateInput = document.getElementById('adjust_first_due_date');
                if (firstDateInput) {
                    firstDateInput.value = nextPending && nextPending.due_date_raw ? nextPending.due_date_raw : '';
                }

                const updateCheck = document.getElementById('adjust_update_total_amount');
                if (updateCheck) {
                    updateCheck.checked = false;
                }

                const reduceInput = document.getElementById('adjust_reduce_installments_count');
                if (reduceInput) {
                    reduceInput.value = '';
                }

                const adjustLastInput = document.getElementById('adjust_adjust_last_installment');
                if (adjustLastInput) {
                    adjustLastInput.value = '0';
                }

                updateAdjustInstallmentsPreview();

                const adjustModalEl = document.getElementById('modalAdjustRemainingInstallments');
                if (adjustModalEl && typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getOrCreateInstance(adjustModalEl);
                    modal.show();
                }
            }
            window.openAdjustRemainingModal = openAdjustRemainingModal;

            // Abre o modal de adicionar parcela avulsa
            function openAddSingleInstallmentModal(debtId) {
                const targetDebtId = debtId || activeScheduleDebtId;
                if (!targetDebtId) return;

                const debt = debtsScheduleData[targetDebtId];
                if (!debt) return;

                activeScheduleDebtId = targetDebtId;

                // Fecha modal do cronograma se aberto
                const scheduleModalEl = document.getElementById('modalDebtSchedule');
                if (scheduleModalEl && typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getInstance(scheduleModalEl);
                    if (modal) modal.hide();
                }

                const form = document.getElementById('formAddSingleInstallment');
                if (form) form.action = `/dividas/${debt.id}/parcelas`;

                const scheduleDebtInput = document.getElementById('add_inst_schedule_debt_id');
                if (scheduleDebtInput) scheduleDebtInput.value = debt.id;

                const subtitle = document.getElementById('add_inst_debt_subtitle');
                if (subtitle) {
                    subtitle.textContent = `${debt.name} • Saldo restante: R$ ${debt.remaining_formatted}`;
                }

                // Sugere data e valor: 1 mês após a última parcela existente
                const installments = debt.installments || [];
                let defaultAmount = '';
                let defaultDate = '';

                if (installments.length > 0) {
                    const lastInst = installments[installments.length - 1];
                    defaultAmount = lastInst.amount_formatted || '';
                    if (lastInst.due_date_raw) {
                        const parts = lastInst.due_date_raw.split('-');
                        if (parts.length === 3) {
                            const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                            d.setMonth(d.getMonth() + 1);
                            const yr = d.getFullYear();
                            const mo = String(d.getMonth() + 1).padStart(2, '0');
                            const day = String(d.getDate()).padStart(2, '0');
                            defaultDate = `${yr}-${mo}-${day}`;
                        }
                    }
                }

                if (!defaultAmount && debt.total_amount && debt.total_count) {
                    defaultAmount = formatMoneyNumber(debt.total_amount / debt.total_count);
                }

                const amountEl = document.getElementById('add_inst_amount');
                if (amountEl) amountEl.value = defaultAmount;

                const dateEl = document.getElementById('add_inst_due_date');
                if (dateEl) dateEl.value = defaultDate;

                const notesEl = document.getElementById('add_inst_notes');
                if (notesEl) notesEl.value = '';

                const checkEl = document.getElementById('add_inst_update_total');
                if (checkEl) checkEl.checked = false;

                const addModalEl = document.getElementById('modalAddSingleInstallment');
                if (addModalEl && typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getOrCreateInstance(addModalEl);
                    modal.show();
                }
            }
            window.openAddSingleInstallmentModal = openAddSingleInstallmentModal;

            // Abre o modal de editar parcela avulsa
            function openEditSingleInstallmentModal(instId, amount, dueDate, notes, number) {
                if (!activeScheduleDebtId) return;
                const debt = debtsScheduleData[activeScheduleDebtId];
                if (!debt) return;

                // Fecha modal do cronograma se aberto
                const scheduleModalEl = document.getElementById('modalDebtSchedule');
                if (scheduleModalEl && typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getInstance(scheduleModalEl);
                    if (modal) modal.hide();
                }

                const form = document.getElementById('formEditSingleInstallment');
                if (form) form.action = `/dividas/parcelas/${instId}`;

                const scheduleDebtInput = document.getElementById('edit_inst_schedule_debt_id');
                if (scheduleDebtInput) scheduleDebtInput.value = debt.id;

                const title = document.getElementById('modalEditSingleInstallmentLabel');
                if (title) title.textContent = `Editar Parcela #${number}`;

                const subtitle = document.getElementById('edit_inst_debt_subtitle');
                if (subtitle) {
                    subtitle.textContent = `${debt.name} • Saldo restante: R$ ${debt.remaining_formatted}`;
                }

                const amountEl = document.getElementById('edit_inst_amount');
                if (amountEl) amountEl.value = amount || '';

                const dateEl = document.getElementById('edit_inst_due_date');
                if (dateEl) dateEl.value = dueDate || '';

                const notesEl = document.getElementById('edit_inst_notes');
                if (notesEl) notesEl.value = notes || '';

                const checkEl = document.getElementById('edit_inst_update_total');
                if (checkEl) checkEl.checked = false;

                const editModalEl = document.getElementById('modalEditSingleInstallment');
                if (editModalEl && typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getOrCreateInstance(editModalEl);
                    modal.show();
                }
            }
            window.openEditSingleInstallmentModal = openEditSingleInstallmentModal;

            function updateAdjustInstallmentsPreview(source = null) {
                if (!activeScheduleDebtId) return;
                const debt = debtsScheduleData[activeScheduleDebtId];
                if (!debt) return;

                const amountInput = document.getElementById('adjust_new_installment_amount');
                const targetCountInput = document.getElementById('adjust_target_installments_count');
                const updateCheck = document.getElementById('adjust_update_total_amount');
                const reduceInput = document.getElementById('adjust_reduce_installments_count');
                const adjustLastInput = document.getElementById('adjust_adjust_last_installment');
                const feedbackPanel = document.getElementById('adjust_feedback_panel');
                const submitBtn = document.getElementById('adjust_btn_submit');
                const helpText = document.getElementById('adjust_update_total_help');

                if (!amountInput || !feedbackPanel) return;

                const pendingCount = debt.pending_count || 0;
                const remainingBalance = debt.remaining || 0;
                const isUpdateTotal = updateCheck ? updateCheck.checked : false;

                let targetCount = parseInt(targetCountInput?.value, 10);
                if (isNaN(targetCount) || targetCount < 1) targetCount = pendingCount;
                if (targetCount > pendingCount) targetCount = pendingCount;

                if (targetCountInput && parseInt(targetCountInput.value, 10) !== targetCount) {
                    targetCountInput.value = targetCount;
                }

                if (helpText) {
                    if (isUpdateTotal) {
                        helpText.innerHTML = `<strong>Marcado:</strong> O valor total da dívida será recalculado para refletir exatamente o que já foi pago + a nova soma das parcelas.`;
                    } else {
                        helpText.innerHTML = `<strong>Desmarcado (Padrão):</strong> Mantém o valor total da dívida contratada (R$ ${debt.total_amount_formatted}) inalterado. As parcelas abertas não poderão exceder o saldo restante de R$ ${debt.remaining_formatted}.`;
                    }
                }

                // Se a alteração não veio do clique em uma das opções sugeridas de ajuste residual, desativa a flag de cravar última parcela
                if (source !== 'option' && adjustLastInput) {
                    adjustLastInput.value = '0';
                }

                const unitAmount = parseMoneyNumber(amountInput.value);

                if (unitAmount <= 0) {
                    feedbackPanel.style.display = 'none';
                    feedbackPanel.innerHTML = '';
                    if (submitBtn) submitBtn.disabled = true;
                    return;
                }

                const totalNewPending = unitAmount * targetCount;
                const shouldAdjustLast = adjustLastInput && adjustLastInput.value === '1';

                if (isUpdateTotal) {
                    if (reduceInput) reduceInput.value = (targetCount < pendingCount) ? targetCount : '';
                    if (adjustLastInput) adjustLastInput.value = '0';
                    feedbackPanel.style.display = 'block';
                    const newTotalDebt = debt.paid + totalNewPending;
                    const reduceNote = (targetCount < pendingCount) ? ` (${pendingCount - targetCount} excedentes removidas)` : '';
                    feedbackPanel.innerHTML = `
                        <div class="alert alert-info border-0 rounded-3 mb-0 p-3 d-flex align-items-center gap-2" style="background: rgba(59, 130, 246, 0.12); color: #1e40af;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <div><strong>${targetCount}</strong> parcelas de <strong>R$ ${formatMoneyNumber(unitAmount)}</strong> somarão <strong>R$ ${formatMoneyNumber(totalNewPending)}</strong>${reduceNote}.</div>
                                <div class="small mt-1">O valor total da dívida será ajustado de R$ ${debt.total_amount_formatted} para <strong>R$ ${formatMoneyNumber(newTotalDebt)}</strong>.</div>
                            </div>
                        </div>
                    `;
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }

                // Quando mantém o total da dívida:
                // Verifica se o ajuste da última parcela está ativado para cravar no saldo restante
                if (shouldAdjustLast) {
                    const firstSum = (targetCount - 1) * unitAmount;
                    if (firstSum >= (remainingBalance + 0.05)) {
                        if (adjustLastInput) adjustLastInput.value = '0';
                    } else {
                        const lastAmount = Math.max(0, Math.round((remainingBalance - firstSum) * 100) / 100);
                        if (reduceInput) reduceInput.value = (targetCount < pendingCount) ? targetCount : '';

                        feedbackPanel.style.display = 'block';
                        let distText = '';
                        if (targetCount === 1) {
                            distText = `<strong>1 parcela de R$ ${formatMoneyNumber(remainingBalance)}</strong>`;
                        } else if (Math.abs(lastAmount - unitAmount) < 0.01) {
                            distText = `<strong>${targetCount} parcelas de R$ ${formatMoneyNumber(unitAmount)}</strong>`;
                        } else {
                            distText = `<strong>${targetCount - 1} parcela(s) de R$ ${formatMoneyNumber(unitAmount)}</strong> + <strong>1 parcela final de R$ ${formatMoneyNumber(lastAmount)}</strong>`;
                        }

                        const removedSnippet = (targetCount < pendingCount)
                            ? ` Quantidade reduzida de <strong>${pendingCount}</strong> para <strong>${targetCount}</strong> (${pendingCount - targetCount} parcelas removidas).`
                            : ` Quantidade mantida em <strong>${targetCount}</strong> parcelas.`;

                        feedbackPanel.innerHTML = `
                            <div class="alert alert-success border-0 rounded-3 mb-0 p-3" style="background: rgba(16, 185, 129, 0.12); color: #065f46;">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                    <span class="fw-bold d-inline-flex align-items-center"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="me-1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>Distribuição cravada no saldo restante!</span>
                                    <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0" id="btn_cancel_distribution" style="font-size: 0.72rem;">Trocar distribuição</button>
                                </div>
                                <div class="small">${removedSnippet}</div>
                                <div class="small mt-1">
                                    Distribuição: ${distText}, totalizando exatamente <strong>R$ ${formatMoneyNumber(remainingBalance)}</strong>.
                                </div>
                            </div>
                        `;

                        const cancelBtn = document.getElementById('btn_cancel_distribution');
                        if (cancelBtn) {
                            cancelBtn.onclick = function() {
                                if (adjustLastInput) adjustLastInput.value = '0';
                                if (reduceInput) reduceInput.value = '';
                                updateAdjustInstallmentsPreview();
                            };
                        }

                        if (submitBtn) submitBtn.disabled = false;
                        return;
                    }
                }

                // Se a soma de todas as parcelas inteiras exceder o saldo restante
                if (totalNewPending > (remainingBalance + 0.05)) {
                    const excess = totalNewPending - remainingBalance;
                    feedbackPanel.style.display = 'block';

                    const options = [];

                    // Opção 1: Manter a quantidade de parcelas atual, ajustando a última para baixo (resíduo menor)
                    const firstSumSameCount = (targetCount - 1) * unitAmount;
                    if (targetCount > 1 && firstSumSameCount < remainingBalance) {
                        const lastSame = Math.round((remainingBalance - firstSumSameCount) * 100) / 100;
                        if (lastSame > 0) {
                            options.push({
                                count: targetCount,
                                label: `Manter ${targetCount} parcelas`,
                                detail: `${targetCount - 1} de R$ ${formatMoneyNumber(unitAmount)} + 1 última de R$ ${formatMoneyNumber(lastSame)}`,
                                btnClass: 'btn-outline-warning text-dark'
                            });
                        }
                    }

                    // Opção 2: Reduzir para a quantidade máxima inteira que cabe
                    const maxFull = Math.floor(remainingBalance / unitAmount);
                    if (maxFull >= 1 && maxFull < targetCount) {
                        const remFull = Math.round((remainingBalance - (maxFull * unitAmount)) * 100) / 100;
                        if (remFull > 0.01) {
                            const firstSumMax = (maxFull - 1) * unitAmount;
                            if (firstSumMax < remainingBalance) {
                                const lastMax = Math.round((remainingBalance - firstSumMax) * 100) / 100;
                                options.push({
                                    count: maxFull,
                                    label: `Reduzir para ${maxFull} parcelas`,
                                    detail: `${maxFull - 1} de R$ ${formatMoneyNumber(unitAmount)} + 1 última de R$ ${formatMoneyNumber(lastMax)} (economiza ${targetCount - maxFull} parcela${(targetCount - maxFull) > 1 ? 's' : ''})`,
                                    btnClass: 'btn-warning text-dark fw-bold'
                                });
                            }
                        } else {
                            options.push({
                                count: maxFull,
                                label: `Reduzir para ${maxFull} parcelas exatas`,
                                detail: `${maxFull} parcelas de R$ ${formatMoneyNumber(unitAmount)} (sem resíduo, economiza ${targetCount - maxFull} parcela${(targetCount - maxFull) > 1 ? 's' : ''})`,
                                btnClass: 'btn-warning text-dark fw-bold'
                            });
                        }
                    }

                    // Se maxFull + 1 for menor que targetCount (ex: unitAmount bem maior)
                    if ((maxFull + 1) >= 1 && (maxFull + 1) < targetCount) {
                        const firstSumPlus = maxFull * unitAmount;
                        if (firstSumPlus < remainingBalance) {
                            const lastPlus = Math.round((remainingBalance - firstSumPlus) * 100) / 100;
                            if (lastPlus > 0) {
                                options.push({
                                    count: maxFull + 1,
                                    label: `Reduzir para ${maxFull + 1} parcelas`,
                                    detail: `${maxFull} de R$ ${formatMoneyNumber(unitAmount)} + 1 última de R$ ${formatMoneyNumber(lastPlus)} (economiza ${targetCount - (maxFull + 1)} parcela${(targetCount - (maxFull + 1)) > 1 ? 's' : ''})`,
                                    btnClass: 'btn-warning text-dark fw-bold'
                                });
                            }
                        }
                    }

                    // Remove opções duplicadas por quantidade
                    const uniqueOptions = [];
                    const seenCounts = new Set();
                    for (const opt of options) {
                        if (!seenCounts.has(opt.count)) {
                            seenCounts.add(opt.count);
                            uniqueOptions.push(opt);
                        }
                    }

                    let optionsHtml = '';
                    if (uniqueOptions.length > 0) {
                        optionsHtml = `
                            <div class="mt-2 pt-2 border-top border-warning-subtle">
                                <div class="fw-semibold mb-2">💡 Escolha como deseja ajustar para quitar exatamente os R$ ${formatMoneyNumber(remainingBalance)}:</div>
                                <div class="d-flex flex-column gap-2">
                                    ${uniqueOptions.map(opt => `
                                        <button type="button" class="btn btn-sm ${opt.btnClass} text-start rounded-3 px-3 py-2 js-btn-apply-opt" data-count="${opt.count}" style="background: rgba(255,255,255,0.85); border: 1px solid rgba(245, 158, 11, 0.4);">
                                            <div>⚡ <strong>${opt.label}</strong></div>
                                            <div class="small fw-normal mt-1" style="opacity: 0.85;">${opt.detail}</div>
                                        </button>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    } else {
                        optionsHtml = `
                            <div class="mt-2 pt-2 border-top border-warning-subtle small">
                                O valor informado de R$ ${formatMoneyNumber(unitAmount)} é superior ao saldo total restante de R$ ${formatMoneyNumber(remainingBalance)}. Marque a opção de atualizar o total ou informe um valor menor.
                            </div>
                        `;
                    }

                    feedbackPanel.innerHTML = `
                        <div class="alert alert-warning border-0 rounded-3 mb-0 p-3" style="background: rgba(245, 158, 11, 0.15); color: #92400e;">
                            <div class="d-flex align-items-start gap-2">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="flex-shrink-0 text-warning"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <div class="w-100">
                                    <div class="fw-bold">O novo valor excede o saldo restante se todas as parcelas forem integrais!</div>
                                    <div class="small mt-1">
                                        ${targetCount} parcelas de R$ ${formatMoneyNumber(unitAmount)} somariam <strong>R$ ${formatMoneyNumber(totalNewPending)}</strong>, o que excede o saldo devedor restante de <strong>R$ ${formatMoneyNumber(remainingBalance)}</strong> (excesso de R$ ${formatMoneyNumber(excess)}).
                                    </div>
                                    ${optionsHtml}
                                </div>
                            </div>
                        </div>
                    `;

                    feedbackPanel.querySelectorAll('.js-btn-apply-opt').forEach(btn => {
                        btn.onclick = function() {
                            const chosenCount = parseInt(this.dataset.count, 10);
                            if (targetCountInput) targetCountInput.value = chosenCount;
                            if (reduceInput) reduceInput.value = (chosenCount < pendingCount) ? chosenCount : '';
                            if (adjustLastInput) adjustLastInput.value = '1';
                            updateAdjustInstallmentsPreview('option');
                        };
                    });

                    if (submitBtn) submitBtn.disabled = true;
                } else {
                    // Valor é menor ou igual ao saldo restante
                    if (reduceInput) reduceInput.value = (targetCount < pendingCount) ? targetCount : '';
                    if (adjustLastInput) adjustLastInput.value = '0';
                    feedbackPanel.style.display = 'block';

                    const removedText = (targetCount < pendingCount)
                        ? ` (${pendingCount - targetCount} parcela${(pendingCount - targetCount) > 1 ? 's' : ''} excedente${(pendingCount - targetCount) > 1 ? 's' : ''} removida${(pendingCount - targetCount) > 1 ? 's' : ''})`
                        : '';

                    feedbackPanel.innerHTML = `
                        <div class="alert alert-success border-0 rounded-3 mb-0 p-3 d-flex align-items-center gap-2" style="background: rgba(16, 185, 129, 0.12); color: #065f46;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div class="w-100">
                                <div><strong>${targetCount}</strong> parcelas de <strong>R$ ${formatMoneyNumber(unitAmount)}</strong> = <strong>R$ ${formatMoneyNumber(totalNewPending)}</strong>${removedText}.</div>
                            </div>
                        </div>
                    `;

                    if (submitBtn) submitBtn.disabled = false;
                }
            }

            // Renderiza a tabela de parcelas dentro do modal de cronograma
            function renderScheduleTable() {
                const tbody = document.getElementById('schedule_table_body');
                if (!tbody || !activeScheduleDebtId) return;

                const debt = debtsScheduleData[activeScheduleDebtId];
                if (!debt) return;

                let items = debt.installments || [];
                if (activeScheduleFilter === 'pending') {
                    items = items.filter(i => i.status === 'pending');
                } else if (activeScheduleFilter === 'overdue') {
                    items = items.filter(i => i.is_overdue);
                } else if (activeScheduleFilter === 'paid') {
                    items = items.filter(i => i.status === 'paid');
                }

                if (items.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center py-4 text-secondary">
                                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="d-block mx-auto mb-2 text-secondary" style="opacity: 0.6;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-4.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H2"/></svg>
                                Nenhuma parcela encontrada para o filtro selecionado.
                            </td>
                        </tr>
                    `;
                    return;
                }

                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '{{ csrf_token() }}';

                tbody.innerHTML = items.map(inst => {
                    let statusBadge = '';
                    if (inst.is_extraordinary) {
                        statusBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 fw-semibold d-inline-flex align-items-center gap-1"><svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>Amortizado</span>`;
                    } else if (inst.status === 'paid') {
                        statusBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 fw-semibold d-inline-flex align-items-center gap-1"><svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>Paga</span>`;
                    } else if (inst.is_overdue) {
                        statusBadge = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 fw-semibold d-inline-flex align-items-center gap-1"><svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Atrasada</span>`;
                    } else {
                        statusBadge = `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 fw-semibold d-inline-flex align-items-center gap-1"><svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>A Vencer</span>`;
                    }

                    let extraMeta = '';
                    if (inst.status === 'paid') {
                        const dateStr = inst.paid_at ? `Pago em ${inst.paid_at}` : 'Pago';
                        const accStr = inst.account_name ? ` • Conta: ${inst.account_name}` : '';
                        extraMeta = `<div class="small text-success mt-1" style="font-size: 0.72rem;">${dateStr}${accStr}</div>`;
                    } else if (inst.notes) {
                        extraMeta = `<div class="small text-secondary mt-1" style="font-size: 0.72rem;">${inst.notes}</div>`;
                    }

                    let actionHtml = '';
                    if (inst.status === 'paid') {
                        const confirmTitle = inst.is_extraordinary ? 'Desfazer Amortização?' : 'Desfazer Pagamento?';
                        const confirmMsg = inst.is_extraordinary
                            ? 'Deseja desfazer esta amortização extraordinária? Isso excluirá o lançamento bancário associado e restaurará o saldo da conta.'
                            : 'Deseja desfazer o pagamento desta parcela? Isso excluirá o lançamento bancário associado e restaurará o saldo da conta.';
                        actionHtml = `
                            <div class="d-flex align-items-center justify-content-end flex-nowrap">
                                <form method="POST" action="/dividas/parcelas/${inst.id}/desfazer"
                                    class="m-0"
                                    data-confirm="${confirmMsg}"
                                    data-confirm-title="${confirmTitle}"
                                    data-confirm-accept="Sim, desfazer"
                                    data-confirm-cancel="Cancelar"
                                    data-confirm-icon="warning"
                                    data-confirm-btn-class="btn btn-danger rounded-pill px-4"
                                    data-confirm-cancel-class="btn btn-outline-secondary rounded-pill px-4">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="redirect_to" value="${window.location.href}">
                                    <input type="hidden" name="schedule_debt_id" value="${inst.debt_id}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Desfazer pagamento e reabrir parcela">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        <span>Desfazer</span>
                                    </button>
                                </form>
                            </div>
                        `;
                    } else {
                        let resetBtn = '';
                        if (inst.original_amount !== null && Math.abs(inst.original_amount - inst.amount) > 0.01) {
                            const origFmt = formatMoneyNumber(inst.original_amount);
                            resetBtn = `
                                <form method="POST" action="/dividas/parcelas/${inst.id}/resetar-valor"
                                    class="m-0 d-inline-flex align-items-center"
                                    data-confirm="Deseja restaurar a parcela #${inst.installment_number} para o valor original do contrato de R$ ${origFmt}?"
                                    data-confirm-title="Restaurar Valor Original?"
                                    data-confirm-accept="Sim, restaurar"
                                    data-confirm-cancel="Cancelar"
                                    data-confirm-icon="question">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="_method" value="PATCH">
                                    <input type="hidden" name="redirect_to" value="${window.location.href}">
                                    <input type="hidden" name="schedule_debt_id" value="${debt.id}">
                                    <button type="submit" class="accounts-action-btn text-warning" data-bs-toggle="tooltip" data-bs-placement="top" title="Restaurar valor original (R$ ${origFmt})" aria-label="Restaurar valor original">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </button>
                                </form>
                            `;
                        }
                        let editBtn = `
                            <button type="button" class="accounts-action-btn text-primary js-schedule-edit-inst-btn"
                                data-inst-id="${inst.id}"
                                data-amount="${inst.amount_formatted}"
                                data-due-date="${inst.due_date_raw || ''}"
                                data-notes="${inst.notes ? String(inst.notes).replace(/"/g, '&quot;') : ''}"
                                data-number="${inst.installment_number}"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="Editar parcela #${inst.installment_number}" aria-label="Editar parcela #${inst.installment_number}">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                        `;

                        let deleteBtn = `
                            <form method="POST" action="/dividas/parcelas/${inst.id}"
                                class="m-0 d-inline-flex align-items-center"
                                data-confirm="Tem certeza que deseja excluir a parcela #${inst.installment_number} de R$ ${inst.amount_formatted}?"
                                data-confirm-title="Excluir Parcela?"
                                data-confirm-accept="Sim, excluir"
                                data-confirm-cancel="Cancelar"
                                data-confirm-icon="warning">
                                <input type="hidden" name="_token" value="${csrfToken}">
                                <input type="hidden" name="_method" value="DELETE">
                                <input type="hidden" name="redirect_to" value="${window.location.href}">
                                <input type="hidden" name="schedule_debt_id" value="${debt.id}">
                                <button type="submit" class="accounts-action-btn text-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Excluir parcela #${inst.installment_number}" aria-label="Excluir parcela #${inst.installment_number}">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        `;

                        actionHtml = `
                            <div class="d-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                ${resetBtn}
                                ${editBtn}
                                ${deleteBtn}
                                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-semibold js-schedule-pay-btn" data-inst-id="${inst.id}" style="background: #10b981; border: none; font-size: 0.8rem;">
                                    Pagar
                                </button>
                            </div>
                        `;
                    }

                    let valorColHtml = '';
                    if (inst.is_extraordinary) {
                        valorColHtml = `
                            <div class="d-flex flex-column align-items-end">
                                <span class="fw-bold text-primary" style="font-size: 0.95rem;">
                                    R$ ${inst.paid_amount_formatted ?? inst.amount_formatted}
                                </span>
                                <div class="small text-secondary" style="font-size: 0.72rem;">
                                    Aporte avulso
                                </div>
                            </div>
                        `;
                    } else if (inst.status === 'paid') {
                        const paid = inst.paid_amount !== null ? inst.paid_amount : inst.amount;
                        const orig = inst.original_amount !== null ? inst.original_amount : inst.amount;
                        const diff = orig - paid;

                        if (inst.original_amount !== null && diff > 0.01) {
                            valorColHtml = `
                                <div class="d-flex flex-column align-items-end">
                                    <span class="fw-bold text-success" style="font-size: 0.95rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor efetivamente pago">
                                        R$ ${formatMoneyNumber(paid)}
                                    </span>
                                    <div class="small text-secondary" style="font-size: 0.72rem;">
                                        <span class="text-decoration-line-through text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor original do contrato">R$ ${formatMoneyNumber(orig)}</span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-1 ms-1 fw-semibold" style="font-size: 0.68rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Desconto obtido">
                                            -R$ ${formatMoneyNumber(diff)}
                                        </span>
                                    </div>
                                </div>
                            `;
                        } else if (inst.original_amount !== null && diff < -0.01) {
                            valorColHtml = `
                                <div class="d-flex flex-column align-items-end">
                                    <span class="fw-bold text-danger" style="font-size: 0.95rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor efetivamente pago com juros">
                                        R$ ${formatMoneyNumber(paid)}
                                    </span>
                                    <div class="small text-secondary" style="font-size: 0.72rem;">
                                        <span class="text-decoration-line-through text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor original do contrato">R$ ${formatMoneyNumber(orig)}</span>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-1 ms-1 fw-semibold" style="font-size: 0.68rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Acréscimo de juros">
                                            +R$ ${formatMoneyNumber(Math.abs(diff))}
                                        </span>
                                    </div>
                                </div>
                            `;
                        } else {
                            valorColHtml = `
                                <div class="d-flex flex-column align-items-end">
                                    <span class="fw-bold text-secondary text-decoration-line-through" style="font-size: 0.95rem;">
                                        R$ ${formatMoneyNumber(paid)}
                                    </span>
                                </div>
                            `;
                        }
                    } else {
                        const cur = inst.amount;
                        const orig = inst.original_amount !== null ? inst.original_amount : inst.amount;
                        const diff = orig - cur;

                        if (inst.original_amount !== null && diff > 0.01) {
                            valorColHtml = `
                                <div class="d-flex flex-column align-items-end">
                                    <span class="fw-bold text-primary" style="font-size: 0.95rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor atual reduzido por amortização">
                                        R$ ${inst.amount_formatted}
                                    </span>
                                    <div class="small text-secondary" style="font-size: 0.72rem;">
                                        <span class="text-decoration-line-through text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor original do contrato">R$ ${formatMoneyNumber(orig)}</span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-1 ms-1 fw-semibold" style="font-size: 0.68rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Redução obtida">
                                            -R$ ${formatMoneyNumber(diff)}
                                        </span>
                                    </div>
                                </div>
                            `;
                        } else {
                            valorColHtml = `
                                <span class="fw-bold" style="color: var(--dz-text-title); font-size: 0.95rem;">
                                    R$ ${inst.amount_formatted}
                                </span>
                            `;
                        }
                    }

                    let numColHtml = '';
                    let mainColHtml = '';

                    if (inst.is_extraordinary) {
                        numColHtml = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;"><svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>Aporte</span>`;
                        let cleanNotes = (inst.notes || '').replace(/^amortização\s+extraordinária\s*/i, '').trim();
                        if (cleanNotes.startsWith('(') && cleanNotes.endsWith(')')) {
                            cleanNotes = cleanNotes.slice(1, -1);
                        }
                        const noteSnippet = cleanNotes ? ` • ${cleanNotes}` : '';
                        mainColHtml = `
                            <div class="fw-semibold text-primary" style="font-size: 0.9rem;">Amortização Extraordinária</div>
                            <div class="text-secondary small" style="font-size: 0.75rem;">${inst.due_date}${noteSnippet}</div>
                            ${extraMeta}
                        `;
                    } else {
                        numColHtml = `#${inst.installment_number}`;
                        mainColHtml = `
                            <div class="fw-semibold" style="color: var(--dz-text-title);">${inst.due_date}</div>
                            ${inst.day_week ? `<div class="text-secondary small" style="font-size: 0.72rem;">${inst.day_week}</div>` : ''}
                            ${extraMeta}
                        `;
                    }

                    return `
                        <tr>
                            <td class="ps-3 py-2 fw-semibold text-nowrap" style="color: var(--dz-text-title);">
                                ${numColHtml}
                            </td>
                            <td class="py-2">
                                ${mainColHtml}
                            </td>
                            <td class="py-2 text-end text-nowrap">
                                ${valorColHtml}
                            </td>
                            <td class="py-2 text-center text-nowrap">
                                ${statusBadge}
                            </td>
                            <td class="pe-3 py-2 text-end text-nowrap">
                                ${actionHtml}
                            </td>
                        </tr>
                    `;
                }).join('');

                // Inicializa tooltips para itens dinâmicos gerados no cronograma
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    tbody.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                        bootstrap.Tooltip.getOrCreateInstance(el, { container: 'body' });
                    });
                }

                // Listener para os botões Pagar de dentro da tabela do cronograma
                tbody.querySelectorAll('.js-schedule-pay-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const instId = parseInt(this.dataset.instId, 10);
                        const inst = (debt.installments || []).find(i => i.id === instId);
                        if (!inst) return;

                        // Fecha o modal de cronograma
                        const scheduleModalEl = document.getElementById('modalDebtSchedule');
                        if (scheduleModalEl && typeof bootstrap !== 'undefined') {
                            const modal = bootstrap.Modal.getInstance(scheduleModalEl);
                            if (modal) modal.hide();
                        }

                        // Preenche e abre o modal de pagamento
                        const form = document.getElementById('formPayInstallment');
                        if (form) form.action = `/dividas/parcelas/${inst.id}/pagamento`;

                        const redirectInput = document.getElementById('pay_redirect_to');
                        if (redirectInput) redirectInput.value = window.location.href;

                        const scheduleDebtInput = document.getElementById('pay_schedule_debt_id');
                        if (scheduleDebtInput) scheduleDebtInput.value = inst.debt_id;

                        const subtitle = document.getElementById('pay_installment_subtitle');
                        if (subtitle) {
                            let subText = `${inst.debt_name} • Parcela ${inst.installment_number} de ${inst.total_parcels}`;
                            if (inst.original_amount_formatted && inst.original_amount_formatted !== inst.amount_formatted) {
                                subText += ` (Original: R$ ${inst.original_amount_formatted})`;
                            }
                            subtitle.textContent = subText;
                        }

                        const amountInput = document.getElementById('pay_amount');
                        if (amountInput) amountInput.value = inst.amount_formatted;

                        const dateInput = document.getElementById('pay_date');
                        if (dateInput) dateInput.value = inst.due_date_raw || '{{ date('Y-m-d') }}';

                        if (inst.default_account) {
                            const accInput = document.getElementById('pay_account_id');
                            if (accInput) accInput.value = inst.default_account;
                        }

                        if (inst.default_category) {
                            const catInput = document.getElementById('pay_category_id');
                            if (catInput) catInput.value = inst.default_category;
                        }

                        const btnRestore = document.getElementById('btn_pay_restore_original');
                        if (btnRestore) {
                            if (inst.original_amount_formatted && inst.original_amount_formatted !== inst.amount_formatted) {
                                btnRestore.style.display = 'inline-block';
                                btnRestore.textContent = `Restaurar original (R$ ${inst.original_amount_formatted})`;
                                btnRestore.onclick = function() {
                                    if (amountInput) amountInput.value = inst.original_amount_formatted;
                                };
                            } else {
                                btnRestore.style.display = 'none';
                            }
                        }

                        const payModalEl = document.getElementById('modalPayInstallment');
                        if (payModalEl && typeof bootstrap !== 'undefined') {
                            new bootstrap.Modal(payModalEl).show();
                        }
                    });
                });

                // Listener para os botões Editar parcela de dentro da tabela do cronograma
                tbody.querySelectorAll('.js-schedule-edit-inst-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const instId = this.dataset.instId;
                        const amount = this.dataset.amount;
                        const dueDate = this.dataset.dueDate;
                        const notes = this.dataset.notes;
                        const number = this.dataset.number;
                        openEditSingleInstallmentModal(instId, amount, dueDate, notes, number);
                    });
                });
            }

            // Listeners para abrir o Cronograma Completo de Parcelas
            document.querySelectorAll('.js-btn-open-schedule').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const debtId = parseInt(this.dataset.debtId, 10);
                    if (debtId) {
                        openDebtSchedule(debtId);
                    }
                });
            });

            // Listeners para filtros de status no modal de cronograma
            document.querySelectorAll('.js-schedule-filter-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.js-schedule-filter-btn').forEach(b => {
                        b.classList.remove('active', 'btn-primary');
                        b.classList.add('btn-outline-secondary');
                    });
                    this.classList.add('active', 'btn-primary');
                    this.classList.remove('btn-outline-secondary');
                    activeScheduleFilter = this.dataset.filter || 'all';
                    renderScheduleTable();
                });
            });

            // Botão de amortizar dentro do modal de cronograma
            const btnAmortizeSchedule = document.getElementById('schedule_btn_amortize');
            if (btnAmortizeSchedule) {
                btnAmortizeSchedule.addEventListener('click', function() {
                    if (!activeScheduleDebtId) return;
                    const scheduleModalEl = document.getElementById('modalDebtSchedule');
                    if (scheduleModalEl && typeof bootstrap !== 'undefined') {
                        const modal = bootstrap.Modal.getInstance(scheduleModalEl);
                        if (modal) modal.hide();
                    }
                    openAmortizeForDebt(activeScheduleDebtId);
                });
            }

            // Modal de Amortização (Listeners de clique e evento Bootstrap)
            document.querySelectorAll('.js-btn-amortize').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    setupAmortizeModal(this);
                });
            });

            const modalAmortizeEl = document.getElementById('modalAmortizeDebt');
            if (modalAmortizeEl) {
                modalAmortizeEl.addEventListener('show.bs.modal', function(event) {
                    if (event.relatedTarget) {
                        setupAmortizeModal(event.relatedTarget);
                    }
                });
            }

            // Alternância de abas de amortização
            document.querySelectorAll('.js-amortize-tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    setAmortizeStrategy(this.dataset.strategy);
                });
            });

            // Listener no input de valor a amortizar
            const amortizeAmountInput = document.getElementById('amortize_amount');
            if (amortizeAmountInput) {
                amortizeAmountInput.addEventListener('input', function() {
                    if (currentStrategy === 'reduce_amount') {
                        updateReduceAmount();
                    } else if (currentStrategy === 'reduce_term') {
                        const countSelect = document.getElementById('amortize_term_count');
                        const k = parseInt(countSelect?.value, 10) || 1;
                        const toSettle = currentInstallments.slice(-k);
                        const origSum = toSettle.reduce((sum, item) => sum + item.amount, 0);
                        const paid = parseMoneyNumber(this.value);
                        const badge = document.getElementById('amortize_amount_discount_badge');
                        if (badge) {
                            if (paid > 0 && origSum > paid) {
                                badge.style.display = 'block';
                                badge.textContent = `🎉 Desconto obtido: R$ ${formatMoneyNumber(origSum - paid)}`;
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                });

                amortizeAmountInput.addEventListener('blur', function() {
                    const val = parseMoneyNumber(this.value);
                    if (val > 0) {
                        this.value = formatMoneyNumber(val);
                    }
                });
            }

            // Listener no input do novo valor das parcelas (Reduzir Parcela)
            const amortizeNewAmountInput = document.getElementById('amortize_new_installment_amount');
            if (amortizeNewAmountInput) {
                amortizeNewAmountInput.addEventListener('input', function() {
                    if (currentStrategy === 'reduce_amount') {
                        updateFromNewInstallmentAmount();
                    }
                });

                amortizeNewAmountInput.addEventListener('blur', function() {
                    const val = parseMoneyNumber(this.value);
                    if (val > 0) {
                        this.value = formatMoneyNumber(val);
                    }
                });
            }

            // Toggle para parcela residual no reduce_term
            const toggleResidual = document.getElementById('amortize_toggle_residual');
            if (toggleResidual) {
                toggleResidual.addEventListener('change', function() {
                    const resFields = document.getElementById('amortize_residual_fields');
                    if (resFields) {
                        resFields.style.display = this.checked ? 'block' : 'none';
                    }
                    if (this.checked) {
                        const countSelect = document.getElementById('amortize_term_count');
                        const k = parseInt(countSelect?.value, 10) || 1;
                        if (currentInstallments.length > k) {
                            const residualInst = currentInstallments[currentInstallments.length - k - 1];
                            const resAmtInput = document.getElementById('amortize_residual_amount');
                            if (resAmtInput && (!resAmtInput.value || resAmtInput.value === '0,00')) {
                                resAmtInput.value = formatMoneyNumber(residualInst.amount);
                            }
                        }
                    }
                });
            }

            const amortizeResidualInput = document.getElementById('amortize_residual_amount');
            if (amortizeResidualInput) {
                amortizeResidualInput.addEventListener('blur', function() {
                    const val = parseMoneyNumber(this.value);
                    if (val > 0) {
                        this.value = formatMoneyNumber(val);
                    }
                });
            }

            // Modal de Edição de Dívida
            document.querySelectorAll('.js-btn-edit-debt').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const form = document.getElementById('formDebtEdit');
                    form.action = `/dividas/${id}`;

                    document.getElementById('edit_debt_name').value = this.dataset.name || '';
                    document.getElementById('edit_debt_creditor').value = this.dataset.creditor || '';
                    const editTotalEl = document.getElementById('edit_debt_total_amount');
                    if (editTotalEl) editTotalEl.value = this.dataset.totalAmount || '';
                    document.getElementById('edit_debt_color').value = this.dataset.color || '#f59e0b';
                    document.getElementById('edit_debt_notes').value = this.dataset.notes || '';
                    document.getElementById('edit_debt_account').value = this.dataset.defaultAccount || '';
                    document.getElementById('edit_debt_category').value = this.dataset.defaultCategory || '';
                    if (document.getElementById('edit_debt_user_id')) {
                        document.getElementById('edit_debt_user_id').value = this.dataset.userId || '';
                    }
                    document.getElementById('edit_debt_is_active').checked = this.dataset.isActive === '1';

                    const adjustBanner = document.getElementById('edit_debt_adjust_banner');
                    const btnAdjustTrigger = document.getElementById('btn_edit_debt_trigger_adjust');
                    if (adjustBanner && btnAdjustTrigger) {
                        if (this.dataset.type === 'installments' && this.dataset.hasPending === '1') {
                            adjustBanner.style.display = 'flex';
                            btnAdjustTrigger.onclick = function() {
                                const editModal = bootstrap.Modal.getInstance(document.getElementById('modalDebtEdit'));
                                if (editModal) editModal.hide();
                                openAdjustRemainingModal(parseInt(id, 10));
                            };
                        } else {
                            adjustBanner.style.display = 'none';
                        }
                    }

                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        const tooltip = bootstrap.Tooltip.getInstance(this);
                        if (tooltip) tooltip.hide();
                    }

                    const editModalEl = document.getElementById('modalDebtEdit');
                    if (editModalEl && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(editModalEl).show();
                    }
                });
            });

            const editTotalInput = document.getElementById('edit_debt_total_amount');
            if (editTotalInput) {
                editTotalInput.addEventListener('blur', function() {
                    const val = parseMoneyNumber(this.value);
                    if (val > 0) {
                        this.value = formatMoneyNumber(val);
                    }
                });
            }

            // Listeners para o Modal de Ajuste de Parcelas Restantes
            const btnBulkEdit = document.getElementById('schedule_btn_bulk_edit');
            if (btnBulkEdit) {
                btnBulkEdit.addEventListener('click', function() {
                    if (activeScheduleDebtId) openAdjustRemainingModal(activeScheduleDebtId);
                });
            }
            const btnBulkEditFooter = document.getElementById('schedule_btn_bulk_edit_footer');
            if (btnBulkEditFooter) {
                btnBulkEditFooter.addEventListener('click', function() {
                    if (activeScheduleDebtId) openAdjustRemainingModal(activeScheduleDebtId);
                });
            }

            const btnAddInst = document.getElementById('schedule_btn_add_inst');
            if (btnAddInst) {
                btnAddInst.addEventListener('click', function() {
                    if (activeScheduleDebtId) openAddSingleInstallmentModal(activeScheduleDebtId);
                });
            }
            const btnAddInstFooter = document.getElementById('schedule_btn_add_inst_footer');
            if (btnAddInstFooter) {
                btnAddInstFooter.addEventListener('click', function() {
                    if (activeScheduleDebtId) openAddSingleInstallmentModal(activeScheduleDebtId);
                });
            }

            const addInstAmountInput = document.getElementById('add_inst_amount');
            if (addInstAmountInput) {
                addInstAmountInput.addEventListener('blur', function() {
                    const val = parseMoneyNumber(this.value);
                    if (val > 0) this.value = formatMoneyNumber(val);
                });
            }

            const editInstAmountInput = document.getElementById('edit_inst_amount');
            if (editInstAmountInput) {
                editInstAmountInput.addEventListener('blur', function() {
                    const val = parseMoneyNumber(this.value);
                    if (val > 0) this.value = formatMoneyNumber(val);
                });
            }

            // Botão direto no card da dívida na listagem
            document.querySelectorAll('.js-btn-direct-adjust').forEach(btn => {
                btn.addEventListener('click', function() {
                    const debtId = parseInt(this.dataset.debtId, 10);
                    if (debtId) openAdjustRemainingModal(debtId);
                });
            });

            const adjustTargetCountInput = document.getElementById('adjust_target_installments_count');
            if (adjustTargetCountInput) {
                adjustTargetCountInput.addEventListener('input', function() {
                    updateAdjustInstallmentsPreview();
                });
                adjustTargetCountInput.addEventListener('change', function() {
                    updateAdjustInstallmentsPreview();
                });
            }

            const adjustAmountInput = document.getElementById('adjust_new_installment_amount');
            if (adjustAmountInput) {
                adjustAmountInput.addEventListener('input', function() {
                    updateAdjustInstallmentsPreview();
                });
                adjustAmountInput.addEventListener('blur', function() {
                    const val = parseMoneyNumber(this.value);
                    if (val > 0) {
                        this.value = formatMoneyNumber(val);
                    }
                    updateAdjustInstallmentsPreview();
                });
            }

            const adjustUpdateTotalCheck = document.getElementById('adjust_update_total_amount');
            if (adjustUpdateTotalCheck) {
                adjustUpdateTotalCheck.addEventListener('change', function() {
                    updateAdjustInstallmentsPreview();
                });
            }

            // Reabre modal de criação em caso de erro de validação
            @if($errors->has('total_amount') || $errors->has('total_installments') || $errors->has('start_date') || $errors->has('name') || $errors->has('default_category_id'))
                const createModalEl = document.getElementById('modalDebtCreate');
                if (createModalEl && typeof bootstrap !== 'undefined') {
                    new bootstrap.Modal(createModalEl).show();
                }
            @endif

            // Reabre cronograma da dívida se o pagamento/amortização veio do modal de parcelas
            @if(session('open_schedule_debt_id'))
                if (typeof openDebtSchedule === 'function') {
                    openDebtSchedule({{ (int) session('open_schedule_debt_id') }});
                }
            @endif
        });
    </script>
    @endpush
</x-app-layout>
