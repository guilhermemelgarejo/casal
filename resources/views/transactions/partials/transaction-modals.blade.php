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
                        <div class="small text-success fw-semibold js-tx-inst-refund-total" aria-live="polite"></div>
                        <div class="small text-muted js-tx-inst-purchase-date" aria-live="polite"></div>
                    </div>
                    <p class="small text-muted mb-3">Todas as parcelas deste parcelamento no cartão. Em <strong>Fatura</strong>, abra o ciclo correspondente em Faturas cartão. Use as ações para alterar o valor ou excluir cada lançamento.</p>
                    <div class="table-responsive tx-modal-table-wrap">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Parcela</th>
                                    <th>Descrição</th>
                                    <th>Registado por</th>
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
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar o resumo das parcelas" data-bs-dismiss="modal">Fechar</button>
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
                    <input type="hidden" name="installment_scope" id="edit-tx-installment-scope" value="single">
                    <div class="modal-body">
                        <p class="small text-secondary mb-3">Altere a descrição, o valor e/ou as categorias. Se mudar só o valor, as categorias são ajustadas na mesma proporção. Em cartão de crédito, o total da fatura é recalculado. Em parcelas, edita-se só o texto base; o sufixo <span class="text-nowrap">(Parcela x/y)</span> mantém-se.</p>
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

                        <div class="form-check mt-3 d-none" id="edit-tx-scope-all-wrap">
                            <input class="form-check-input" type="checkbox" value="1" id="edit-tx-scope-all">
                            <label class="form-check-label" for="edit-tx-scope-all">
                                Aplicar categorias em todas as parcelas desta compra
                            </label>
                            <div class="form-text">O valor total de cada parcela é mantido; apenas a repartição por categorias é aplicada a todas.</div>
                        </div>

                        @php
                            $editAllocVisibleRows = 1;
                            for ($r = 0; $r < 5; $r++) {
                                $ov = old('category_allocations.'.$r.'.category_id');
                                if ($ov !== null && $ov !== '') {
                                    $editAllocVisibleRows = max($editAllocVisibleRows, $r + 1);
                                }
                            }
                            if (! $errors->has('category_allocations')) {
                                $existingEditAllocs = ($editTransactionModalMeta ?? [])['category_allocations'] ?? [];
                                if (is_array($existingEditAllocs) && count($existingEditAllocs) > 1) {
                                    $editAllocVisibleRows = max($editAllocVisibleRows, min(5, count($existingEditAllocs)));
                                }
                            }
                        @endphp
                        <hr class="my-3">
                        <div>
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="fw-semibold">Categorias e valores</div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="edit-tx-add-cat-row" data-bs-toggle="tooltip" data-bs-placement="top" title="Mostrar mais uma linha de categoria (até 5)">Adicionar categoria</button>
                            </div>
                            <p class="small text-secondary mb-2 mt-1">Até 5 linhas. A soma deve ser igual ao valor total.</p>
                            <div id="edit-tx-category-allocations-wrap" data-tx-alloc-root="1">
                                @for($si = 0; $si < 5; $si++)
                                    @php
                                        $existing = (($editTransactionModalMeta ?? [])['category_allocations'][$si] ?? null);
                                        $catIdDefault = is_array($existing) ? ($existing['category_id'] ?? '') : '';
                                        $amtDefault = is_array($existing) ? ($existing['amount'] ?? '') : '';
                                    @endphp
                                    <div class="tx-cat-alloc-row row g-2 mb-2 align-items-end {{ $si < $editAllocVisibleRows ? '' : 'd-none' }}" data-tx-alloc-row="{{ $si }}">
                                        <div class="col-12 col-md-6 min-w-0">
                                            <label class="form-label small text-secondary mb-0" for="edit-tx-split-cat-{{ $si }}">Categoria {{ $si + 1 }}</label>
                                            <select
                                                id="edit-tx-split-cat-{{ $si }}"
                                                name="category_allocations[{{ $si }}][category_id]"
                                                class="form-select mt-1 js-tx-split-cat"
                                            >
                                                <option value="" {{ old('category_allocations.'.$si.'.category_id', $catIdDefault) ? '' : 'selected' }}>Selecione…</option>
                                                @foreach($categories as $c)
                                                    <option
                                                        value="{{ $c->id }}"
                                                        data-type="{{ $c->type }}"
                                                        {{ (string) old('category_allocations.'.$si.'.category_id', $catIdDefault) === (string) $c->id ? 'selected' : '' }}
                                                    >
                                                        {{ $c->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <label class="form-label small text-secondary mb-0" for="edit-tx-split-amt-{{ $si }}">Valor (R$)</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                name="category_allocations[{{ $si }}][amount]"
                                                id="edit-tx-split-amt-{{ $si }}"
                                                class="form-control mt-1 js-tx-split-amount"
                                                value="{{ old('category_allocations.'.$si.'.amount', $amtDefault) }}"
                                                placeholder="0,00"
                                            >
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-2 col-lg-auto d-flex align-items-end justify-content-sm-end justify-content-md-start">
                                            <button
                                                type="button"
                                                class="btn btn-outline-danger btn-sm js-tx-remove-alloc-row w-100 w-sm-auto"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="Remover esta linha de categoria do lançamento"
                                                aria-label="Remover esta categoria"
                                            >
                                                Remover
                                            </button>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                            <x-input-error :messages="$errors->get('category_allocations')" class="mt-2" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar sem guardar alterações" data-bs-dismiss="modal">Cancelar</button>
                        <x-primary-button class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Guardar descrição, valor e categorias">Salvar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @php
        $openNewTransactionModal = ! session('edit_transaction_id') && $errors->hasAny([
            'funding', 'account_id', 'description', 'amount', 'payment_method',
            'installments', 'type', 'date', 'reference_month', 'reference_year', 'credit_limit_confirm_token',
            'category_allocations', 'recurring_template_id', 'refund_of_transaction_id',
        ]);
        $openEditTransactionAmountModal = $editTransactionModalMeta && session('edit_transaction_id') && ($errors->has('amount') || $errors->has('description') || $errors->has('credit_limit_confirm_token') || $errors->has('category_allocations'));
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
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar o formulário" data-bs-dismiss="modal">Fechar</button>
                        <a href="{{ route('accounts.index') }}" class="btn btn-primary rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Cadastrar conta ou cartão para poder lançar">Ir para contas</a>
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
                        data-tx-default-date="{{ date('Y-m-d') }}"
                        data-credit-limit-precheck-url="{{ route('transactions.credit-limit-precheck') }}"
                        data-tx-recurring-prefill="{{ ($txRecurringPrefill ?? null) ? json_encode($txRecurringPrefill, JSON_UNESCAPED_UNICODE) : '' }}"
                    >
                        @csrf
                        <input type="hidden" name="recurring_template_id" id="tx-recurring-template-id" value="{{ old('recurring_template_id', '') }}">
                        <input type="hidden" name="is_refund" id="tx-is-refund" value="{{ old('is_refund', '0') }}">
                        <input type="hidden" name="refund_of_transaction_id" id="tx-refund-of-transaction-id" value="{{ old('refund_of_transaction_id', '') }}">
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
                                            <p class="form-text mb-0" id="tx-account-meta" aria-live="polite"></p>
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
                                                    <p class="form-text mb-0" id="tx-account-meta" aria-live="polite"></p>
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
                                            <x-text-input id="date" name="date" type="text" data-duozen-flatpickr="date" class="mt-1" autocomplete="off" value="{{ old('date', date('Y-m-d')) }}" required />
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

                                    <hr class="my-3">
                                    <div id="tx-refund-ui" class="vstack gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" id="tx-refund-check" {{ old('is_refund') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="tx-refund-check">
                                                Estorno no cartão (crédito)
                                            </label>
                                            <div class="form-text">
                                                Informe o valor como positivo. O lançamento será gravado como crédito (valor negativo) na fatura.
                                            </div>
                                        </div>
                                        <div class="small text-secondary d-none" id="tx-refund-linked-hint">
                                            Compra vinculada: <span class="fw-semibold text-body" id="tx-refund-linked-label"></span>
                                            <button type="button" class="btn btn-link btn-sm p-0 ms-2" id="tx-refund-clear" data-bs-toggle="tooltip" data-bs-placement="top" title="Desvincular o estorno da compra original">Limpar</button>
                                        </div>
                                        <div class="small text-secondary d-none" id="tx-refund-manual-id-hint">
                                            ID da compra (opcional): <span class="text-body fw-semibold" id="tx-refund-manual-id"></span>
                                        </div>
                                        <x-input-error :messages="$errors->get('refund_of_transaction_id')" class="mt-2" />
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
                                                        data-bs-toggle="tooltip"
                                                        data-bs-placement="top"
                                                        title="Remover esta linha de categoria do lançamento"
                                                        aria-label="Remover esta categoria"
                                                    >
                                                        Remover
                                                    </button>
                                                </div>
                                            </div>
                                        @endfor
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="tx-add-cat-row" data-bs-toggle="tooltip" data-bs-placement="top" title="Mostrar mais uma linha de categoria (até 5)">Adicionar categoria</button>
                                    </div>
                                    <x-input-error :messages="$errors->get('category_allocations')" class="mt-2" />
                                </section>

                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar sem criar lançamento" data-bs-dismiss="modal">Cancelar</button>
                            <x-primary-button class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Registar o lançamento no painel">Salvar lançamento</x-primary-button>
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
