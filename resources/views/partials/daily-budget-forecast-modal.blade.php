@php
    $forecast = $dailyBudgetForecast ?? null;
@endphp

@if ($forecast)
    @php
        $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
        $status = $forecast['status'];
        
        $statusBadgeClass = match($status) {
            'healthy' => 'bg-success-subtle text-success border border-success-subtle',
            'warning' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            'deficit' => 'bg-danger-subtle text-danger border border-danger-subtle',
            default => 'bg-secondary-subtle text-secondary',
        };

        $accentColor = match($status) {
            'healthy' => 'var(--dz-success)',
            'warning' => 'var(--dz-warning)',
            'deficit' => 'var(--dz-danger)',
            default => 'var(--dz-primary)',
        };

        $dailyValueClass = match($status) {
            'healthy' => 'text-success',
            'warning' => 'text-warning',
            'deficit' => 'text-danger',
            default => 'text-primary',
        };

        $totalIncomeItemsCount = ($forecast['base_planned_income'] > 0 ? 1 : 0) + ($forecast['recurring_incomes_count'] ?? 0);
    @endphp

    <div class="modal fade" id="modalDailyBudgetForecast" tabindex="-1" aria-labelledby="modalDailyBudgetForecastLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content border-0 shadow" style="border-radius: var(--dz-radius-lg); background: var(--dz-bg-card);">
                <!-- Modal Header -->
                <div class="modal-header align-items-start pb-2" style="border-bottom: 1px solid var(--dz-border-subtle); border-top: 4px solid {{ $accentColor }};">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span style="font-size: 1.25rem;">🎯</span>
                            <h2 class="modal-title h5 fw-bold mb-0" id="modalDailyBudgetForecastLabel" style="color: var(--dz-text-title);">
                                Composição do Orçamento Diário
                            </h2>
                            <span class="badge rounded-pill {{ $statusBadgeClass }}" style="font-size: 0.72rem; padding: 0.35rem 0.65rem;">
                                Restam {{ $forecast['days_remaining_current_month'] }} {{ $forecast['days_remaining_current_month'] === 1 ? 'dia' : 'dias' }} em {{ ucfirst($forecast['current_month_label']) }} (contando hoje)
                            </span>
                        </div>
                        <p class="small text-secondary mb-0">
                            Base: gastos já contratados para <strong>{{ ucfirst($forecast['target_month_label']) }}</strong> descontados da receita prevista.
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-3 p-md-4">
                    <!-- 4 Pilares do Cálculo (clique para abrir/focar no acordeão) -->
                    <div class="row g-2 mb-3">
                        <!-- 1. Receita Prevista -->
                        <div class="col-6 col-md-3">
                            <div class="p-2 p-md-3 rounded-3 h-100" 
                                 onclick="const el = document.getElementById('collapseIncome'); if (el) { bootstrap.Collapse.getOrCreateInstance(el).show(); el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }"
                                 title="Ver detalhes de receitas"
                                 style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle); cursor: pointer; transition: var(--dz-transition);">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <div class="d-flex align-items-center gap-1 text-success small fw-bold">
                                        <span>🟢</span>
                                        <span>Receita Prevista</span>
                                    </div>
                                    <span style="font-size: 0.7rem; opacity: 0.6;">▾</span>
                                </div>
                                <div class="fw-bold fs-6 dz-privacy-blur text-body">
                                    + {{ $money($forecast['planned_income']) }}
                                </div>
                                <div style="font-size: 0.7rem; color: var(--dz-text-secondary); margin-top: 2px;">
                                    @if (! empty($forecast['recurring_incomes_count']))
                                        Base + {{ $forecast['recurring_incomes_count'] }} recorrente(s)
                                    @else
                                        {{ ucfirst($forecast['target_month_label']) }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- 2. Faturas de Cartão -->
                        <div class="col-6 col-md-3">
                            <div class="p-2 p-md-3 rounded-3 h-100" 
                                 onclick="const el = document.getElementById('collapseInvoices'); if (el) { bootstrap.Collapse.getOrCreateInstance(el).show(); el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }"
                                 title="Ver detalhes das faturas"
                                 style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle); cursor: pointer; transition: var(--dz-transition);">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <div class="d-flex align-items-center gap-1 text-danger small fw-bold">
                                        <span>💳</span>
                                        <span>Faturas Cartão</span>
                                    </div>
                                    <span style="font-size: 0.7rem; opacity: 0.6;">▾</span>
                                </div>
                                <div class="fw-bold fs-6 dz-privacy-blur text-danger">
                                    - {{ $money($forecast['card_invoices_total']) }}
                                </div>
                                <div style="font-size: 0.7rem; color: var(--dz-text-secondary); margin-top: 2px;">
                                    {{ $forecast['card_invoices_count'] }} fatura(s)
                                </div>
                            </div>
                        </div>

                        <!-- 3. Recorrentes -->
                        <div class="col-6 col-md-3">
                            <div class="p-2 p-md-3 rounded-3 h-100" 
                                 onclick="const el = document.getElementById('collapseRecurring'); if (el) { bootstrap.Collapse.getOrCreateInstance(el).show(); el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }"
                                 title="Ver detalhes das despesas recorrentes"
                                 style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle); cursor: pointer; transition: var(--dz-transition);">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <div class="d-flex align-items-center gap-1 text-warning-emphasis small fw-bold">
                                        <span>🔄</span>
                                        <span>Recorrentes</span>
                                    </div>
                                    <span style="font-size: 0.7rem; opacity: 0.6;">▾</span>
                                </div>
                                <div class="fw-bold fs-6 dz-privacy-blur text-danger">
                                    - {{ $money($forecast['recurring_expenses_total']) }}
                                </div>
                                <div style="font-size: 0.7rem; color: var(--dz-text-secondary); margin-top: 2px;">
                                    {{ $forecast['recurring_expenses_count'] }} modelo(s)
                                </div>
                            </div>
                        </div>

                        <!-- 4. Dívidas & Boletos -->
                        <div class="col-6 col-md-3">
                            <div class="p-2 p-md-3 rounded-3 h-100" 
                                 onclick="const el = document.getElementById('collapseDebts'); if (el) { bootstrap.Collapse.getOrCreateInstance(el).show(); el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }"
                                 title="Ver detalhes das dívidas"
                                 style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle); cursor: pointer; transition: var(--dz-transition);">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <div class="d-flex align-items-center gap-1 text-danger-emphasis small fw-bold">
                                        <span>📑</span>
                                        <span>Dívidas</span>
                                    </div>
                                    <span style="font-size: 0.7rem; opacity: 0.6;">▾</span>
                                </div>
                                <div class="fw-bold fs-6 dz-privacy-blur text-danger">
                                    - {{ $money($forecast['debt_installments_total']) }}
                                </div>
                                <div style="font-size: 0.7rem; color: var(--dz-text-secondary); margin-top: 2px;">
                                    {{ $forecast['debt_installments_count'] }} parcela(s)
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Destaque do Orçamento Diário & Barra de Comprometimento -->
                    <div class="p-3 rounded-3 mb-3" style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle);">
                        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                            <div>
                                <div class="small text-secondary fw-semibold">Limite diário sugerido para passar o mês</div>
                                <div class="d-flex align-items-baseline gap-2">
                                    <span class="h2 fw-extrabold mb-0 dz-privacy-blur {{ $dailyValueClass }}" style="letter-spacing: -0.02em;">
                                        {{ $money($forecast['daily_budget']) }}
                                    </span>
                                    <span class="fw-semibold text-secondary" style="font-size: 0.95rem;">/ dia</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="small text-secondary d-block">Saldo livre projetado</span>
                                <strong class="fs-5 dz-privacy-blur text-body">{{ $money(max(0, $forecast['free_amount'])) }}</strong>
                            </div>
                        </div>

                        @if ($status === 'deficit')
                            <div class="alert alert-danger p-2 px-3 mt-2 mb-0 rounded-2 small fw-semibold">
                                ⚠️ Atenção: Gastos já contratados superam a receita prevista para {{ ucfirst($forecast['target_month_label']) }} em <strong class="dz-privacy-blur">{{ $money(abs($forecast['free_amount'])) }}</strong>.
                            </div>
                        @elseif (! $forecast['has_planned_income_configured'])
                            <div class="alert alert-warning p-2 px-3 mt-2 mb-0 rounded-2 small fw-semibold">
                                ℹ️ Renda planejada não informada. <a href="{{ route('categories.index') }}#orcamento" class="text-decoration-underline" style="color: inherit;">Configurar renda mensal ↗</a>
                            </div>
                        @endif

                        <!-- Barra de Comprometimento -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.76rem;">
                                <span class="fw-bold" style="color: var(--dz-text-title);">Comprometimento da Renda ({{ number_format($forecast['committed_pct'], 1, ',', '.') }}%)</span>
                                <span>Contratado: <strong class="dz-privacy-blur text-body">{{ $money($forecast['committed_total']) }}</strong> de <strong class="dz-privacy-blur text-body">{{ $money($forecast['planned_income']) }}</strong></span>
                            </div>
                            <div class="dz-progress-bar" style="height: 6px; background: var(--dz-border); border-radius: 9999px;">
                                <div class="dz-progress-bar__fill" style="width: {{ min(100, $forecast['committed_pct']) }}%; background: {{ $accentColor }};"></div>
                            </div>
                        </div>
                    </div>

                    <!-- DETALHAMENTO EM ACORDEÃO SANFONA DE LARGURA TOTAL (OPÇÃO C) -->
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                        <span class="fw-bold small text-secondary">Composição dos Compromissos</span>
                        <span class="small text-secondary" style="font-size: 0.72rem;">Clique em qualquer seção para expandir ou recolher</span>
                    </div>

                    <div class="accordion d-flex flex-column gap-2" id="accordionForecast">
                        <!-- 1. Faturas de Cartão de Crédito -->
                        <div class="accordion-item border rounded-3 overflow-hidden" style="border-color: var(--dz-border) !important; background: var(--dz-bg-card);">
                            <h3 class="accordion-header" id="headingInvoices">
                                <button class="accordion-button collapsed py-2 px-3 fw-bold" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapseInvoices" 
                                        aria-expanded="false" 
                                        aria-controls="collapseInvoices"
                                        style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); color: var(--dz-text-title); font-size: 0.88rem; box-shadow: none;">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                        <span>💳</span>
                                        <span class="text-truncate">Faturas de Cartão de Crédito</span>
                                        <span class="badge rounded-pill bg-light text-secondary border ms-1 flex-shrink-0" style="font-size: 0.68rem; font-weight: 600;">
                                            {{ $forecast['card_invoices_count'] }} fatura(s)
                                        </span>
                                    </div>
                                    <div class="pe-3 flex-shrink-0">
                                        <strong class="dz-privacy-blur text-danger">- {{ $money($forecast['card_invoices_total']) }}</strong>
                                    </div>
                                </button>
                            </h3>
                            <div id="collapseInvoices" class="accordion-collapse collapse" aria-labelledby="headingInvoices">
                                <div class="accordion-body p-3 pt-2">
                                    <div class="d-flex justify-content-between align-items-center pb-2 mb-1 border-bottom border-light-subtle">
                                        <span class="small text-secondary" style="font-size: 0.72rem;">Compras e parcelas que fecham no próximo mês</span>
                                        <a href="{{ route('credit-card-statements.index') }}" style="font-size: 0.72rem; color: var(--dz-primary); text-decoration: none; font-weight: 700;">Ver faturas ↗</a>
                                    </div>
                                    @if (empty($forecast['card_invoices_items']))
                                        <div class="py-2 text-secondary small">Nenhuma fatura com compras para o próximo mês.</div>
                                    @else
                                        <ul class="list-group list-group-flush mb-0">
                                            @foreach ($forecast['card_invoices_items'] as $cardItem)
                                                <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-bottom border-light-subtle" style="background: transparent;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: {{ $cardItem['account_color'] }};"></span>
                                                        <strong class="text-body">{{ $cardItem['account_name'] }}</strong>
                                                        <a href="{{ route('credit-card-statements.index', ['account_id' => $cardItem['account_id']]) }}" class="small text-decoration-none ms-2" style="color: var(--dz-primary); font-size: 0.72rem;">
                                                            Ver fatura ↗
                                                        </a>
                                                    </div>
                                                    <strong class="dz-privacy-blur text-danger">- {{ $money($cardItem['amount']) }}</strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- 2. Despesas Recorrentes -->
                        <div class="accordion-item border rounded-3 overflow-hidden" style="border-color: var(--dz-border) !important; background: var(--dz-bg-card);">
                            <h3 class="accordion-header" id="headingRecurring">
                                <button class="accordion-button collapsed py-2 px-3 fw-bold" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapseRecurring" 
                                        aria-expanded="false" 
                                        aria-controls="collapseRecurring"
                                        style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); color: var(--dz-text-title); font-size: 0.88rem; box-shadow: none;">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                        <span>🔄</span>
                                        <span class="text-truncate">Despesas Recorrentes Contratadas</span>
                                        <span class="badge rounded-pill bg-light text-secondary border ms-1 flex-shrink-0" style="font-size: 0.68rem; font-weight: 600;">
                                            {{ $forecast['recurring_expenses_count'] }} modelo(s)
                                        </span>
                                    </div>
                                    <div class="pe-3 flex-shrink-0">
                                        <strong class="dz-privacy-blur text-danger">- {{ $money($forecast['recurring_expenses_total']) }}</strong>
                                    </div>
                                </button>
                            </h3>
                            <div id="collapseRecurring" class="accordion-collapse collapse" aria-labelledby="headingRecurring">
                                <div class="accordion-body p-3 pt-2">
                                    <div class="d-flex justify-content-between align-items-center pb-2 mb-1 border-bottom border-light-subtle">
                                        <span class="small text-secondary" style="font-size: 0.72rem;">Compromissos fixos mensais previstos</span>
                                        <a href="{{ route('recurring-transactions.index') }}" style="font-size: 0.72rem; color: var(--dz-primary); text-decoration: none; font-weight: 700;">Gerenciar recorrentes ↗</a>
                                    </div>
                                    @if (empty($forecast['recurring_expenses_items']))
                                        <div class="py-2 text-secondary small">Nenhuma despesa recorrente cadastrada.</div>
                                    @else
                                        <ul class="list-group list-group-flush mb-0">
                                            @foreach ($forecast['recurring_expenses_items'] as $recItem)
                                                <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-bottom border-light-subtle" style="background: transparent;">
                                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                                        <span class="fw-semibold text-body">{{ $recItem['description'] }}</span>
                                                        @if ($recItem['day_of_month'])
                                                            <span class="badge rounded-pill bg-light text-secondary border ms-1" style="font-size: 0.65rem;">dia {{ $recItem['day_of_month'] }}</span>
                                                        @endif
                                                        <span class="text-secondary small ms-1" style="font-size: 0.7rem;">
                                                            ({{ $recItem['funding'] === 'credit_card' ? 'Cartão' : 'Conta' }})
                                                        </span>
                                                    </div>
                                                    <strong class="dz-privacy-blur text-danger flex-shrink-0 ms-2">- {{ $money($recItem['amount']) }}</strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- 3. Parcelas de Dívidas -->
                        <div class="accordion-item border rounded-3 overflow-hidden" style="border-color: var(--dz-border) !important; background: var(--dz-bg-card);">
                            <h3 class="accordion-header" id="headingDebts">
                                <button class="accordion-button collapsed py-2 px-3 fw-bold" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapseDebts" 
                                        aria-expanded="false" 
                                        aria-controls="collapseDebts"
                                        style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); color: var(--dz-text-title); font-size: 0.88rem; box-shadow: none;">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                        <span>📑</span>
                                        <span class="text-truncate">Parcelas de Dívidas & Empréstimos</span>
                                        <span class="badge rounded-pill bg-light text-secondary border ms-1 flex-shrink-0" style="font-size: 0.68rem; font-weight: 600;">
                                            {{ $forecast['debt_installments_count'] }} parcela(s)
                                        </span>
                                    </div>
                                    <div class="pe-3 flex-shrink-0">
                                        <strong class="dz-privacy-blur text-danger">- {{ $money($forecast['debt_installments_total']) }}</strong>
                                    </div>
                                </button>
                            </h3>
                            <div id="collapseDebts" class="accordion-collapse collapse" aria-labelledby="headingDebts">
                                <div class="accordion-body p-3 pt-2">
                                    <div class="d-flex justify-content-between align-items-center pb-2 mb-1 border-bottom border-light-subtle">
                                        <span class="small text-secondary" style="font-size: 0.72rem;">Boletos e parcelas com vencimento no próximo mês</span>
                                        <a href="{{ route('debts.index', ['tab' => 'agenda']) }}" style="font-size: 0.72rem; color: var(--dz-primary); text-decoration: none; font-weight: 700;">Ver agenda ↗</a>
                                    </div>
                                    @if (empty($forecast['debt_installments_items']))
                                        <div class="py-2 text-secondary small">Nenhuma parcela de dívida prevista para o próximo mês.</div>
                                    @else
                                        <ul class="list-group list-group-flush mb-0">
                                            @foreach ($forecast['debt_installments_items'] as $debtItem)
                                                <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-bottom border-light-subtle" style="background: transparent;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="fw-semibold text-body">{{ $debtItem['debt_name'] }}</span>
                                                        <span class="badge rounded-pill bg-light text-secondary border ms-1" style="font-size: 0.65rem;">#{{ $debtItem['installment_number'] }}</span>
                                                        @if ($debtItem['due_date'])
                                                            <span class="text-secondary small ms-1" style="font-size: 0.7rem;">(venc. {{ $debtItem['due_date'] }})</span>
                                                        @endif
                                                    </div>
                                                    <strong class="dz-privacy-blur text-danger">- {{ $money($debtItem['amount']) }}</strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- 4. Receitas Previstas -->
                        <div class="accordion-item border rounded-3 overflow-hidden" style="border-color: var(--dz-border) !important; background: var(--dz-bg-card);">
                            <h3 class="accordion-header" id="headingIncome">
                                <button class="accordion-button collapsed py-2 px-3 fw-bold" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapseIncome" 
                                        aria-expanded="false" 
                                        aria-controls="collapseIncome"
                                        style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); color: var(--dz-text-title); font-size: 0.88rem; box-shadow: none;">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                        <span>🟢</span>
                                        <span class="text-truncate">Receitas Previstas (Entradas)</span>
                                        <span class="badge rounded-pill bg-light text-secondary border ms-1 flex-shrink-0" style="font-size: 0.68rem; font-weight: 600;">
                                            {{ $totalIncomeItemsCount }} entrada(s)
                                        </span>
                                    </div>
                                    <div class="pe-3 flex-shrink-0">
                                        <strong class="dz-privacy-blur text-success">+ {{ $money($forecast['planned_income']) }}</strong>
                                    </div>
                                </button>
                            </h3>
                            <div id="collapseIncome" class="accordion-collapse collapse" aria-labelledby="headingIncome">
                                <div class="accordion-body p-3 pt-2">
                                    <div class="d-flex justify-content-between align-items-center pb-2 mb-1 border-bottom border-light-subtle">
                                        <span class="small text-secondary" style="font-size: 0.72rem;">Renda planejada mensal e receitas recorrentes ativas</span>
                                        <a href="{{ route('recurring-transactions.index') }}" style="font-size: 0.72rem; color: var(--dz-primary); text-decoration: none; font-weight: 700;">Gerenciar receitas ↗</a>
                                    </div>
                                    <ul class="list-group list-group-flush mb-0">
                                        @if ((float) ($forecast['base_planned_income'] ?? 0) > 0)
                                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-bottom border-light-subtle" style="background: transparent;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span style="font-size: 0.95rem;">💼</span>
                                                    <span class="fw-semibold text-body">Renda Mensal Planejada</span>
                                                    <span class="text-secondary small ms-1" style="font-size: 0.7rem;">(salário base do casal)</span>
                                                </div>
                                                <strong class="dz-privacy-blur text-success">+ {{ $money($forecast['base_planned_income']) }}</strong>
                                            </li>
                                        @endif
                                        @foreach ($forecast['recurring_incomes_items'] as $recInc)
                                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-bottom border-light-subtle" style="background: transparent;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span style="font-size: 0.95rem;">🟢</span>
                                                    <span class="fw-semibold text-body">{{ $recInc['description'] }}</span>
                                                    @if ($recInc['day_of_month'])
                                                        <span class="badge rounded-pill bg-light text-secondary border ms-1" style="font-size: 0.65rem;">dia {{ $recInc['day_of_month'] }}</span>
                                                    @endif
                                                </div>
                                                <strong class="dz-privacy-blur text-success">+ {{ $money($recInc['amount']) }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer py-2" style="border-top: 1px solid var(--dz-border-subtle);">
                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
@endif
