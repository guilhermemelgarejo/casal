<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">Faturas de cartão de crédito</h2>
    </x-slot>

    <div class="py-4">
        <div class="container-xxl px-3 px-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    @if (session('success'))
                        <div class="alert alert-success mb-4">{{ session('success') }}</div>
                    @endif

                    @if ($errors->has('payment'))
                        <div class="alert alert-danger mb-4">{{ $errors->first('payment') }}</div>
                    @endif

                    <p class="text-secondary small mb-4">
                        Fatura = <strong>mês de referência</strong> do cartão; o total reflete os lançamentos desse ciclo (incluindo exclusões). Vencimento padrão no cartão (<a href="{{ route('accounts.index') }}">Contas</a>); ao primeiro lançamento do mês a fatura nasce com esse vencimento e pode ajustar-se em Editar.
                    </p>

                    @if ($cardAccounts->isEmpty())
                        <div class="alert alert-warning">
                            Cadastre um <strong>cartão de crédito</strong> em
                            <a href="{{ route('accounts.index') }}">Contas</a> e faça lançamentos no cartão para ver as faturas aqui.
                        </div>
                    @elseif ($invoiceCycles->isEmpty())
                        <div class="alert alert-info mb-0">
                            Ainda não há despesas em cartão com mês de referência. Use <a href="{{ route('transactions.index') }}">Lançamentos</a> com forma &quot;Cartão de crédito&quot;.
                        </div>
                    @else
                        <div class="table-responsive border rounded">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Cartão</th>
                                        <th>Ref.</th>
                                        <th class="text-end">Total (soma no cartão)</th>
                                        <th>Vencimento</th>
                                        <th>Pagamento</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoiceCycles as $cycle)
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
                                        @endphp
                                        <tr>
                                            <td class="fw-medium">{{ $cycle->account->name }}</td>
                                            <td>{{ sprintf('%02d/%d', $cycle->reference_month, $cycle->reference_year) }}</td>
                                            <td class="text-end fw-medium">R$ {{ number_format($cycle->spent_total, 2, ',', '.') }}</td>
                                            <td>
                                                @if ($dueForDisplay)
                                                    @if ($dueIsSuggestion)
                                                        <span class="text-secondary" title="Conforme o cartão em Contas; confirme em Editar ou ao registrar pagamento">Sug. {{ $dueForDisplay->format('d/m/Y') }}</span>
                                                    @else
                                                        {{ $dueForDisplay->format('d/m/Y') }}
                                                    @endif
                                                @else
                                                    <span class="text-secondary">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($isPaid)
                                                    <span class="badge text-bg-success">Paga</span>
                                                    <div class="small text-secondary">{{ $meta->paid_at?->format('d/m/Y') }}</div>
                                                    @if ($hasPayments)
                                                        <ul class="list-unstyled small mb-0 mt-1">
                                                            @foreach ($meta->paymentTransactions as $ptx)
                                                                <li>
                                                                    <a href="{{ route('transactions.index', ['month' => $ptx->reference_month, 'year' => $ptx->reference_year]) }}">{{ $ptx->date->format('d/m/Y') }}</a>
                                                                    — R$ {{ number_format((float) $ptx->amount, 2, ',', '.') }}
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                @elseif ($hasPayments)
                                                    <span class="badge text-bg-info text-dark">Parcial</span>
                                                    <div class="small text-secondary mt-1">
                                                        Pago: R$ {{ number_format((float) $meta->paymentsTotal(), 2, ',', '.') }}
                                                        · Pendente: R$ {{ number_format($remaining, 2, ',', '.') }}
                                                    </div>
                                                    <ul class="list-unstyled small mb-0 mt-1">
                                                        @foreach ($meta->paymentTransactions as $ptx)
                                                            <li>
                                                                <a href="{{ route('transactions.index', ['month' => $ptx->reference_month, 'year' => $ptx->reference_year]) }}">{{ $ptx->date->format('d/m/Y') }}</a>
                                                                — R$ {{ number_format((float) $ptx->amount, 2, ',', '.') }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <span class="badge text-bg-warning text-dark">Aberta</span>
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editStatementModal"
                                                    data-edit-action="{{ route('credit-card-statements.update', [$cycle->account, $cycle->reference_year, $cycle->reference_month]) }}"
                                                    data-edit-subtitle="{{ $cycle->account->name }} — {{ sprintf('%02d/%d', $cycle->reference_month, $cycle->reference_year) }}"
                                                    data-edit-due="{{ $editDueValue }}"
                                                >Editar</button>
                                                @if ($showPaymentForms)
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#payStatementModal"
                                                        data-pay-action="{{ route('credit-card-statements.attach-payment', [$cycle->account, $cycle->reference_year, $cycle->reference_month]) }}"
                                                        data-pay-subtitle="{{ $cycle->account->name }} — {{ sprintf('%02d/%d', $cycle->reference_month, $cycle->reference_year) }}"
                                                        data-pay-hint="{{ $payHint }}"
                                                        data-pay-amount-placeholder="{{ $payAmtPlaceholder }}"
                                                        data-pay-date-default="{{ now()->format('Y-m-d') }}"
                                                    >Pagamento</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="modal fade" id="payStatementModal" tabindex="-1" aria-labelledby="payStatementModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                    <form id="payStatementForm" method="POST" action="#">
                                        @csrf
                                        <div class="modal-header">
                                            <h2 class="modal-title h5 mb-0" id="payStatementModalLabel">Pagamento da fatura</h2>
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
                                                    <input type="date" id="payStatementPaidDate" name="paid_date" class="form-control mt-1" required value="{{ old('paid_date', now()->format('Y-m-d')) }}">
                                                    <x-input-error :messages="$errors->get('paid_date')" class="mt-2" />
                                                </div>
                                                <div>
                                                    <x-input-label for="payStatementAmount" value="Valor (opcional)" />
                                                    <input type="text" inputmode="decimal" id="payStatementAmount" name="amount" class="form-control mt-1" value="{{ old('amount') }}" placeholder="">
                                                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                                </div>
                                            </div>
                                            <p class="small text-secondary mt-3 mb-0">Para desfazer um pagamento, exclua o lançamento correspondente em <a href="{{ route('transactions.index') }}">Lançamentos</a>.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <x-primary-button type="submit">Criar lançamento</x-primary-button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="editStatementModal" tabindex="-1" aria-labelledby="editStatementModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form id="editStatementForm" method="POST" action="#">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h2 class="modal-title h5 mb-0" id="editStatementModalLabel">Editar fatura</h2>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small text-secondary mb-3" id="editStatementSubtitle"></p>
                                            <div class="mb-0">
                                                <x-input-label for="editStatementDue" value="Vencimento" />
                                                <input type="date" name="due_date" id="editStatementDue" class="form-control mt-1" value="{{ old('due_date') }}">
                                                <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <x-primary-button type="submit">Salvar</x-primary-button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <p class="small text-secondary mt-3 mb-0">
                            <a href="{{ route('transactions.index') }}">Lançamentos</a> — para mudar o total da fatura, ajuste ou exclua as despesas no cartão naquele mês de referência.
                        </p>

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
                                (function () {
                                    const editModalEl = document.getElementById('editStatementModal');
                                    const editForm = document.getElementById('editStatementForm');
                                    const editSubtitleEl = document.getElementById('editStatementSubtitle');
                                    const editDueInput = document.getElementById('editStatementDue');
                                    if (editModalEl && editForm) {
                                        editModalEl.addEventListener('show.bs.modal', function (e) {
                                            const btn = e.relatedTarget;
                                            if (!btn || !btn.hasAttribute('data-edit-action')) return;
                                            editForm.action = btn.getAttribute('data-edit-action');
                                            if (editSubtitleEl) {
                                                editSubtitleEl.textContent = btn.getAttribute('data-edit-subtitle') || '';
                                            }
                                            if (editDueInput) {
                                                editDueInput.value = btn.getAttribute('data-edit-due') || '';
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
                                                payDateInput.value = ddef;
                                            }
                                            if (payAccSelect) {
                                                payAccSelect.value = '';
                                            }
                                            if (payPmSelect && payPmSelect.options.length) {
                                                payPmSelect.selectedIndex = 0;
                                            }
                                        });
                                    }

                                    @if ($openEditReopen)
                                    document.addEventListener('DOMContentLoaded', function () {
                                        if (!editForm || !editModalEl) return;
                                        editForm.action = {!! json_encode($openEditUpdateUrl) !!};
                                        if (editSubtitleEl) {
                                            editSubtitleEl.textContent = {!! json_encode($openEditSubtitleJs) !!};
                                        }
                                        if (editDueInput) editDueInput.value = {!! json_encode(old('due_date', '')) !!};
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
                                })();
                            </script>
                        @endpush
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
