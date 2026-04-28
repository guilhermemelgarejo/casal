@php
    try {
        $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $period)->locale(app()->getLocale())->translatedFormat('F \d\e Y');
    } catch (\Throwable $e) {
        $periodLabel = $period;
    }

    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $spendingPressurePct = (float) (($plannedIncomeResolved ?? 0) > 0 ? (((float) $totalExpense / (float) $plannedIncomeResolved) * 100) : 0);
    $spendingPressureBar = $spendingPressurePct > 0 ? max(4, min(100, $spendingPressurePct)) : 0;
    $recurringReminderCount = ($recurringReminders ?? collect())->count();
    $invoiceReminderCount = ($creditCardInvoiceReminders ?? collect())->count();
    $selectedFilterAccount = ($filterAccountId ?? null) !== null
        ? ($accountsSortedForFilter->firstWhere('id', (int) $filterAccountId) ?? null)
        : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="dashboard-header-intro">
            <div class="dashboard-header-text">
                <h2 class="h5 mb-0 dashboard-title">Painel</h2>
                <p class="small text-secondary mb-0 mt-1 dashboard-header-period"><span class="text-body fw-medium">{{ $periodLabel }}</span></p>
            </div>
        </div>
    </x-slot>

    <div class="py-4 dashboard-page">
        <div class="container-xxl px-3 px-lg-4 d-grid gap-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-0 d-flex align-items-start gap-3 rounded-4" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger border-0 shadow-sm mb-0 d-flex align-items-start gap-3 rounded-4" role="alert">
                    <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <span class="pt-1">{{ session('error') }}</span>
                </div>
            @endif
            @if (! empty($txRecurringPrefillBlockedReason ?? null))
                <div class="alert alert-warning border-0 shadow-sm mb-0 rounded-4" role="status">
                    <p class="small mb-0">{{ $txRecurringPrefillBlockedReason }}</p>
                </div>
            @endif
            @if (! empty($txCofrinhoPrefillBlockedReason ?? null))
                <div class="alert alert-warning border-0 shadow-sm mb-0 rounded-4" role="status">
                    <p class="small mb-0">{{ $txCofrinhoPrefillBlockedReason }}</p>
                </div>
            @endif

            <section class="dashboard-hero card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5">
                            <span class="dashboard-hero__badge">Resumo mensal</span>
                            <h3 class="dashboard-hero__title h4 mt-3 mb-2">Acompanhe o mês sem perder o contexto.</h3>
                            <p class="text-secondary mb-0">Receitas, despesas e lançamentos usam os critérios do painel para mostrar a situação de caixa do período.</p>
                        </div>
                        <div class="col-lg-7">
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
                    </div>

                    <div class="dashboard-summary-grid mt-4">
                        <article class="dashboard-summary-card dashboard-summary-card--success">
                            <span class="dashboard-summary-card__label">Receitas</span>
                            <strong class="dashboard-summary-card__value text-success">{{ $money($totalIncome) }}</strong>
                            <span class="dashboard-summary-card__hint">renda planejada: {{ $money($plannedIncomeResolved ?? 0) }}</span>
                        </article>
                        <article class="dashboard-summary-card dashboard-summary-card--danger">
                            <span class="dashboard-summary-card__label">Despesas</span>
                            <strong class="dashboard-summary-card__value text-danger">{{ $money($totalExpense) }}</strong>
                            <span class="dashboard-summary-card__hint">{{ number_format($spendingPressurePct, 1, ',', '.') }}% da renda planejada</span>
                            <div class="progress dashboard-summary-progress mt-2" role="progressbar" aria-label="Pressão de gastos">
                                <div class="progress-bar {{ $spendingPressurePct >= $thresholdPercentage ? 'bg-danger' : ($spendingPressurePct >= 60 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $spendingPressureBar }}%"></div>
                            </div>
                        </article>
                        <article class="dashboard-summary-card dashboard-summary-card--primary">
                            <span class="dashboard-summary-card__label">Resultado</span>
                            <strong class="dashboard-summary-card__value {{ $netResult >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($netResult) }}</strong>
                            <span class="dashboard-summary-card__hint">{{ $periodTransactionCount }} lançamento(s) no filtro</span>
                        </article>
                        <article class="dashboard-summary-card dashboard-summary-card--warning">
                            <span class="dashboard-summary-card__label">Lembretes</span>
                            <strong class="dashboard-summary-card__value">{{ $recurringReminderCount + $invoiceReminderCount }}</strong>
                            <span class="dashboard-summary-card__hint">{{ $recurringReminderCount }} recorrente(s), {{ $invoiceReminderCount }} fatura(s)</span>
                        </article>
                    </div>

                    <div class="dashboard-hero__strip mt-4">
                        <span>{{ ucfirst($periodLabel) }}</span>
                        <span>{{ $selectedFilterAccount ? ($selectedFilterAccount->name.($selectedFilterAccount->isCreditCard() ? ' (cartão)' : '')) : 'Todas as contas' }}</span>
                        <span>Alerta em {{ number_format($thresholdPercentage, 0, ',', '.') }}%</span>
                    </div>
                </div>
            </section>

            <x-cofrinho-promo variant="compact" />

            @if ($filteredRegularAccountBalance !== null)
                <div class="dashboard-balance-panel">
                    <span class="dashboard-balance-panel__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" /></svg>
                    </span>
                    <div class="min-w-0">
                        <span class="dashboard-balance-panel__label">Saldo da conta filtrada</span>
                        <strong class="dashboard-balance-panel__value {{ $filteredRegularAccountBalance >= 0 ? 'text-body' : 'text-danger' }}">R$ {{ number_format($filteredRegularAccountBalance, 2, ',', '.') }}</strong>
                        <span class="dashboard-balance-panel__hint">Considera todos os lançamentos da conta, não apenas o período filtrado.</span>
                    </div>
                </div>
            @endif
            @include('partials.rt-reminder-panel', [
                'reminders' => $recurringReminders ?? collect(),
                'invoiceReminders' => $creditCardInvoiceReminders ?? collect(),
                'month' => $month,
                'year' => $year,
                'embedded' => true,
            ])
            @if($showAlert)
                <div class="alert alert-danger border-0 shadow-sm mb-0 d-flex align-items-start gap-3 rounded-4" role="alert">
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

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden dashboard-tx-list-card">
                <div class="dashboard-tx-list-head px-4 py-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div class="dashboard-tx-list-title min-w-0">
                            <span class="dashboard-tx-list-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10" /></svg>
                            </span>
                            <div class="min-w-0">
                                <span class="dashboard-section-kicker">Movimentação</span>
                                <h3 class="h5 mb-1 fw-semibold">Lançamentos do período</h3>
                                <p class="small text-secondary mb-0">No cartão, o mês do painel filtra pela <strong class="fw-medium text-body">data da compra</strong>; parcelas aparecem numa linha com o total.</p>
                                @if (! empty($focusTransactionId))
                                    <p class="small text-primary mb-0 mt-2">
                                        A mostrar apenas o lançamento aberto a partir da fatura.
                                        <a href="{{ route('dashboard', array_filter(['period' => $period, 'account_id' => $filterAccountId])) }}" class="fw-semibold">Ver todos os lançamentos deste filtro</a>.
                                    </p>
                                @endif
                            </div>
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
                        'emptyHint' => 'Altere o mês no filtro do painel ou registre com <strong class="fw-medium text-body">+ Receita</strong> ou <strong class="fw-medium text-body">+ Despesa</strong>.',
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
                <div class="modal-content dashboard-transfer-modal">
                    <form action="{{ route('accounts.transfer') }}" method="POST" class="d-flex flex-column">
                        @csrf
                        <input type="hidden" name="_form" value="account-transfer">

                        <div class="modal-header align-items-start tx-modal-head dashboard-transfer-modal__head">
                            <div class="pe-3">
                                <h2 class="modal-title h5 mb-1" id="modalAccountTransferLabel">Transferir entre contas</h2>
                                <p class="small text-secondary mb-0 fw-normal">
                                    Registra uma <strong>despesa</strong> na origem e uma <strong>receita</strong> no destino. Apenas contas correntes (não cartão de crédito).
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

                        <div class="modal-footer flex-wrap gap-2 dashboard-transfer-modal__footer">
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
