<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 mb-0 tx-page-title">Lançamentos</h2>
            <p class="small text-secondary mb-0 mt-1">Registre receitas e despesas; no cartão, o período da lista segue a <strong class="fw-medium text-body">data da compra</strong> — parcelas do mesmo pagamento aparecem numa linha com o total.</p>
        </div>
    </x-slot>

    <div class="py-4 transactions-page">
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

            @include('partials.rt-reminder-panel', [
                'reminders' => $recurringReminders ?? collect(),
                'invoiceReminders' => $creditCardInvoiceReminders ?? collect(),
                'month' => $selectedMonth,
                'year' => $selectedYear,
            ])

            <div class="card border-0 shadow-sm overflow-hidden tx-index-card">
                <div class="tx-index-head px-4 py-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div class="min-w-0">
                            <h3 class="h5 mb-1 fw-semibold">Lista do período</h3>
                            <p class="small text-secondary mb-0">Demais lançamentos usam o <strong class="fw-medium text-body">mês de referência</strong> escolhido abaixo.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-end flex-shrink-0" id="onboarding-tx-actions" role="group" aria-label="Novo lançamento">
                            @if (($canCreateAccountTransfer ?? false) === true)
                                <button
                                    type="button"
                                    class="btn btn-outline-primary rounded-pill px-3"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalAccountTransfer"
                                >
                                    Transferências
                                </button>
                            @endif
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

                <div class="tx-index-filters px-4 py-3">
                    <form method="GET" action="{{ route('transactions.index') }}" class="row g-3 align-items-end">
                        <div class="col-6 col-md-4 col-lg-2 min-w-0 tx-filter-field">
                            <label class="form-label" for="filter-month">Mês</label>
                            <select
                                id="filter-month"
                                name="month"
                                class="form-select form-select-sm"
                                aria-label="Mês"
                            >
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ (int) $selectedMonth === $m ? 'selected' : '' }}>
                                        {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                    </option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-6 col-md-4 col-lg-2 min-w-0 tx-filter-field">
                            <label class="form-label" for="filter-year">Ano</label>
                            <select
                                id="filter-year"
                                name="year"
                                class="form-select form-select-sm"
                                aria-label="Ano"
                            >
                                @foreach($years as $y)
                                    <option value="{{ $y }}" {{ (int) $selectedYear === (int) $y ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-4 col-lg min-w-0 tx-filter-field">
                            <label class="form-label" for="filter-account">Conta</label>
                            <select
                                id="filter-account"
                                name="account_id"
                                class="form-select form-select-sm"
                                aria-label="Filtrar por conta"
                            >
                                <option value="" {{ $filterAccountId === null ? 'selected' : '' }}>Todas</option>
                                @foreach($accountsSortedForFilter as $acc)
                                    <option value="{{ $acc->id }}" {{ (int) $filterAccountId === (int) $acc->id ? 'selected' : '' }}>
                                        @if($acc->isCreditCard())
                                            {{ $acc->name }} (cartão)
                                        @else
                                            {{ $acc->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-lg-auto">
                            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Ações do filtro">
                                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 flex-grow-1 flex-md-grow-0">Filtrar</button>
                                <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 flex-grow-1 flex-md-grow-0">Mês atual</a>
                            </div>
                        </div>
                    </form>
                    @if ($filteredRegularAccountBalance !== null)
                        <div class="mt-3 mb-0">
                            <span class="tx-balance-pill text-secondary">
                                <span class="small text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.04em;">Saldo da conta</span>
                                <span class="fw-semibold {{ $filteredRegularAccountBalance >= 0 ? 'text-body' : 'text-danger' }}">R$ {{ number_format($filteredRegularAccountBalance, 2, ',', '.') }}</span>
                                <span class="small d-none d-md-inline">(todos os lançamentos)</span>
                            </span>
                        </div>
                    @endif
                </div>

                <div class="list-group list-group-flush" role="list">
                    @include('transactions.partials.transaction-list-rows')
                </div>
                <div class="tx-pagination-wrap px-3 py-3">
                    {{ $transactions->links() }}
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
                                <x-input-label for="transfer_from_tx" value="Conta de origem" />
                                <select id="transfer_from_tx" name="from_account_id" class="form-select mt-1" required>
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
                                <x-input-label for="transfer_to_tx" value="Conta de destino" />
                                <select id="transfer_to_tx" name="to_account_id" class="form-select mt-1" required>
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
                                <x-input-label for="transfer_amount_tx" value="Valor (R$)" />
                                <x-text-input
                                    id="transfer_amount_tx"
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
                                <x-input-label for="transfer_date_tx" value="Data" />
                                <x-text-input
                                    id="transfer_date_tx"
                                    name="date"
                                    type="date"
                                    class="mt-1"
                                    required
                                    value="{{ old('_form') === 'account-transfer' ? old('date', now()->toDateString()) : now()->toDateString() }}"
                                />
                                <x-input-error :messages="$errors->get('date')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_pm_tx" value="Forma de pagamento (registro)" />
                                <select id="transfer_pm_tx" name="payment_method" class="form-select mt-1" required>
                                    @foreach ($transferPaymentMethods as $pm)
                                        <option value="{{ $pm }}" @selected(old('_form') === 'account-transfer' ? old('payment_method') === $pm : $loop->first)>
                                            {{ $pm }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_desc_tx" value="Descrição (opcional)" />
                                <x-text-input
                                    id="transfer_desc_tx"
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
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">Confirmar transferência</button>
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


</x-app-layout>
