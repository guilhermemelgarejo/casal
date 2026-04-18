@if ($transactions->isNotEmpty())
    <div class="list-group-item px-3 py-2 border-start-0 border-end-0 tx-list-column-header d-none d-lg-block" role="presentation">
        <div class="tx-list-row-grid">
            <div>Data</div>
            <div>Descrição</div>
            <div>Categorias</div>
            <div>Registado por</div>
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
        $accRow = $transaction->accountModel;
        $registeredByLabel = $transaction->user?->firstGivenName() ?? '';
        $registeredByTitle = $transaction->user ? 'Registado por '.$transaction->user->name : '';
        $txAllocationsMeta = $transaction->categorySplits
            ->map(fn ($sp) => ['category_id' => (int) $sp->category_id, 'amount' => number_format((float) $sp->amount, 2, '.', '')])
            ->values()
            ->all();
    @endphp
    <div
        id="dashboard-tx-{{ $transaction->id }}"
        @class([
            'list-group-item',
            'px-3',
            'py-2',
            'border-start-0',
            'border-end-0',
            'tx-list-row-item',
            'tx-list-row-item--income' => $transaction->type === 'income',
            'tx-list-row-item--focused' => (int) ($focusTransactionId ?? 0) === (int) $transaction->id,
        ])
        role="listitem"
    >
        <div class="tx-list-row-grid">
            <div class="text-secondary small text-nowrap">{{ $transaction->date->format('d/m/Y') }}</div>
            <div class="tx-cell-truncate">
                <div class="text-truncate" title="{{ $ccRowMeta['base_description'] ?? $transaction->description }}">
                    <span class="fw-medium">{{ $ccRowMeta['base_description'] ?? $transaction->description }}</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1 align-items-center">
                @forelse($transaction->categorySplits as $sp)
                    @if($sp->category)
                        <span
                            class="badge rounded-pill text-white"
                            style="background-color: {{ $sp->category->color ?? '#ccc' }}"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="Valor: R$ {{ number_format((float) $sp->amount, 2, ',', '.') }}"
                        >{{ $sp->category->name }}</span>
                    @endif
                @empty
                    <span class="text-secondary small">—</span>
                @endforelse
            </div>
            <div class="tx-cell-truncate small">
                <div class="text-truncate" title="{{ $registeredByTitle }}">
                    <span class="d-lg-none text-secondary">Registado por </span><span class="text-body">{{ $registeredByLabel !== '' ? $registeredByLabel : '—' }}</span>
                </div>
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
            @php
                $amtRaw = (float) $transaction->amount;
                $isCreditCardRefund = $transaction->type === 'expense' && $amtRaw < -0.004;
                $amtAbsStr = number_format(abs($amtRaw), 2, ',', '.');
            @endphp
            <div class="fw-bold text-nowrap {{ $transaction->type === 'income' || $isCreditCardRefund ? 'text-success' : 'text-danger' }}">
                @if($ccRowMeta !== null)
                    {{ $transaction->type === 'income' ? '+' : '-' }} R$ {{ $ccRowMeta['purchase_total_str'] }}@if($ccRowMeta['installment_count'] > 1)<span class="small fw-normal text-muted"> em {{ $ccRowMeta['installment_count'] }}x</span>@endif
                    @if(($ccRowMeta['refund_total'] ?? 0) > 0.004)
                        <div class="small fw-semibold text-success">Estornado: R$ {{ $ccRowMeta['refund_total_str'] }}</div>
                    @endif
                @else
                    @if($transaction->type === 'income')
                        + R$ {{ number_format($amtRaw, 2, ',', '.') }}
                    @elseif($isCreditCardRefund)
                        + R$ {{ $amtAbsStr }}
                    @else
                        - R$ {{ $amtAbsStr }}
                    @endif
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
                @if($transaction->type === 'expense' && $transaction->accountModel?->isCreditCard() && (float) $transaction->amount > 0.004)
                    <button
                        type="button"
                        class="btn btn-link text-success btn-sm p-0 js-tx-open-refund"
                        title="Registrar estorno"
                        aria-label="Registrar estorno"
                        data-bs-toggle="modal"
                        data-bs-target="#modalNewTransaction"
                        data-tx-refund-of="{{ $transaction->installmentRootId() }}"
                        data-tx-refund-account-id="{{ (int) $transaction->account_id }}"
                        data-tx-refund-label="{{ e($ccRowMeta['base_description'] ?? $transaction->baseDescriptionWithoutInstallmentSuffix()) }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/></svg>
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
                            data-tx-type="{{ $transaction->type }}"
                            data-tx-allocations="{{ rawurlencode(json_encode($txAllocationsMeta, JSON_UNESCAPED_UNICODE)) }}"
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
            <p class="fw-semibold text-body mb-1">{{ $emptyTitle ?? 'Nenhum lançamento neste período' }}</p>
            <p class="small text-secondary mb-0 mx-auto" style="max-width: 22rem;">{!! $emptyHint ?? 'Ajuste mês, ano ou conta — ou registe com <strong class="fw-medium text-body">+ Receita</strong> ou <strong class="fw-medium text-body">+ Despesa</strong>.' !!}</p>
        </div>
    </div>
@endforelse
