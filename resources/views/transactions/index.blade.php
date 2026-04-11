<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">
            Lançamentos
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container-xxl px-3 px-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                @if (session('success'))
                    <div class="alert alert-success mb-4">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="vstack gap-4">
                    <div class="card border shadow-sm">
                            <div class="card-header bg-white py-3 border-bottom">
                                <div class="vstack gap-0">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                        <div class="min-w-0">
                                            <h3 class="h5 mb-0">Lançamentos</h3>
                                            <p class="small text-secondary mb-0 mt-1">Histórico do período: cartão por data da compra (compras parceladas em uma linha com total); demais lançamentos por mês de referência.</p>
                                        </div>
                                        <button
                                            type="button"
                                            class="btn btn-primary flex-shrink-0"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalNewTransaction"
                                        >
                                            Novo lançamento
                                        </button>
                                    </div>

                                    <div class="border-top pt-3 mt-3 min-w-0">
                                    <form method="GET" action="{{ route('transactions.index') }}" class="row g-2 gx-2 gy-2 align-items-end">
                                        <div class="col-6 col-lg-2 min-w-0">
                                            <div class="input-group">
                                                <span class="input-group-text text-secondary px-2">Mês</span>
                                                <select
                                                    id="filter-month"
                                                    name="month"
                                                    class="form-select min-w-0 px-2"
                                                    aria-label="Mês"
                                                >
                                                    @for($m = 1; $m <= 12; $m++)
                                                        <option value="{{ $m }}" {{ (int) $selectedMonth === $m ? 'selected' : '' }}>
                                                            {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                                        </option>
                                                    @endfor
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-6 col-lg-2 min-w-0">
                                            <div class="input-group">
                                                <span class="input-group-text text-secondary px-2">Ano</span>
                                                <select
                                                    id="filter-year"
                                                    name="year"
                                                    class="form-select min-w-0 px-2"
                                                    aria-label="Ano"
                                                >
                                                    @foreach($years as $y)
                                                        <option value="{{ $y }}" {{ (int) $selectedYear === (int) $y ? 'selected' : '' }}>
                                                            {{ $y }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 col-lg min-w-0">
                                            <div class="input-group">
                                                <span class="input-group-text text-secondary px-2">Conta</span>
                                                <select
                                                    id="filter-account"
                                                    name="account_id"
                                                    class="form-select min-w-0 px-2"
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
                                        </div>

                                        <div class="col-12 col-lg-auto">
                                            <div class="d-flex gap-2" role="group" aria-label="Ações do filtro">
                                                <button type="submit" class="btn btn-primary flex-grow-1 flex-lg-grow-0">Filtrar</button>
                                                <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary flex-grow-1 flex-lg-grow-0">Atual</a>
                                            </div>
                                        </div>
                                    </form>
                                    @if ($filteredRegularAccountBalance !== null)
                                        <p class="small text-secondary mb-0 mt-2">
                                            Saldo atual desta conta (todos os lançamentos):
                                            <span class="fw-semibold {{ $filteredRegularAccountBalance >= 0 ? 'text-body' : 'text-danger' }}">R$ {{ number_format($filteredRegularAccountBalance, 2, ',', '.') }}</span>
                                        </p>
                                    @endif
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush rounded-0" role="list">
                                            @if ($transactions->isNotEmpty())
                                                <div class="list-group-item px-3 py-2 border-start-0 border-end-0 bg-light d-none d-lg-block" role="presentation">
                                                    <div class="tx-list-row-grid small text-secondary fw-semibold">
                                                        <div>Data</div>
                                                        <div>Ref.</div>
                                                        <div>Descrição</div>
                                                        <div>Categorias</div>
                                                        <div>Pagamento / conta</div>
                                                        <div>Valor</div>
                                                        <div class="text-end text-nowrap">Ações</div>
                                                    </div>
                                                </div>
                                            @endif
                                            @forelse ($transactions as $transaction)
                                                @php
                                                    $delMeta = $transactionDeleteMeta[$transaction->id] ?? [
                                                        'paidInvoice' => false,
                                                        'peerCount' => 1,
                                                        'singleAllowed' => true,
                                                    ];
                                                    $editMeta = $transactionAmountEditMeta[$transaction->id] ?? [
                                                        'canEditAmount' => false,
                                                        'blockedMessage' => null,
                                                        'needsCreditLimitPrecheck' => false,
                                                        'precheckUrl' => null,
                                                    ];
                                                    $ccRowMeta = $creditCardPurchaseRowMeta[$transaction->id] ?? null;
                                                    $hideListEditForCcInstallments = $delMeta['peerCount'] > 1 && $transaction->accountModel?->isCreditCard();
                                                    $blockedMsg = 'Este lançamento faz parte de um ciclo de fatura de cartão já marcado como pago. Desmarque o pagamento em Faturas de cartão se precisar alterar os lançamentos desse período.';
                                                    $refMonth = (int) ($transaction->reference_month ?? $transaction->date->month);
                                                    $refYear = (int) ($transaction->reference_year ?? $transaction->date->year);
                                                    $refLabel = str_pad((string) $refMonth, 2, '0', STR_PAD_LEFT) . '/' . $refYear;
                                                    $dateMonthYear = $transaction->date->format('m/Y');
                                                    $accRow = $transaction->accountModel;
                                                @endphp
                                                <div class="list-group-item px-3 py-2 border-start-0 border-end-0" role="listitem">
                                                    <div class="tx-list-row-grid">
                                                        <div class="text-secondary small text-nowrap">{{ $transaction->date->format('d/m/Y') }}</div>
                                                        <div class="small text-muted text-nowrap">
                                                            @if($refLabel !== $dateMonthYear)
                                                                Ref. {{ $refLabel }}
                                                            @else
                                                                <span class="text-muted opacity-50">—</span>
                                                            @endif
                                                        </div>
                                                        <div class="tx-cell-truncate">
                                                            <div class="text-truncate" title="{{ $ccRowMeta['base_description'] ?? $transaction->description }} — {{ $transaction->user->name }}">
                                                                <span class="fw-medium">{{ $ccRowMeta['base_description'] ?? $transaction->description }}</span><span class="text-muted small"> · {{ $transaction->user->name }}</span>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                                            @forelse($transaction->categorySplits as $sp)
                                                                @if($sp->category)
                                                                    <span class="badge rounded-pill text-white" style="background-color: {{ $sp->category->color ?? '#ccc' }}">{{ $sp->category->name }}</span>
                                                                @endif
                                                            @empty
                                                                <span class="text-secondary small">—</span>
                                                            @endforelse
                                                        </div>
                                                        <div class="small text-body-secondary tx-cell-truncate">
                                                            @if($accRow?->isCreditCard())
                                                                <div class="text-truncate" title="Cartão · {{ $accRow->name }}"><span class="fw-medium text-body">Cartão</span><span class="text-muted"> · {{ $accRow->name }}</span></div>
                                                            @elseif($transaction->payment_method || $accRow)
                                                                <div class="text-truncate" title="{{ $transaction->payment_method ?: '—' }} · {{ $accRow?->name ?? '—' }}"><span class="fw-medium text-body">{{ $transaction->payment_method ?: '—' }}</span><span class="text-muted"> · {{ $accRow?->name ?? '—' }}</span></div>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </div>
                                                        <div class="fw-bold text-nowrap {{ $transaction->type === 'income' ? 'text-success' : 'text-danger' }}">
                                                            @if($ccRowMeta !== null)
                                                                {{ $transaction->type === 'income' ? '+' : '-' }} R$ {{ $ccRowMeta['purchase_total_str'] }}@if($ccRowMeta['installment_count'] > 1)<span class="small fw-normal text-muted"> em {{ $ccRowMeta['installment_count'] }}x</span>@endif
                                                            @else
                                                                {{ $transaction->type === 'income' ? '+' : '-' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                                                            @endif
                                                        </div>
                                                        <div class="tx-cell-actions">
                                                            @if ($delMeta['peerCount'] > 1 && $transaction->accountModel?->isCreditCard())
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-link btn-sm p-0 text-secondary js-tx-open-installment-summary"
                                                                    title="Parcelas da compra"
                                                                    aria-label="Parcelas da compra"
                                                                    data-tx-root-id="{{ $transaction->installmentRootId() }}"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="3.75" cy="5.5" r="1.35"/><circle cx="3.75" cy="10" r="1.35"/><circle cx="3.75" cy="14.5" r="1.35"/><path d="M7 4.75h10.25a.75.75 0 010 1.5H7a.75.75 0 010-1.5zm0 4.5h10.25a.75.75 0 010 1.5H7a.75.75 0 010-1.5zm0 4.5h10.25a.75.75 0 010 1.5H7a.75.75 0 010-1.5z"/></svg>
                                                                </button>
                                                            @endif
                                                            @if (! $hideListEditForCcInstallments)
                                                                @if (! $editMeta['canEditAmount'])
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-link text-secondary btn-sm p-0 js-tx-edit-blocked"
                                                                        title="Edição do valor não permitida"
                                                                        aria-label="Edição do valor não permitida"
                                                                        data-tx-blocked-msg="{{ $editMeta['blockedMessage'] ?? 'Não é possível editar o valor deste lançamento.' }}"
                                                                    >
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                                                    </button>
                                                                @else
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-link text-primary btn-sm p-0 js-tx-edit-amount-open"
                                                                        title="Alterar valor"
                                                                        aria-label="Alterar valor do lançamento"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#modalEditTransactionAmount"
                                                                        data-tx-action="{{ route('transactions.update', $transaction) }}"
                                                                        data-tx-amount="{{ $transaction->amount }}"
                                                                        data-tx-precheck="{{ $editMeta['needsCreditLimitPrecheck'] ? $editMeta['precheckUrl'] : '' }}"
                                                                    >
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                                                    </button>
                                                                @endif
                                                            @endif
                                                            @if ($delMeta['paidInvoice'])
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-link text-secondary btn-sm p-0 js-tx-delete-blocked"
                                                                    title="Exclusão bloqueada: fatura deste período já paga"
                                                                    aria-label="Exclusão bloqueada: lançamento em ciclo de fatura de cartão já pago"
                                                                    data-tx-blocked-msg="{{ $blockedMsg }}"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                                                </button>
                                                            @else
                                                                <form
                                                                    action="{{ route('transactions.destroy', $transaction) }}"
                                                                    method="POST"
                                                                    class="d-inline js-tx-delete-form"
                                                                    data-tx-delete-meta="{!! htmlspecialchars(json_encode($delMeta, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') !!}"
                                                                >
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <input type="hidden" name="installment_scope" value="single" class="js-tx-installment-scope">
                                                                    <button type="submit" class="btn btn-link text-danger btn-sm p-0" title="Excluir">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                                                    </button>
                                                                </form>
                                                            @endif
                                                            </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="list-group-item border-0 text-center text-secondary py-5" role="listitem">
                                                    Nenhum lançamento encontrado.
                                                </div>
                                            @endforelse
                                </div>
                                <div class="px-3 py-3">
                                    {{ $transactions->links() }}
                                </div>
                            </div>
                        </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalInstallmentGroupSummary" tabindex="-1" aria-labelledby="modalInstallmentGroupSummaryLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="modalInstallmentGroupSummaryLabel">Parcelas da compra</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-2 fw-medium js-tx-inst-summary-desc"></p>
                    <div class="rounded-3 border border-danger border-opacity-25 bg-danger bg-opacity-10 px-4 py-3 mb-3 text-center">
                        <div class="small text-secondary fw-semibold text-uppercase mb-1">Total da compra</div>
                        <div class="h4 mb-1 fw-bold text-danger text-nowrap js-tx-inst-total-value" aria-live="polite">—</div>
                        <div class="small text-muted js-tx-inst-purchase-date" aria-live="polite"></div>
                    </div>
                    <p class="small text-muted mb-3">Todas as parcelas deste parcelamento no cartão. Em <strong>Fatura</strong>, abra o ciclo correspondente em Faturas cartão. Use as ações para alterar o valor ou excluir cada lançamento.</p>
                    <div class="table-responsive border rounded">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Parcela</th>
                                    <th>Descrição</th>
                                    <th>Ref. fatura</th>
                                    <th>Fatura</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-installment-summary"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditTransactionAmount" tabindex="-1" aria-labelledby="modalEditTransactionAmountLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="modalEditTransactionAmountLabel">Alterar valor</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form
                    id="form-edit-transaction-amount"
                    method="POST"
                    action="{{ ($editTransactionModalMeta ?? [])['action'] ?? '' }}"
                    data-tx-edit-precheck-url="{{ (($editTransactionModalMeta ?? [])['edit']['needsCreditLimitPrecheck'] ?? false) ? (($editTransactionModalMeta ?? [])['edit']['precheckUrl'] ?? '') : '' }}"
                >
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="return_from_installment_modal" id="input-return-from-installment-modal" value="0">
                    <div class="modal-body">
                        <p class="small text-secondary mb-3">Só o valor é alterado; as categorias são ajustadas na mesma proporção. Em cartão de crédito, o total da fatura é recalculado. Parcelas podem ficar com valores diferentes entre si.</p>
                        <div>
                            <x-input-label for="edit-tx-amount" value="Novo valor (R$)" />
                            <x-text-input
                                id="edit-tx-amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                class="mt-1"
                                required
                                value="{{ ($editTransactionModalMeta ?? [])['amount'] ?? '' }}"
                                placeholder="0,00"
                            />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <x-primary-button>Salvar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @php
        $openNewTransactionModal = ! session('edit_transaction_id') && $errors->hasAny([
            'funding', 'account_id', 'description', 'amount', 'payment_method',
            'installments', 'type', 'date', 'reference_month', 'reference_year', 'credit_limit_confirm_token',
            'category_allocations',
        ]);
        $openEditTransactionAmountModal = $editTransactionModalMeta && session('edit_transaction_id') && ($errors->has('amount') || $errors->has('credit_limit_confirm_token'));
        $txAllocVisibleRows = 1;
        for ($r = 0; $r < 5; $r++) {
            $ov = old('category_allocations.'.$r.'.category_id');
            if ($ov !== null && $ov !== '') {
                $txAllocVisibleRows = max($txAllocVisibleRows, $r + 1);
            }
        }
    @endphp
    <div class="modal fade" id="modalNewTransaction" tabindex="-1" aria-labelledby="modalNewTransactionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="modalNewTransactionLabel">Novo lançamento</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                @if($accounts->isEmpty())
                    <div class="modal-body overflow-auto" style="max-height: calc(100vh - 12rem);">
                        <div class="alert alert-warning mb-0">
                            Cadastre ao menos uma conta em
                            <a href="{{ route('accounts.index') }}" class="alert-link">Gerenciar contas</a>
                            para poder lançar movimentações.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                        <a href="{{ route('accounts.index') }}" class="btn btn-primary">Ir para contas</a>
                    </div>
                @else
                    <form
                        id="form-new-transaction"
                        action="{{ route('transactions.store') }}"
                        method="POST"
                        data-tx-form-mode="{{ $txFormMode }}"
                        data-tx-accounts='@json($txAccountsPayload)'
                        data-tx-old-account-id="{{ old('account_id', '') }}"
                        data-tx-default-ref-month="{{ $refDefaultMonth }}"
                        data-tx-default-ref-year="{{ $refDefaultYear }}"
                        data-credit-limit-precheck-url="{{ route('transactions.credit-limit-precheck') }}"
                    >
                        @csrf
                        <div class="modal-body overflow-auto" style="max-height: calc(100vh - 12rem);">
                            <p class="small text-secondary mb-3">Comece pela forma de pagamento; em seguida escolha o cartão ou a conta compatível.</p>

                            <input type="hidden" name="funding" id="tx-funding" value="@if($txFormMode === 'cards_only')credit_card@elseif($txFormMode === 'regular_only')account@else{{ old('funding', '') }}@endif">

                            @if($txFormMode !== 'cards_only')
                                <input type="hidden" name="payment_method" id="tx-payment-method" value="{{ old('payment_method', '') }}" @if($txFormMode === 'both' && old('funding') === 'credit_card') disabled @endif>
                            @endif

                            <div class="vstack gap-3">
                                @if($txFormMode === 'cards_only')
                                    <div>
                                        <x-input-label for="tx-account-id" value="Cartão de crédito" />
                                        <select name="account_id" id="tx-account-id" class="form-select mt-1" required>
                                            <option value="" disabled {{ old('account_id') ? '' : 'selected' }}>Selecione o cartão…</option>
                                            @foreach($cardAccounts as $account)
                                                <option value="{{ $account->id }}" {{ (string) old('account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                                    </div>
                                @else
                                    <div class="row g-2">
                                        <div class="col-12 col-md-6">
                                            <x-input-label for="payment_flow" value="Forma de pagamento" />
                                            <select id="payment_flow" class="form-select mt-1" required>
                                                <option value="" {{ $paymentFlowOld === '' ? 'selected' : '' }}>Selecione…</option>
                                                @if($txFormMode === 'both')
                                                    <option value="__credit__" {{ $paymentFlowOld === '__credit__' ? 'selected' : '' }}>Cartão de crédito</option>
                                                @endif
                                                @foreach(\App\Support\PaymentMethods::forRegularAccounts() as $pm)
                                                    <option value="{{ $pm }}" {{ $paymentFlowOld === $pm ? 'selected' : '' }}>{{ $pm }}</option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('funding')" class="mt-2" />
                                            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div id="tx-destination-wrap" class="{{ $paymentFlowOld !== '' ? '' : 'd-none' }}">
                                                <label class="form-label" for="tx-account-id" id="tx-destination-label">
                                                    @if($paymentFlowOld === '__credit__')
                                                        Cartão de crédito
                                                    @elseif($paymentFlowOld !== '')
                                                        Conta
                                                    @else
                                                        Conta ou cartão
                                                    @endif
                                                </label>
                                                <select id="tx-account-id" class="form-select mt-1"></select>
                                                <p class="form-text mb-0 d-none text-warning" id="tx-no-account-hint">Nenhuma conta permite esta forma. Ajuste em Gerenciar contas.</p>
                                                <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <p class="form-text mb-0">
                                    <a href="{{ route('accounts.index') }}">Gerenciar contas e cartões</a>
                                </p>

                                <div>
                                    <x-input-label for="transaction-type" value="Tipo de Lançamento" />
                                    <select id="transaction-type" name="type" class="form-select mt-1">
                                        <option value="expense" {{ old('type', 'expense') === 'expense' ? 'selected' : '' }}>Despesa (Saída)</option>
                                        <option value="income" {{ old('type') === 'income' ? 'selected' : '' }}>Receita (Entrada)</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label value="Categorias e valores" />
                                    <p class="small text-secondary mb-2">Até 5 linhas. A soma deve ser igual ao valor total. A quitação de fatura de cartão continua só em <a href="{{ route('credit-card-statements.index') }}">Faturas cartão</a>.</p>
                                    <div id="tx-category-allocations-wrap">
                                        @for($si = 0; $si < 5; $si++)
                                            <div class="tx-cat-alloc-row row g-2 mb-2 align-items-end {{ $si < $txAllocVisibleRows ? '' : 'd-none' }}" data-tx-alloc-row="{{ $si }}">
                                                <div class="col-md-7">
                                                    <label class="form-label small text-secondary mb-0" for="tx-split-cat-{{ $si }}">Categoria {{ $si + 1 }}</label>
                                                    <select
                                                        id="tx-split-cat-{{ $si }}"
                                                        name="category_allocations[{{ $si }}][category_id]"
                                                        class="form-select mt-1 js-tx-split-cat"
                                                    >
                                                        <option value="" {{ old('category_allocations.'.$si.'.category_id') ? '' : 'selected' }}>Selecione…</option>
                                                        @foreach($categories as $c)
                                                            <option
                                                                value="{{ $c->id }}"
                                                                data-type="{{ $c->type }}"
                                                                {{ (string) old('category_allocations.'.$si.'.category_id') === (string) $c->id ? 'selected' : '' }}
                                                            >
                                                                {{ $c->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label small text-secondary mb-0" for="tx-split-amt-{{ $si }}">Valor (R$)</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        name="category_allocations[{{ $si }}][amount]"
                                                        id="tx-split-amt-{{ $si }}"
                                                        class="form-control mt-1 js-tx-split-amount"
                                                        value="{{ old('category_allocations.'.$si.'.amount') }}"
                                                        placeholder="0,00"
                                                    >
                                                </div>
                                            </div>
                                        @endfor
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="tx-add-cat-row">Adicionar categoria</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="tx-remove-cat-row">Remover última linha</button>
                                    </div>
                                    <x-input-error :messages="$errors->get('category_allocations')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="description" value="Descrição" />
                                    <x-text-input id="description" name="description" type="text" class="mt-1" required value="{{ old('description') }}" placeholder="Ex: Compras do mês" />
                                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <x-input-label for="amount" value="Valor (R$)" />
                                        <x-text-input id="amount" name="amount" type="number" step="0.01" class="mt-1" required value="{{ old('amount') }}" placeholder="0,00" />
                                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                    </div>
                                    <div class="col-6">
                                        <x-input-label for="date" value="Data" />
                                        <x-text-input id="date" name="date" type="date" class="mt-1" value="{{ old('date', date('Y-m-d')) }}" required />
                                        <x-input-error :messages="$errors->get('date')" class="mt-2" />
                                    </div>
                                </div>

                                @php
                                    $isCreditRender = $txFormMode === 'cards_only' || $fundingOld === 'credit_card' || $paymentFlowOld === '__credit__';
                                    $installmentsOld = (int) old('installments', 1);

                                    $dateForRef = old('date', date('Y-m-d'));
                                    $hasOldRef = old('reference_month') !== null && old('reference_month') !== ''
                                        && old('reference_year') !== null && old('reference_year') !== '';
                                    if ($hasOldRef) {
                                        $parsedRefMonth = (int) old('reference_month');
                                        $parsedRefYear = (int) old('reference_year');
                                    } elseif ($isCreditRender) {
                                        $parsedRefMonth = $refDefaultMonth;
                                        $parsedRefYear = $refDefaultYear;
                                    } else {
                                        $parsedRefMonth = (int) date('m', strtotime($dateForRef));
                                        $parsedRefYear = (int) date('Y', strtotime($dateForRef));
                                    }
                                @endphp
                                <div id="installments-wrapper" class="{{ $isCreditRender ? '' : 'd-none' }}">
                                    <x-input-label for="installments" value="Parcelas (crédito)" />
                                    <select
                                        id="installments"
                                        name="installments"
                                        class="form-select mt-1"
                                        {{ $isCreditRender ? 'required' : '' }}
                                    >
                                        @for($i = 1; $i <= 12; $i++)
                                            <option value="{{ $i }}" {{ $installmentsOld === $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                    <x-input-error :messages="$errors->get('installments')" class="mt-2" />
                                    <p class="form-text mb-0">Geramos 1 lançamento por mês de referência.</p>
                                </div>

                                <div id="reference-wrapper" class="{{ $isCreditRender ? '' : 'd-none' }}">
                                    <x-input-label value="Mês de referência (fatura)" />
                                    <div class="row g-2 mt-1">
                                        <div class="col-6">
                                            <select id="reference_month" name="reference_month" class="form-select">
                                                @for($m = 1; $m <= 12; $m++)
                                                    <option value="{{ $m }}" {{ (int) $parsedRefMonth === $m ? 'selected' : '' }}>
                                                        {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                                    </option>
                                                @endfor
                                            </select>
                                            <x-input-error :messages="$errors->get('reference_month')" class="mt-2" />
                                        </div>
                                        <div class="col-6">
                                            <select id="reference_year" name="reference_year" class="form-select">
                                                @foreach($years as $y)
                                                    <option value="{{ $y }}" {{ (int) $parsedRefYear === (int) $y ? 'selected' : '' }}>
                                                        {{ $y }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('reference_year')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <x-primary-button>Salvar lançamento</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if ($openNewTransactionModal)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var el = document.getElementById('modalNewTransaction');
                    if (!el || typeof bootstrap === 'undefined') return;
                    bootstrap.Modal.getOrCreateInstance(el).show();
                });
            </script>
        @endpush
    @endif

    @if ($openEditTransactionAmountModal)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var el = document.getElementById('modalEditTransactionAmount');
                    if (!el || typeof bootstrap === 'undefined') return;
                    bootstrap.Modal.getOrCreateInstance(el).show();
                });
            </script>
        @endpush
    @endif

    @push('scripts')
        <script>
            window.__txInstallmentGroups = @json($installmentGroupsModalPayload ?? []);
            window.__txOpenInstallmentModalRoot = @json(session('open_installment_modal_root'));
        </script>
    @endpush

</x-app-layout>
