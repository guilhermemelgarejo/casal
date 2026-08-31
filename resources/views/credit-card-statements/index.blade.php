@php
    $selectedCard = $filterCardId !== null ? $cardAccounts->firstWhere('id', $filterCardId) : null;
    $openSummaries = collect($cardPickerSummaries)->filter();
    $openAmount = (float) $openSummaries->sum(fn ($summary) => (float) ($summary['remaining'] ?? $summary['spent_total'] ?? 0));
    $listedTotal = $filterCardId !== null ? (float) $invoiceCycles->sum(fn ($cycle) => (float) $cycle->spent_total) : 0.0;
    $listedPaid = $filterCardId !== null ? $invoiceCycles->filter(fn ($cycle) => $cycle->meta?->isPaid() ?? false)->count() : 0;
    $listedPartial = $filterCardId !== null ? $invoiceCycles->filter(fn ($cycle) => $cycle->meta?->hasPartialPayments() ?? false)->count() : 0;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="dz-page-title">Faturas de Cartão</h1>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Ciclos, vencimentos, compras parceladas e pagamentos
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        @if ($cardAccounts->isNotEmpty())
            <a href="{{ route('accounts.index') }}" class="dz-btn dz-btn-outline">
                Ver Cartões ↗
            </a>
        @endif
    </x-slot>

    <div class="container-xxl py-4 px-3 px-lg-4 faturas-page">
        @if (session('success'))
            <x-alert type="success" class="mb-4" :message="session('success')" />
        @endif

        @if ($errors->has('payment'))
            <x-alert type="danger" class="mb-4" :message="$errors->first('payment')" />
        @endif

        <!-- TOP KPIS DUOZEN 2.0 -->
        <section class="dz-kpi-grid mb-4">
            <!-- Total em Aberto -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Total em Aberto</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--warning">
                        💳
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-warning dz-privacy-blur">
                        R$ {{ number_format($openAmount, 2, ',', '.') }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>{{ $openSummaries->count() }} ciclo(s) em aberto</span>
                        @if($pastOpenStatementAccountIds->isNotEmpty())
                            <span class="badge rounded-pill bg-danger-subtle text-danger" style="font-size: 0.65rem;">{{ $pastOpenStatementAccountIds->count() }} atrasada(s)</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Cartão Ativo -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Cartão Selecionado</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                        💎
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-truncate" style="font-size: 1.25rem; color: var(--dz-text-title);" title="{{ $selectedCard ? $selectedCard->name : 'Nenhum' }}">
                        {{ $selectedCard ? $selectedCard->name : 'Todos / Escolha' }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>{{ $cardAccounts->count() }} cartões no sistema</span>
                        <a href="{{ route('accounts.index') }}" style="color: var(--dz-primary); font-weight: 700; text-decoration: none;">Gerenciar ↗</a>
                    </div>
                </div>
            </div>

            <!-- Faturas Listadas -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Ciclos Listados</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                        📋
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value" style="color: var(--dz-text-title);">
                        {{ $filterCardId !== null ? $invoiceCycles->count() : '—' }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>{{ $listedPaid }} paga(s) • {{ $listedPartial }} parcial(is)</span>
                        <span class="fw-bold {{ $filterCardId !== null ? 'dz-privacy-blur' : '' }}">{{ $filterCardId !== null ? 'R$ ' . number_format($listedTotal, 2, ',', '.') : '' }}</span>
                    </div>
                </div>
            </div>

            <!-- Atenção / Pendências -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Meses Anteriores</span>
                    <div class="dz-kpi-card__icon-box" style="background: rgba(244, 63, 94, 0.15); color: var(--dz-danger);">
                        ⚠️
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value {{ $pastOpenStatementAccountIds->isNotEmpty() ? 'text-danger' : 'text-success' }}">
                        {{ $pastOpenStatementAccountIds->count() }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>{{ $pastOpenStatementAccountIds->isNotEmpty() ? 'Cartão(ões) com pendências' : 'Tudo em dia!' }}</span>
                    </div>
                </div>
            </div>
        </section>

        @if ($cardAccounts->isEmpty())
            <div class="text-center py-5 px-3 mb-4" style="background: var(--dz-bg-card-subtle); border-radius: var(--dz-radius-lg); border: 1px dashed var(--dz-border);">
                <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.5rem;">
                    💳
                </div>
                <h3 class="h5 mb-2 fw-bold" style="color: var(--dz-text-title);">Nenhum cartão cadastrado</h3>
                <p class="small mb-4 mx-auto" style="max-width: 26rem; color: var(--dz-text-secondary);">
                    Cadastre um <strong>cartão de crédito</strong> em <a href="{{ route('accounts.index') }}" style="color: var(--dz-primary); font-weight: 600;">Contas</a> e registre compras no <a href="{{ route('dashboard') }}" style="color: var(--dz-primary); font-weight: 600;">Painel</a> para ver as faturas aqui.
                </p>
                <a href="{{ route('accounts.index') }}" class="dz-btn dz-btn-primary px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Cadastrar um cartão de crédito em Contas">
                    Ir para Contas
                </a>
            </div>
        @else
            @if ($filterCardId === null)
                <div class="dz-card cc-picker-hero mb-4 overflow-hidden" style="background: var(--dz-bg-card); border-radius: var(--dz-radius-lg); border: 1px solid var(--dz-border);">
                    <div class="cc-picker-hero-head p-3 p-lg-4" style="background: var(--dz-bg-card); border-bottom: 1px solid var(--dz-border-subtle);">
                        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.25rem;">
                                    💳
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <h3 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title); font-size: 1.05rem;">Escolher cartão</h3>
                                        <span class="badge rounded-pill" style="background: var(--dz-primary-subtle); color: var(--dz-primary); font-size: 0.72rem; font-weight: 700; border: 1px solid var(--dz-primary-border);">
                                            {{ $cardAccounts->count() }} {{ $cardAccounts->count() === 1 ? 'cartão' : 'cartões' }}
                                        </span>
                                    </div>
                                    <span style="font-size: 0.8rem; color: var(--dz-text-secondary); display: block; margin-top: 0.2rem;">
                                        Um cartão de cada vez — depois veja os ciclos, totais e pagamentos.
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap flex-shrink-0">
                                <button
                                    type="button"
                                    class="dz-btn dz-btn-primary"
                                    style="font-size: 0.8rem; padding: 0.42rem 0.95rem; font-weight: 700;"
                                    title="Criar fatura manual para um período sem compras no cartão"
                                    data-bs-toggle="modal"
                                    data-bs-target="#newAvulsaStatementModal"
                                >
                                    + Cadastrar fatura avulsa
                                </button>
                                <a href="{{ route('accounts.index') }}" class="dz-btn dz-btn-outline" style="font-size: 0.8rem; padding: 0.42rem 0.9rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Ir para Contas e gerenciar cartões">
                                    Gerenciar cartões ↗
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="cc-picker-hero-body p-3 p-lg-4" style="background: var(--dz-bg-card);">
                        <div class="cc-picker-grid">
                            @foreach ($cardAccounts as $ca)
                                @include('credit-card-statements.partials.cc-picker-card', [
                                    'account' => $ca,
                                    'compact' => false,
                                    'active' => false,
                                    'pickerSummary' => $cardPickerSummaries[$ca->id] ?? null,
                                    'hasPastOpenStatements' => $pastOpenStatementAccountIds->contains($ca->id),
                                ])
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="cc-picker-toolbar dz-card p-3 p-lg-4 mb-4" style="background: var(--dz-bg-card); border-radius: var(--dz-radius-lg); border: 1px solid var(--dz-border);">
                    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-3 pb-3 border-bottom" style="border-color: var(--dz-border-subtle) !important;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary flex-shrink-0" style="width: 38px; height: 38px; font-size: 1.1rem;">
                                💳
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h3 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title); font-size: 0.98rem;">Trocar cartão</h3>
                                    <span class="badge rounded-pill" style="background: var(--dz-primary-subtle); color: var(--dz-primary); font-size: 0.72rem; font-weight: 700; border: 1px solid var(--dz-primary-border);">
                                        {{ $cardAccounts->count() }} disponíveis
                                    </span>
                                </div>
                                <span style="font-size: 0.78rem; color: var(--dz-text-secondary); display: block; margin-top: 0.15rem;">
                                    Selecione outro cartão para alternar a exibição das faturas.
                                </span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center flex-shrink-0">
                            <button
                                type="button"
                                class="dz-btn dz-btn-primary"
                                style="font-size: 0.78rem; padding: 0.4rem 0.85rem; font-weight: 700;"
                                title="Criar fatura manual para um período sem compras no cartão"
                                data-bs-toggle="modal"
                                data-bs-target="#newAvulsaStatementModal"
                            >
                                + Cadastrar fatura avulsa
                            </button>
                            <a href="{{ route('credit-card-statements.index') }}" class="dz-btn dz-btn-outline" style="font-size: 0.78rem; padding: 0.4rem 0.85rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="Voltar à grade de escolha de cartão">
                                Voltar à escolha
                            </a>
                        </div>
                    </div>
                    <div class="cc-picker-grid cc-picker-grid--toolbar justify-content-start">
                        @foreach ($cardAccounts as $ca)
                            @include('credit-card-statements.partials.cc-picker-card', [
                                'account' => $ca,
                                'compact' => true,
                                'active' => (int) $filterCardId === (int) $ca->id,
                                'pickerSummary' => $cardPickerSummaries[$ca->id] ?? null,
                                'hasPastOpenStatements' => $pastOpenStatementAccountIds->contains($ca->id),
                            ])
                        @endforeach
                    </div>
                </div>
            @endif

                        @if ($filterCardId !== null)
                            @if ($invoiceCycles->isEmpty())
                                <div class="cc-statements-empty-note">
                                    <span class="cc-statements-empty-note__icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" /></svg>
                                    </span>
                                    <div>
                                        <strong>Nenhuma fatura com despesas neste cartão</strong>
                                        <span>Registre compras no <a href="{{ route('dashboard') }}">Painel</a> com este cartão ou escolha outro cartão.</span>
                                    </div>
                                </div>
                            @else
                        <div class="vstack gap-3">
                                    @foreach ($invoiceCycles as $cycle)
                                        @php
                                            $cycleSubtitle = $cycle->account->name.' — '.sprintf('%02d/%d', $cycle->reference_month, $cycle->reference_year);
                                        @endphp
                                        @php
                                            $meta = $cycle->meta;
                                            $isPaid = $meta?->isPaid() ?? false;
                                            $hasPayments = $meta && $meta->paymentTransactions->isNotEmpty();
                                            $isFullyPaidByTx = $meta?->isFullyPaidByPayments() ?? false;
                                            $showPaymentForms = $meta === null
                                                || (! $isFullyPaidByTx
                                                    && ! ($meta->paid_at !== null && $meta->paymentTransactions->isEmpty()));
                                            $remaining = $meta ? $meta->remainingToPay() : (float) $cycle->spent_total;
                                            $virtualDue = $cycle->account->defaultStatementDueDate($cycle->reference_month, $cycle->reference_year);
                                            if ($meta?->due_date) {
                                                $dueForDisplay = $meta->due_date;
                                                $dueIsSuggestion = false;
                                            } elseif ($virtualDue) {
                                                $dueForDisplay = $virtualDue;
                                                $dueIsSuggestion = true;
                                            } else {
                                                $dueForDisplay = null;
                                                $dueIsSuggestion = false;
                                            }
                                            $editDueValue = $meta?->due_date?->format('Y-m-d')
                                                ?? ($virtualDue?->format('Y-m-d') ?? '');
                                            $payHint = ($hasPayments && ! $isFullyPaidByTx)
                                                ? 'Valor sugerido: restante (R$ '.number_format($remaining, 2, ',', '.').').'
                                                : 'Valor sugerido: total da fatura (R$ '.number_format($cycle->spent_total, 2, ',', '.').').';
                                            $payDefaultAmount = $hasPayments && ! $isFullyPaidByTx ? $remaining : (float) $cycle->spent_total;
                                            $payAmtPlaceholder = 'Padrão: R$ '.number_format($payDefaultAmount, 2, ',', '.');
                                            if ($isPaid) {
                                                $statementHeaderClass = 'cc-statement-header--paid';
                                                $statementStatusLabel = 'Paga';
                                                $statementStatusClass = 'cc-statement-status--paid';
                                            } elseif ($hasPayments) {
                                                $statementHeaderClass = 'cc-statement-header--partial';
                                                $statementStatusLabel = 'Parcial';
                                                $statementStatusClass = 'cc-statement-status--partial';
                                            } else {
                                                $statementHeaderClass = 'cc-statement-header--open';
                                                $statementStatusLabel = 'Aberta';
                                                $statementStatusClass = 'cc-statement-status--open';
                                            }
                                        @endphp
                                        <div
                                            id="statement-cycle-{{ $cycle->account->id }}-{{ $cycle->reference_year }}-{{ $cycle->reference_month }}"
                                            class="card cc-statement-card p-3 mb-2 shadow-sm"
                                        >
                                            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                                                <!-- Lado Esquerdo: Identificação, Status e Vencimento -->
                                                <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3 min-w-0">
                                                    <span class="cc-statement-icon" aria-hidden="true" style="width: 32px; height: 32px; border-radius: var(--dz-radius-md); background: rgba(124, 58, 237, 0.1); color: var(--dz-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                                    </span>
                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                        <strong class="text-body fw-bold" style="font-size: 0.95rem;">{{ $cycle->account->name }}</strong>
                                                        <span class="text-secondary small">Ref. {{ sprintf('%02d/%d', $cycle->reference_month, $cycle->reference_year) }}</span>
                                                        <span class="cc-statement-status {{ $statementStatusClass }}">{{ $statementStatusLabel }}</span>
                                                        @if ($meta?->is_avulsa)
                                                            <span class="cc-statement-status cc-statement-status--muted">Avulsa</span>
                                                        @endif
                                                        <span class="text-secondary small ms-md-2">
                                                            <strong>Vencimento:</strong>
                                                            @if ($dueForDisplay)
                                                                {{ $dueForDisplay->format('d/m/Y') }} {{ $dueIsSuggestion ? '(Sug.)' : '' }}
                                                            @else
                                                                —
                                                            @endif
                                                        </span>
                                                        @if ($hasPayments && ! $isPaid)
                                                            <span class="text-info small ms-1" style="font-size: 0.75rem;">(Pago: R$ {{ number_format((float) $meta->paymentsTotal(), 2, ',', '.') }} · Resta: R$ {{ number_format($remaining, 2, ',', '.') }})</span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Lado Direito: Total da Fatura e Ações -->
                                                <div class="d-flex align-items-center gap-3 flex-wrap justify-content-between justify-content-lg-end">
                                                    <div class="text-lg-end">
                                                        <div class="cc-statement-total fw-bold text-body duozen-privacy-blur" style="font-size: 1.15rem; letter-spacing: -0.02em; line-height: 1.1;">R$ {{ number_format($cycle->spent_total, 2, ',', '.') }}</div>
                                                    </div>

                                                    <!-- Botões de Ação -->
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        @if ($showPaymentForms)
                                                            <button
                                                                type="button"
                                                                class="dz-btn dz-btn-primary"
                                                                style="font-size: 0.78rem; padding: 0.35rem 0.85rem; font-weight: 700;"
                                                                title="Registrar um pagamento parcial ou total desta fatura"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#payStatementModal"
                                                                data-pay-action="{{ route('credit-card-statements.attach-payment', [$cycle->account, $cycle->reference_year, $cycle->reference_month]) }}"
                                                                data-pay-subtitle="{{ $cycle->account->name }} — {{ sprintf('%02d/%d', $cycle->reference_month, $cycle->reference_year) }}"
                                                                data-pay-hint="{{ $payHint }}"
                                                                data-pay-amount-placeholder="{{ $payAmtPlaceholder }}"
                                                                data-pay-date-default="{{ now()->format('Y-m-d') }}"
                                                            >💳 Pagamento</button>
                                                        @endif

                                                        @unless ($meta?->is_avulsa)
                                                            <button
                                                                type="button"
                                                                class="dz-btn dz-btn-outline"
                                                                style="font-size: 0.78rem; padding: 0.35rem 0.8rem;"
                                                                title="Ver lançamentos que compõem esta fatura"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#statementItemsModal"
                                                                data-statement-subtitle="{{ $cycleSubtitle }}"
                                                                data-statement-cycle-key="{{ $cycle->cycle_key }}"
                                                            >📋 Itens da fatura</button>
                                                        @endunless

                                                        <button
                                                            type="button"
                                                            class="dz-btn dz-btn-outline"
                                                            style="font-size: 0.78rem; padding: 0.35rem 0.75rem;"
                                                            title="Alterar vencimento ou dados da fatura avulsa"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editStatementModal"
                                                            data-edit-action="{{ route('credit-card-statements.update', [$cycle->account, $cycle->reference_year, $cycle->reference_month]) }}"
                                                            data-edit-subtitle="{{ $cycle->account->name }} — {{ sprintf('%02d/%d', $cycle->reference_month, $cycle->reference_year) }}"
                                                            data-edit-due="{{ $editDueValue }}"
                                                            data-edit-is-avulsa="{{ $meta?->is_avulsa ? '1' : '0' }}"
                                                            data-edit-can-edit="{{ $meta?->canEditAvulsaFields() ? '1' : '0' }}"
                                                            data-edit-total="{{ $meta?->is_avulsa ? number_format((float) $meta->spent_total, 2, ',', '.') : '' }}"
                                                        >✏️ Editar</button>

                                                        @if ($meta?->is_avulsa)
                                                            <form
                                                                action="{{ route('credit-card-statements.destroy', [$cycle->account, $cycle->reference_year, $cycle->reference_month]) }}"
                                                                method="POST"
                                                                class="d-inline"
                                                                data-confirm="Excluir esta fatura avulsa? Esta ação não pode ser desfeita."
                                                                data-confirm-title="Excluir fatura avulsa"
                                                                data-confirm-accept="Sim, excluir"
                                                                data-confirm-cancel="Cancelar"
                                                                data-confirm-icon="warning"
                                                            >
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dz-btn dz-btn-outline" style="font-size: 0.78rem; padding: 0.35rem 0.75rem; color: var(--dz-danger); border-color: rgba(244, 63, 94, 0.3);" title="Excluir esta fatura avulsa">🗑️ Excluir</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="modal fade" id="statementItemsModal" tabindex="-1" aria-labelledby="statementItemsModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
                                    <div class="modal-content cc-statement-items-modal">
                                        <div class="modal-header align-items-start">
                                            <div class="min-w-0 pe-2">
                                                <h2 class="modal-title h5 mb-0" id="statementItemsModalLabel">Itens desta fatura</h2>
                                                <p class="small text-secondary mb-0 mt-2 fw-semibold" id="statementItemsSubtitle"></p>
                                            </div>
                                            <button type="button" class="btn-close flex-shrink-0 mt-1" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="table-responsive cc-statement-items-table-wrap">
                                                <table class="table table-hover align-middle mb-0 cc-statement-items-table">
                                                    <thead>
                                                        <tr>
                                                            <th class="ps-3">Data compra</th>
                                                            <th>Descrição</th>
                                                            <th>Parcela</th>
                                                            <th>Ref.</th>
                                                            <th class="text-end">Valor nesta fatura</th>
                                                            <th class="text-end pe-3">Lançamentos</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="statementItemsTbody"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar a lista de itens" data-bs-dismiss="modal">Fechar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="payStatementModal" tabindex="-1" aria-labelledby="payStatementModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                                    <div class="modal-content cc-statement-form-modal">
                                        <form id="payStatementForm" method="POST" action="#">
                                            @csrf
                                            <div class="modal-header cc-statement-form-modal__head">
                                                <div>
                                                    <span class="cc-statement-form-modal__kicker">Fatura</span>
                                                    <h2 class="modal-title h5 mb-0" id="payStatementModalLabel">Pagamento da fatura</h2>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="small text-secondary mb-2" id="payStatementSubtitle"></p>
                                                <p class="small text-secondary mb-3" id="payStatementHint"></p>
                                                <div class="vstack gap-3">
                                                    <div>
                                                        <x-input-label for="payStatementAccountId" value="Conta" />
                                                        <select id="payStatementAccountId" name="account_id" class="form-select mt-1" required>
                                                            <option value="">Selecione…</option>
                                                            @foreach ($regularAccounts as $ra)
                                                                <option value="{{ $ra->id }}" @selected((string) old('account_id') === (string) $ra->id)>{{ $ra->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                                                    </div>
                                                    <div>
                                                        <x-input-label for="payStatementPaymentMethod" value="Forma de pagamento" />
                                                        <select id="payStatementPaymentMethod" name="payment_method" class="form-select mt-1" required>
                                                            @foreach (\App\Support\PaymentMethods::forRegularAccounts() as $pm)
                                                                <option value="{{ $pm }}" @selected(old('payment_method') === $pm)>{{ $pm }}</option>
                                                            @endforeach
                                                        </select>
                                                        <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                                                    </div>
                                                    <p class="small text-secondary mb-0">
                                                        Categoria: <strong>{{ \App\Models\Category::NAME_CREDIT_CARD_INVOICE_PAYMENT }}</strong> (fixa para pagamento de fatura).
                                                    </p>
                                                    <div>
                                                        <x-input-label for="payStatementPaidDate" value="Data do pagamento" />
                                                        <input type="text" id="payStatementPaidDate" name="paid_date" data-duozen-flatpickr="date" class="form-control mt-1" required autocomplete="off" value="{{ old('paid_date', now()->format('Y-m-d')) }}">
                                                        <x-input-error :messages="$errors->get('paid_date')" class="mt-2" />
                                                    </div>
                                                    <div>
                                                        <x-input-label for="payStatementAmount" value="Valor (opcional)" />
                                                        <input type="text" inputmode="decimal" id="payStatementAmount" name="amount" class="form-control mt-1" value="{{ old('amount') }}" placeholder="">
                                                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                                    </div>
                                                </div>
                                                <p class="small text-secondary mt-3 mb-0">Para desfazer um pagamento, exclua o lançamento correspondente no <a href="{{ route('dashboard') }}">Painel</a>.</p>
                                            </div>
                                            <div class="modal-footer cc-statement-form-modal__footer">
                                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar sem registrar pagamento" data-bs-dismiss="modal">Cancelar</button>
                                                <x-primary-button type="submit" class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Registrar o pagamento desta fatura como lançamento">Criar lançamento</x-primary-button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="editStatementModal" tabindex="-1" aria-labelledby="editStatementModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content cc-statement-form-modal">
                                        <form id="editStatementForm" method="POST" action="#">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header cc-statement-form-modal__head">
                                                <div>
                                                    <span class="cc-statement-form-modal__kicker">Ciclo</span>
                                                    <h2 class="modal-title h5 mb-0" id="editStatementModalLabel">Editar fatura</h2>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="small text-secondary mb-3" id="editStatementSubtitle"></p>
                                                <div class="vstack gap-3 mb-0">
                                                    <div id="editStatementTotalWrap" class="d-none">
                                                        <x-input-label for="editStatementTotal" value="Total da fatura (avulsa)" />
                                                        <input type="text" inputmode="decimal" name="spent_total" id="editStatementTotal" class="form-control mt-1" value="{{ old('spent_total') }}">
                                                        <x-input-error :messages="$errors->get('spent_total')" class="mt-2" />
                                                    </div>

                                                    <x-input-label for="editStatementDue" value="Vencimento" />
                                                    <input type="text" name="due_date" id="editStatementDue" data-duozen-flatpickr="date" class="form-control mt-1" autocomplete="off" value="{{ old('due_date') }}">
                                                    <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                                                </div>
                                                <p class="small text-secondary mt-3 mb-0 d-none" id="editStatementLockedHint">
                                                    Esta fatura avulsa não pode mais ser editada após registrar pagamentos.
                                                </p>
                                            </div>
                                            <div class="modal-footer cc-statement-form-modal__footer">
                                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar sem salvar alterações" data-bs-dismiss="modal">Cancelar</button>
                                                <x-primary-button type="submit" class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Salvar vencimento e dados da fatura">Salvar</x-primary-button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <p class="small text-secondary mt-3 mb-0">
                                <a href="{{ route('dashboard') }}">Painel</a> — em faturas normais, o total é calculado pelos itens do cartão. Em fatura <strong>avulsa</strong>, o total pode ser ajustado até existir um pagamento.
                            </p>
                        @endif

                        <div class="modal fade" id="newAvulsaStatementModal" tabindex="-1" aria-labelledby="newAvulsaStatementModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content cc-statement-form-modal">
                                    <form method="POST" action="{{ route('credit-card-statements.store-avulsa-direct') }}">
                                        @csrf
                                        <input type="hidden" name="_form" value="cc-statement-avulsa">
                                        <div class="modal-header cc-statement-form-modal__head">
                                            <div>
                                                <span class="cc-statement-form-modal__kicker">Manual</span>
                                                <h2 class="modal-title h5 mb-0" id="newAvulsaStatementModalLabel">Cadastrar fatura avulsa</h2>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small text-secondary mb-3">
                                                Use para registrar uma fatura sem itens lançados. A referência (mês/ano) não poderá ser alterada depois.
                                            </p>
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <x-input-label for="avulsaAccountId" value="Cartão de crédito" />
                                                    <select id="avulsaAccountId" name="account_id" class="form-select mt-1" required>
                                                        <option value="">Selecione o cartão…</option>
                                                        @foreach ($cardAccounts as $ca)
                                                            <option value="{{ $ca->id }}" @selected((string) old('account_id', $filterCardId ?? ($cardAccounts->count() === 1 ? $cardAccounts->first()->id : null)) === (string) $ca->id)>{{ $ca->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                                                </div>
                                                <div class="col-6">
                                                    <x-input-label for="avulsaRefMonth" value="Mês de referência" />
                                                    <select id="avulsaRefMonth" name="reference_month" class="form-select mt-1" required>
                                                        @for ($m = 1; $m <= 12; $m++)
                                                            <option value="{{ $m }}" @selected((int) old('reference_month', now()->month) === $m)>{{ sprintf('%02d', $m) }}</option>
                                                        @endfor
                                                    </select>
                                                    <x-input-error :messages="$errors->get('reference_month')" class="mt-2" />
                                                </div>
                                                <div class="col-6">
                                                    <x-input-label for="avulsaRefYear" value="Ano de referência" />
                                                    <input type="number" id="avulsaRefYear" name="reference_year" class="form-control mt-1" min="2000" max="2100" required value="{{ old('reference_year', now()->year) }}">
                                                    <x-input-error :messages="$errors->get('reference_year')" class="mt-2" />
                                                </div>
                                                <div class="col-12">
                                                    <x-input-label for="avulsaTotal" value="Total da fatura" />
                                                    <input type="text" inputmode="decimal" id="avulsaTotal" name="spent_total" class="form-control mt-1" required value="{{ old('spent_total') }}" placeholder="Ex.: 1234,56">
                                                    <x-input-error :messages="$errors->get('spent_total')" class="mt-2" />
                                                </div>
                                                <div class="col-12">
                                                    <x-input-label for="avulsaDue" value="Vencimento (opcional)" />
                                                    <input type="text" id="avulsaDue" name="due_date" data-duozen-flatpickr="date" class="form-control mt-1" autocomplete="off" value="{{ old('due_date') }}">
                                                    <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer cc-statement-form-modal__footer">
                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar sem criar fatura" data-bs-dismiss="modal">Cancelar</button>
                                            <x-primary-button type="submit" class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Criar a fatura avulsa com o total indicado">Cadastrar</x-primary-button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        @php
                            $openEdit = session('open_statement_edit');
                            $openEditAccount = $openEdit ? $cardAccounts->firstWhere('id', $openEdit['account_id']) : null;
                            $openEditReopen = $openEdit && $openEditAccount;
                            $openEditUpdateUrl = $openEditReopen
                                ? route('credit-card-statements.update', [$openEditAccount, $openEdit['reference_year'], $openEdit['reference_month']])
                                : '';
                            $openEditSubtitleJs = $openEditReopen
                                ? $openEditAccount->name.' — '.sprintf('%02d/%d', $openEdit['reference_month'], $openEdit['reference_year'])
                                : '';

                            $openPay = session('open_statement_payment');
                            $openPayCardAccount = $openPay ? $cardAccounts->firstWhere('id', $openPay['account_id']) : null;
                            $openPayCycle = ($openPayCardAccount && $invoiceCycles->isNotEmpty())
                                ? $invoiceCycles->first(function ($c) use ($openPay) {
                                    return (int) $c->account->id === (int) $openPay['account_id']
                                        && (int) $c->reference_year === (int) $openPay['reference_year']
                                        && (int) $c->reference_month === (int) $openPay['reference_month'];
                                })
                                : null;
                            $openPayReopen = $openPayCardAccount !== null && $openPayCycle !== null;
                            if ($openPayReopen) {
                                $opMeta = $openPayCycle->meta;
                                $opHasPayments = $opMeta && $opMeta->paymentTransactions->isNotEmpty();
                                $opIsFullyPaidByTx = $opMeta?->isFullyPaidByPayments() ?? false;
                                $opRemaining = $opMeta ? $opMeta->remainingToPay() : (float) $openPayCycle->spent_total;
                                $openPayActionUrl = route('credit-card-statements.attach-payment', [$openPayCycle->account, $openPayCycle->reference_year, $openPayCycle->reference_month]);
                                $openPaySubtitleJs = $openPayCycle->account->name.' — '.sprintf('%02d/%d', $openPayCycle->reference_month, $openPayCycle->reference_year);
                                $openPayHintJs = ($opHasPayments && ! $opIsFullyPaidByTx)
                                    ? 'Valor sugerido: restante (R$ '.number_format($opRemaining, 2, ',', '.').').'
                                    : 'Valor sugerido: total da fatura (R$ '.number_format($openPayCycle->spent_total, 2, ',', '.').').';
                                $openPayAmtPlaceholderJs = 'Padrão: R$ '.number_format($opHasPayments && ! $opIsFullyPaidByTx ? $opRemaining : (float) $openPayCycle->spent_total, 2, ',', '.');
                            } else {
                                $openPayActionUrl = '';
                                $openPaySubtitleJs = '';
                                $openPayHintJs = '';
                                $openPayAmtPlaceholderJs = '';
                            }
                        @endphp
                        @push('scripts')
                            <script>
                                window.__invoiceCycleLinesByKey = @json($invoiceCycleLinesByKey ?? []);
                            </script>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    const h = window.location.hash;
                                    if (!h || h.indexOf('statement-cycle-') !== 1) {
                                        return;
                                    }
                                    const id = h.slice(1);
                                    const el = document.getElementById(id);
                                    if (!el) {
                                        return;
                                    }
                                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    el.classList.add('cc-statement-card--flash');
                                    window.setTimeout(function () {
                                        el.classList.remove('cc-statement-card--flash');
                                    }, 2400);
                                });
                            </script>
                            <script>
                                (function () {
                                    const maskMoneyPtBr = (inputEl) => {
                                        if (!inputEl) return;

                                        const format = (raw) => {
                                            let s = String(raw || '');
                                            // Mantém apenas dígitos.
                                            s = s.replace(/[^\d]/g, '');
                                            if (s === '') return '';

                                            // Converte para centavos (sempre 2 casas).
                                            while (s.length < 3) s = '0' + s;
                                            const cents = s.slice(-2);
                                            let intPart = s.slice(0, -2);

                                            // Remove zeros à esquerda (mas mantém pelo menos 0).
                                            intPart = intPart.replace(/^0+(?=\d)/, '');
                                            if (intPart === '') intPart = '0';

                                            // Milhar com ponto.
                                            intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                            return `${intPart},${cents}`;
                                        };

                                        const apply = () => {
                                            const cur = inputEl.value;
                                            const next = format(cur);
                                            inputEl.value = next;
                                        };

                                        inputEl.addEventListener('input', apply);
                                        inputEl.addEventListener('blur', apply);
                                    };

                                    const itemsModalEl = document.getElementById('statementItemsModal');
                                    const itemsSubtitleEl = document.getElementById('statementItemsSubtitle');
                                    const itemsTbody = document.getElementById('statementItemsTbody');
                                    const linesPayload = window.__invoiceCycleLinesByKey || {};
                                    if (itemsModalEl && itemsSubtitleEl && itemsTbody) {
                                        itemsModalEl.addEventListener('show.bs.modal', function (e) {
                                            const btn = e.relatedTarget;
                                            if (!btn || !btn.getAttribute('data-statement-cycle-key')) return;
                                            itemsSubtitleEl.textContent = btn.getAttribute('data-statement-subtitle') || '';
                                            const cycleKey = btn.getAttribute('data-statement-cycle-key');
                                            const lines = Array.isArray(linesPayload[cycleKey]) ? linesPayload[cycleKey] : [];
                                            itemsTbody.innerHTML = '';
                                            if (!lines.length) {
                                                const tr = document.createElement('tr');
                                                tr.innerHTML = '<td colspan="6" class="text-center py-4 px-3">' +
                                                    '<div class="cc-statement-items-empty small text-secondary mb-0">Nenhum lançamento neste ciclo.</div></td>';
                                                itemsTbody.appendChild(tr);
                                                return;
                                            }
                                            lines.forEach(function (row) {
                                                const tr = document.createElement('tr');
                                                const url = row.transactions_url || '#';

                                                function td(className, text) {
                                                    const cell = document.createElement('td');
                                                    if (className) cell.className = className;
                                                    cell.textContent = text == null || text === '' ? '—' : String(text);
                                                    return cell;
                                                }

                                                tr.appendChild(td('text-nowrap small ps-3 text-secondary', row.date));
                                                tr.appendChild(td('small', row.description));
                                                tr.appendChild(td('small text-nowrap text-secondary', row.parcel_label));
                                                tr.appendChild(td('small text-nowrap text-secondary', row.ref_label));

                                                const tdAmt = document.createElement('td');
                                                const isCredit = !!row.is_credit;
                                                tdAmt.className = 'text-end small fw-semibold text-nowrap ' + (isCredit ? 'text-success' : 'text-body');
                                                tdAmt.textContent = (isCredit ? '+ ' : '') + 'R$ ' + (isCredit ? (row.amount_abs_str || row.amount_str || '') : (row.amount_abs_str || row.amount_str || ''));
                                                tr.appendChild(tdAmt);

                                                const tdLink = document.createElement('td');
                                                tdLink.className = 'text-end pe-3';
                                                const a = document.createElement('a');
                                                a.className = 'btn btn-sm btn-outline-primary rounded-pill px-3';
                                                a.href = url;
                                                a.target = '_blank';
                                                a.rel = 'noopener noreferrer';
                                                a.textContent = 'Abrir';
                                                tdLink.appendChild(a);
                                                tr.appendChild(tdLink);

                                                itemsTbody.appendChild(tr);
                                            });
                                        });
                                    }

                                    const editModalEl = document.getElementById('editStatementModal');
                                    const editForm = document.getElementById('editStatementForm');
                                    const editSubtitleEl = document.getElementById('editStatementSubtitle');
                                    const editDueInput = document.getElementById('editStatementDue');
                                    const editTotalWrap = document.getElementById('editStatementTotalWrap');
                                    const editTotalInput = document.getElementById('editStatementTotal');
                                    const editLockedHint = document.getElementById('editStatementLockedHint');
                                    maskMoneyPtBr(editTotalInput);
                                    if (editModalEl && editForm) {
                                        editModalEl.addEventListener('show.bs.modal', function (e) {
                                            const btn = e.relatedTarget;
                                            if (!btn || !btn.hasAttribute('data-edit-action')) return;
                                            editForm.action = btn.getAttribute('data-edit-action');
                                            if (editSubtitleEl) {
                                                editSubtitleEl.textContent = btn.getAttribute('data-edit-subtitle') || '';
                                            }
                                            if (editDueInput) {
                                                const dueRaw = btn.getAttribute('data-edit-due') || '';
                                                if (typeof window.duozenFlatpickrSetDate === 'function') {
                                                    window.duozenFlatpickrSetDate(editDueInput, dueRaw);
                                                } else {
                                                    editDueInput.value = dueRaw;
                                                }
                                            }

                                            const isAvulsa = (btn.getAttribute('data-edit-is-avulsa') || '') === '1';
                                            const canEdit = (btn.getAttribute('data-edit-can-edit') || '') === '1';
                                            if (editTotalWrap) {
                                                editTotalWrap.classList.toggle('d-none', !isAvulsa);
                                            }
                                            if (editTotalInput) {
                                                editTotalInput.value = isAvulsa ? (btn.getAttribute('data-edit-total') || '') : '';
                                                editTotalInput.disabled = !isAvulsa || !canEdit;
                                            }
                                            if (editDueInput) {
                                                editDueInput.disabled = isAvulsa && !canEdit;
                                            }
                                            if (editLockedHint) {
                                                editLockedHint.classList.toggle('d-none', !(isAvulsa && !canEdit));
                                            }
                                        });
                                    }

                                    const payModalEl = document.getElementById('payStatementModal');
                                    const payForm = document.getElementById('payStatementForm');
                                    if (payModalEl && payForm) {
                                        const paySubtitleEl = document.getElementById('payStatementSubtitle');
                                        const payHintEl = document.getElementById('payStatementHint');
                                        const payAmountInput = document.getElementById('payStatementAmount');
                                        const payDateInput = document.getElementById('payStatementPaidDate');
                                        const payAccSelect = document.getElementById('payStatementAccountId');
                                        const payPmSelect = document.getElementById('payStatementPaymentMethod');

                                        payModalEl.addEventListener('show.bs.modal', function (e) {
                                            const btn = e.relatedTarget;
                                            if (!btn || !btn.hasAttribute('data-pay-action')) return;
                                            payForm.action = btn.getAttribute('data-pay-action') || '#';
                                            if (paySubtitleEl) {
                                                paySubtitleEl.textContent = btn.getAttribute('data-pay-subtitle') || '';
                                            }
                                            if (payHintEl) {
                                                payHintEl.textContent = btn.getAttribute('data-pay-hint') || '';
                                            }
                                            if (payAmountInput) {
                                                payAmountInput.placeholder = btn.getAttribute('data-pay-amount-placeholder') || '';
                                                payAmountInput.value = '';
                                            }
                                            const ddef = btn.getAttribute('data-pay-date-default');
                                            if (payDateInput && ddef) {
                                                if (typeof window.duozenFlatpickrSetDate === 'function') {
                                                    window.duozenFlatpickrSetDate(payDateInput, ddef);
                                                } else {
                                                    payDateInput.value = ddef;
                                                }
                                            }
                                            if (payAccSelect) {
                                                payAccSelect.value = '';
                                            }
                                            if (payPmSelect && payPmSelect.options.length) {
                                                payPmSelect.selectedIndex = 0;
                                            }
                                        });
                                    }

                                    const avulsaTotalInput = document.getElementById('avulsaTotal');
                                    maskMoneyPtBr(avulsaTotalInput);

                                    @if ($openEditReopen)
                                    document.addEventListener('DOMContentLoaded', function () {
                                        if (!editForm || !editModalEl) return;
                                        editForm.action = {!! json_encode($openEditUpdateUrl) !!};
                                        if (editSubtitleEl) {
                                            editSubtitleEl.textContent = {!! json_encode($openEditSubtitleJs) !!};
                                        }
                                        if (editDueInput) {
                                            const dueOld = {!! json_encode(old('due_date', '')) !!};
                                            if (typeof window.duozenFlatpickrSetDate === 'function') {
                                                window.duozenFlatpickrSetDate(editDueInput, dueOld);
                                            } else {
                                                editDueInput.value = dueOld;
                                            }
                                        }
                                        // Em reabertura por erro, tentamos manter o total (se aplicável).
                                        const totalWrap = document.getElementById('editStatementTotalWrap');
                                        const totalInput = document.getElementById('editStatementTotal');
                                        if (totalWrap && totalInput) {
                                            const oldTotal = {!! json_encode(old('spent_total', '')) !!};
                                            if (oldTotal && String(oldTotal).trim() !== '') {
                                                totalWrap.classList.remove('d-none');
                                                totalInput.value = oldTotal;
                                            }
                                        }
                                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
                                        }
                                    });
                                    @endif

                                    @if ($openPayReopen)
                                    document.addEventListener('DOMContentLoaded', function () {
                                        if (!payForm || !payModalEl) return;
                                        payForm.action = {!! json_encode($openPayActionUrl) !!};
                                        const paySubtitleEl = document.getElementById('payStatementSubtitle');
                                        const payHintEl = document.getElementById('payStatementHint');
                                        const payAmountInput = document.getElementById('payStatementAmount');
                                        if (paySubtitleEl) {
                                            paySubtitleEl.textContent = {!! json_encode($openPaySubtitleJs) !!};
                                        }
                                        if (payHintEl) {
                                            payHintEl.textContent = {!! json_encode($openPayHintJs) !!};
                                        }
                                        if (payAmountInput) {
                                            payAmountInput.placeholder = {!! json_encode($openPayAmtPlaceholderJs) !!};
                                        }
                                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                            bootstrap.Modal.getOrCreateInstance(payModalEl).show();
                                        }
                                    });
                                    @endif

                                    @if (old('_form') === 'cc-statement-avulsa' && $errors->any())
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const avulsaModalEl = document.getElementById('newAvulsaStatementModal');
                                        if (!avulsaModalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
                                        bootstrap.Modal.getOrCreateInstance(avulsaModalEl).show();
                                    });
                                    @endif
                                })();
                            </script>
                        @endpush
                    @endif
    </div>
</x-app-layout>
