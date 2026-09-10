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
                    <!-- Destaque do Orçamento Diário -->
                    <div class="p-3 rounded-3 mb-3 text-center" style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle);">
                        <div class="small text-secondary fw-semibold mb-1">Limite diário sugerido para passar o mês</div>
                        <div class="d-flex align-items-baseline justify-content-center gap-2">
                            <span class="h1 fw-extrabold mb-0 dz-privacy-blur {{ $dailyValueClass }}" style="letter-spacing: -0.02em;">
                                {{ $money($forecast['daily_budget']) }}
                            </span>
                            <span class="fw-semibold text-secondary" style="font-size: 1rem;">/ dia</span>
                        </div>

                        @if ($status === 'deficit')
                            <p class="small text-danger fw-semibold mt-2 mb-0">
                                ⚠️ Atenção: Gastos já contratados superam a receita prevista para {{ ucfirst($forecast['target_month_label']) }} em <strong class="dz-privacy-blur">{{ $money(abs($forecast['free_amount'])) }}</strong>.
                            </p>
                        @elseif (! $forecast['has_planned_income_configured'])
                            <p class="small text-warning fw-semibold mt-2 mb-0">
                                ℹ️ Renda planejada não informada. <a href="{{ route('categories.index') }}#orcamento" class="text-decoration-underline" style="color: inherit;">Configurar renda mensal ↗</a>
                            </p>
                        @else
                            <p class="small text-secondary mt-2 mb-0">
                                Saldo livre projetado de <strong class="dz-privacy-blur text-body">{{ $money($forecast['free_amount']) }}</strong> dividido igualmente pelos {{ $forecast['days_remaining_current_month'] }} dias restantes de {{ ucfirst($forecast['current_month_label']) }}.
                            </p>
                        @endif

                        <!-- Barra de Comprometimento -->
                        <div class="mt-3 text-start">
                            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.78rem;">
                                <span class="fw-bold" style="color: var(--dz-text-title);">Comprometimento da Renda Prevista</span>
                                <span class="fw-bold" style="color: {{ $accentColor }};">{{ number_format($forecast['committed_pct'], 1, ',', '.') }}%</span>
                            </div>

                            <div class="dz-progress-bar" style="height: 6px; background: var(--dz-border); border-radius: 9999px;">
                                <div class="dz-progress-bar__fill" style="width: {{ min(100, $forecast['committed_pct']) }}%; background: {{ $accentColor }};"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-2" style="font-size: 0.73rem; color: var(--dz-text-secondary);">
                                <span>Total contratado: <strong class="dz-privacy-blur text-body">{{ $money($forecast['committed_total']) }}</strong></span>
                                <span>Saldo livre: <strong class="dz-privacy-blur text-body">{{ $money(max(0, $forecast['free_amount'])) }}</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 4 Pilares do Cálculo -->
                    <div class="row g-2 mb-3">
                        <!-- 1. Receita Prevista -->
                        <div class="col-6 col-md-3">
                            <div class="p-2 p-md-3 rounded-3 h-100" style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle);">
                                <div class="d-flex align-items-center gap-1 text-success small fw-bold mb-1">
                                    <span>🟢</span>
                                    <span>Receita Prevista</span>
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
                            <div class="p-2 p-md-3 rounded-3 h-100" style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle);">
                                <div class="d-flex align-items-center gap-1 text-danger small fw-bold mb-1">
                                    <span>💳</span>
                                    <span>Faturas Cartão</span>
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
                            <div class="p-2 p-md-3 rounded-3 h-100" style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle);">
                                <div class="d-flex align-items-center gap-1 text-warning-emphasis small fw-bold mb-1">
                                    <span>🔄</span>
                                    <span>Recorrentes</span>
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
                            <div class="p-2 p-md-3 rounded-3 h-100" style="background: var(--dz-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--dz-border-subtle);">
                                <div class="d-flex align-items-center gap-1 text-danger-emphasis small fw-bold mb-1">
                                    <span>📑</span>
                                    <span>Dívidas</span>
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

                    <!-- Listagem Detalhada de Itens -->
                    @php
                        $hasRecIncomes = ! empty($forecast['recurring_incomes_items']);
                        $detailColClass = $hasRecIncomes ? 'col-12 col-md-6 col-lg-3' : 'col-12 col-md-4';
                    @endphp
                    <div class="p-3 rounded-3" style="background: var(--dz-bg-subtle, rgba(0,0,0,0.015)); border: 1px solid var(--dz-border);">
                        <div class="row g-3">
                            @if ($hasRecIncomes)
                                <!-- Receitas Recorrentes Detalhadas -->
                                <div class="{{ $detailColClass }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold small" style="color: var(--dz-text-title);">🟢 Receitas</span>
                                        <a href="{{ route('recurring-transactions.index') }}" style="font-size: 0.7rem; color: var(--dz-primary); text-decoration: none;">Gerenciar ↗</a>
                                    </div>
                                    <ul class="list-unstyled mb-0" style="font-size: 0.75rem;">
                                        @if ((float) ($forecast['base_planned_income'] ?? 0) > 0)
                                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                                <span class="text-truncate me-2 text-secondary">Renda planejada</span>
                                                <strong class="dz-privacy-blur text-success">{{ $money($forecast['base_planned_income']) }}</strong>
                                            </li>
                                        @endif
                                        @foreach ($forecast['recurring_incomes_items'] as $recInc)
                                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                                <span class="text-truncate me-2" title="{{ $recInc['description'] }}">
                                                    {{ $recInc['description'] }}
                                                    @if ($recInc['day_of_month'])
                                                        <span class="text-secondary" style="font-size: 0.68rem;">(dia {{ $recInc['day_of_month'] }})</span>
                                                    @endif
                                                </span>
                                                <strong class="dz-privacy-blur text-success">+ {{ $money($recInc['amount']) }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <!-- Faturas Detalhadas -->
                            <div class="{{ $detailColClass }}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold small" style="color: var(--dz-text-title);">💳 Faturas de Cartão</span>
                                    <a href="{{ route('credit-card-statements.index') }}" style="font-size: 0.7rem; color: var(--dz-primary); text-decoration: none;">Ver todas ↗</a>
                                </div>
                                @if (empty($forecast['card_invoices_items']))
                                    <div class="small text-secondary" style="font-size: 0.75rem;">Sem faturas previstas para o mês.</div>
                                @else
                                    <ul class="list-unstyled mb-0" style="font-size: 0.75rem;">
                                        @foreach ($forecast['card_invoices_items'] as $cardItem)
                                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                                <span class="text-truncate me-2">
                                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: {{ $cardItem['account_color'] }}; margin-right: 4px;"></span>
                                                    {{ $cardItem['account_name'] }}
                                                </span>
                                                <strong class="dz-privacy-blur text-danger">{{ $money($cardItem['amount']) }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <!-- Recorrentes Detalhadas -->
                            <div class="col-12 col-md-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold small" style="color: var(--dz-text-title);">🔄 Despesas Recorrentes</span>
                                    <a href="{{ route('recurring-transactions.index') }}" style="font-size: 0.7rem; color: var(--dz-primary); text-decoration: none;">Gerenciar ↗</a>
                                </div>
                                @if (empty($forecast['recurring_expenses_items']))
                                    <div class="small text-secondary" style="font-size: 0.75rem;">Sem despesas recorrentes ativas.</div>
                                @else
                                    <ul class="list-unstyled mb-0" style="font-size: 0.75rem;">
                                        @foreach ($forecast['recurring_expenses_items'] as $recItem)
                                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                                <span class="text-truncate me-2" title="{{ $recItem['description'] }}">
                                                    {{ $recItem['description'] }}
                                                    @if($recItem['day_of_month'])
                                                        <span class="text-secondary" style="font-size: 0.68rem;">(dia {{ $recItem['day_of_month'] }})</span>
                                                    @endif
                                                </span>
                                                <strong class="dz-privacy-blur text-danger">{{ $money($recItem['amount']) }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <!-- Dívidas Detalhadas -->
                            <div class="col-12 col-md-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold small" style="color: var(--dz-text-title);">📑 Parcelas de Dívidas</span>
                                    <a href="{{ route('debts.index', ['tab' => 'agenda']) }}" style="font-size: 0.7rem; color: var(--dz-primary); text-decoration: none;">Ver agenda ↗</a>
                                </div>
                                @if (empty($forecast['debt_installments_items']))
                                    <div class="small text-secondary" style="font-size: 0.75rem;">Nenhuma parcela com vencimento no mês.</div>
                                @else
                                    <ul class="list-unstyled mb-0" style="font-size: 0.75rem;">
                                        @foreach ($forecast['debt_installments_items'] as $debtItem)
                                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                                <span class="text-truncate me-2" title="{{ $debtItem['debt_name'] }}">
                                                    {{ $debtItem['debt_name'] }}
                                                    <span class="text-secondary" style="font-size: 0.68rem;">(#{{ $debtItem['installment_number'] }})</span>
                                                </span>
                                                <strong class="dz-privacy-blur text-danger">{{ $money($debtItem['amount']) }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
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
