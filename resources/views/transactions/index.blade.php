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

            <div class="card border-0 shadow-sm overflow-hidden tx-index-card">
                <div class="tx-index-head px-4 py-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div class="min-w-0">
                            <h3 class="h5 mb-1 fw-semibold">Lista do período</h3>
                            <p class="small text-secondary mb-0">Demais lançamentos usam o <strong class="fw-medium text-body">mês de referência</strong> escolhido abaixo.</p>
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
                                            @if ($transactions->isNotEmpty())
                                                <div class="list-group-item px-3 py-2 border-start-0 border-end-0 tx-list-column-header d-none d-lg-block" role="presentation">
                                                    <div class="tx-list-row-grid">
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
                                                <div class="list-group-item px-3 py-2 border-start-0 border-end-0 tx-list-row-item" role="listitem">
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
                                                                        title="Alterar lançamento"
                                                                        aria-label="Alterar descrição ou valor do lançamento"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#modalEditTransactionAmount"
                                                                        data-tx-action="{{ route('transactions.update', $transaction) }}"
                                                                        data-tx-amount="{{ $transaction->amount }}"
                                                                        data-tx-description="{{ e($ccRowMeta['base_description'] ?? $transaction->baseDescriptionWithoutInstallmentSuffix()) }}"
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
                                                <div class="list-group-item border-0 text-center py-4" role="listitem">
                                                    <div class="tx-empty-state py-5 px-3">
                                                        <p class="fw-semibold text-body mb-1">Nenhum lançamento neste período</p>
                                                        <p class="small text-secondary mb-0 mx-auto" style="max-width: 22rem;">Ajuste mês, ano ou conta — ou registe com <strong class="fw-medium text-body">+ Receita</strong> ou <strong class="fw-medium text-body">+ Despesa</strong>.</p>
                                                    </div>
                                                </div>
                                            @endforelse
                </div>
                <div class="tx-pagination-wrap px-3 py-3">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalInstallmentGroupSummary" tabindex="-1" aria-labelledby="modalInstallmentGroupSummaryLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="modalInstallmentGroupSummaryLabel">Parcelas da compra</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-2 fw-medium js-tx-inst-summary-desc"></p>
                    <div class="tx-modal-installment-total px-4 py-3 mb-3 text-center">
                        <div class="small text-secondary fw-semibold text-uppercase mb-1">Total da compra</div>
                        <div class="h4 mb-1 fw-bold text-danger text-nowrap js-tx-inst-total-value" aria-live="polite">—</div>
                        <div class="small text-muted js-tx-inst-purchase-date" aria-live="polite"></div>
                    </div>
                    <p class="small text-muted mb-3">Todas as parcelas deste parcelamento no cartão. Em <strong>Fatura</strong>, abra o ciclo correspondente em Faturas cartão. Use as ações para alterar o valor ou excluir cada lançamento.</p>
                    <div class="table-responsive tx-modal-table-wrap">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditTransactionAmount" tabindex="-1" aria-labelledby="modalEditTransactionAmountLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="modalEditTransactionAmountLabel">Alterar lançamento</h2>
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
                        <p class="small text-secondary mb-3">Altere a descrição e/ou o valor. Se mudar o valor, as categorias são ajustadas na mesma proporção. Em cartão de crédito, o total da fatura é recalculado. Em parcelas, edita-se só o texto base; o sufixo <span class="text-nowrap">(Parcela x/y)</span> mantém-se.</p>
                        <div class="mb-3">
                            <x-input-label for="edit-tx-description" value="Descrição" />
                            <x-text-input
                                id="edit-tx-description"
                                name="description"
                                type="text"
                                class="mt-1"
                                required
                                value="{{ ($editTransactionModalMeta ?? [])['description'] ?? '' }}"
                            />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="edit-tx-amount" value="Valor (R$)" />
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
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <x-primary-button class="rounded-pill px-4">Salvar</x-primary-button>
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
        $openEditTransactionAmountModal = $editTransactionModalMeta && session('edit_transaction_id') && ($errors->has('amount') || $errors->has('description') || $errors->has('credit_limit_confirm_token'));
        $txAllocVisibleRows = 1;
        for ($r = 0; $r < 5; $r++) {
            $ov = old('category_allocations.'.$r.'.category_id');
            if ($ov !== null && $ov !== '') {
                $txAllocVisibleRows = max($txAllocVisibleRows, $r + 1);
            }
        }
    @endphp
    <div class="modal fade" id="modalNewTransaction" tabindex="-1" aria-labelledby="modalNewTransactionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
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
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
                        <a href="{{ route('accounts.index') }}" class="btn btn-primary rounded-pill px-4">Ir para contas</a>
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
                                $txShowCreditBlock = $txFormMode === 'cards_only' || $isCreditRender;
                            @endphp

                            <div class="vstack gap-4 tx-form-sections">
                                <section class="tx-form-section" aria-labelledby="tx-section-account-heading">
                                    <h3 class="tx-form-section-title" id="tx-section-account-heading">Conta e forma de pagamento</h3>
                                    <p class="small text-secondary mb-3">Comece pela forma de pagamento; em seguida escolha o cartão ou a conta compatível.</p>

                                    <input type="hidden" name="funding" id="tx-funding" value="@if($txFormMode === 'cards_only')credit_card@elseif($txFormMode === 'regular_only')account@else{{ old('funding', '') }}@endif">

                                    @if($txFormMode !== 'cards_only')
                                        <input type="hidden" name="payment_method" id="tx-payment-method" value="{{ old('payment_method', '') }}" @if($txFormMode === 'both' && old('funding') === 'credit_card') disabled @endif>
                                    @endif

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

                                    <p class="form-text mb-0 mt-2 pt-1 border-top border-light-subtle">
                                        <a href="{{ route('accounts.index') }}">Gerenciar contas e cartões</a>
                                    </p>
                                </section>

                                <section class="tx-form-section" aria-labelledby="tx-section-details-heading">
                                    <h3 class="tx-form-section-title" id="tx-section-details-heading">Detalhes do lançamento</h3>

                                    <input type="hidden" id="transaction-type" name="type" value="{{ old('type', 'expense') }}">
                                    <x-input-error :messages="$errors->get('type')" class="mt-2" />

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
                                </section>

                                <div id="tx-section-credit" class="tx-form-section {{ $txShowCreditBlock ? '' : 'd-none' }}" aria-labelledby="tx-section-credit-heading">
                                    <h3 class="tx-form-section-title" id="tx-section-credit-heading">Parcelas e fatura</h3>
                                    <div id="installments-wrapper" class="mb-3 pb-1 border-bottom border-light-subtle">
                                        <x-input-label for="installments" value="Parcelas (crédito)" />
                                        <select
                                            id="installments"
                                            name="installments"
                                            class="form-select mt-1"
                                            {{ $isCreditRender ? 'required' : '' }}
                                        >
                                            @for($i = 1; $i <= 12; $i++)
                                                <option value="{{ $i }}" {{ $installmentsOld === $i ? 'selected' : '' }}>{{ $i }}x</option>
                                            @endfor
                                        </select>
                                        <x-input-error :messages="$errors->get('installments')" class="mt-2" />
                                        <p class="form-text mb-0">Geramos 1 lançamento por mês de referência.</p>
                                    </div>

                                    <div id="reference-wrapper">
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

                                <section class="tx-form-section" aria-labelledby="tx-section-categories-heading">
                                    <h3 class="tx-form-section-title" id="tx-section-categories-heading">Categorias e valores</h3>
                                    <p class="small text-secondary mb-2">Até 5 linhas. A soma deve ser igual ao valor total.</p>
                                    <div id="tx-category-allocations-wrap">
                                        @for($si = 0; $si < 5; $si++)
                                            <div class="tx-cat-alloc-row row g-2 mb-2 align-items-end {{ $si < $txAllocVisibleRows ? '' : 'd-none' }}" data-tx-alloc-row="{{ $si }}">
                                                <div class="col-12 col-md-6 min-w-0">
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
                                                <div class="col-12 col-sm-6 col-md-4">
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
                                                <div class="col-12 col-sm-6 col-md-2 col-lg-auto d-flex align-items-end justify-content-sm-end justify-content-md-start">
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-danger btn-sm js-tx-remove-alloc-row w-100 w-sm-auto"
                                                        aria-label="Remover esta categoria"
                                                    >
                                                        Remover
                                                    </button>
                                                </div>
                                            </div>
                                        @endfor
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="tx-add-cat-row">Adicionar categoria</button>
                                    </div>
                                    <x-input-error :messages="$errors->get('category_allocations')" class="mt-2" />
                                </section>

                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                            <x-primary-button class="rounded-pill px-4">Salvar lançamento</x-primary-button>
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
