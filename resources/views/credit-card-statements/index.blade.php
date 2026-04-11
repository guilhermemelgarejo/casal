<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 mb-0 cc-statements-page-title">Faturas de cartão de crédito</h2>
            <p class="small text-secondary mb-0 mt-1">Faturas por ciclo de cada cartão, pagamentos e itens do período.</p>
        </div>
    </x-slot>

    <div class="py-4 cc-statements-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif

            @if ($errors->has('payment'))
                <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                    <span class="pt-1">{{ $errors->first('payment') }}</span>
                </div>
            @endif

            <div class="card border-0 shadow-sm cc-statements-shell overflow-hidden">
                <div class="card-body p-4 p-lg-5">
                    @if ($cardAccounts->isEmpty())
                        <div class="cc-picker-empty text-center py-5 px-3">
                            <div class="cc-picker-empty-icon rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                            </div>
                            <h3 class="h5 mb-2">Nenhum cartão cadastrado</h3>
                            <p class="small text-secondary mb-4 mx-auto cc-picker-empty-text">
                                Cadastre um <strong>cartão de crédito</strong> em <a href="{{ route('accounts.index') }}">Contas</a> e registe compras em <a href="{{ route('transactions.index') }}">Lançamentos</a> para ver as faturas aqui.
                            </p>
                            <a href="{{ route('accounts.index') }}" class="btn btn-primary rounded-pill px-4">Ir para Contas</a>
                        </div>
                    @else
                        @if ($filterCardId === null)
                            <div class="card border-0 shadow-sm cc-picker-hero mb-4 overflow-hidden">
                                <div class="cc-picker-hero-head">
                                    <h3 class="h5 mb-1 fw-semibold">Escolher cartão</h3>
                                    <p class="small text-secondary mb-0">Um cartão de cada vez — depois vê os ciclos, totais e pagamentos.</p>
                                </div>
                                <div class="card-body p-3 p-md-4 p-lg-5 cc-picker-hero-body border-top border-secondary-subtle">
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
                            <div class="cc-picker-toolbar card border-0 shadow-sm mb-4 overflow-hidden">
                                <div class="cc-picker-toolbar-inner d-flex flex-column align-items-stretch gap-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <span class="small fw-semibold text-secondary text-uppercase cc-picker-toolbar-label">Trocar cartão</span>
                                        <a href="{{ route('credit-card-statements.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 align-self-start">Voltar à escolha</a>
                                    </div>
                                    <div class="cc-picker-grid cc-picker-grid--toolbar justify-content-center">
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
                            </div>
                        @endif

                        @if ($filterCardId !== null)
                            @if ($invoiceCycles->isEmpty())
                        <div class="alert alert-info mb-0">
                            Não há faturas com despesas neste cartão. Registre compras em <a href="{{ route('transactions.index') }}">Lançamentos</a> com este cartão ou escolha outro cartão.
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
                                            } elseif ($hasPayments) {
                                                $statementHeaderClass = 'cc-statement-header--partial';
                                            } else {
                                                $statementHeaderClass = 'cc-statement-header--open';
                                            }
                                        @endphp
                                        <div
                                            id="statement-cycle-{{ $cycle->account->id }}-{{ $cycle->reference_year }}-{{ $cycle->reference_month }}"
                                            class="card shadow-sm border-secondary-subtle cc-statement-card"
                                        >
                                            <div class="card-header cc-statement-header d-flex flex-wrap justify-content-between align-items-start gap-2 py-3 border-0 {{ $statementHeaderClass }}">
                                                <div class="min-w-0">
                                                    <div class="fw-semibold">{{ $cycle->account->name }}</div>
                                                    <div class="small text-secondary">Ref. {{ sprintf('%02d/%d', $cycle->reference_month, $cycle->reference_year) }}</div>
                                                </div>
                                                <div class="text-md-end">
                                                    <div class="fs-5 fw-semibold text-nowrap">R$ {{ number_format($cycle->spent_total, 2, ',', '.') }}</div>
                                                    <div class="small text-secondary">Total no cartão</div>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="row g-3 g-lg-4">
                                                    <div class="col-md-4 col-lg-3">
                                                        <div class="small text-secondary text-uppercase mb-1" style="letter-spacing: 0.02em; font-size: 0.7rem;">Vencimento</div>
                                                        <div class="small">
                                                            @if ($dueForDisplay)
                                                                @if ($dueIsSuggestion)
                                                                    <span class="text-secondary" title="Conforme o cartão em Contas; confirme em Editar ou ao registrar pagamento">Sug. {{ $dueForDisplay->format('d/m/Y') }}</span>
                                                                @else
                                                                    {{ $dueForDisplay->format('d/m/Y') }}
                                                                @endif
                                                            @else
                                                                <span class="text-secondary">—</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-md-8 col-lg-6">
                                                        <div class="small text-secondary text-uppercase mb-1" style="letter-spacing: 0.02em; font-size: 0.7rem;">Pagamento</div>
                                                        <div class="small">
                                                            @if ($isPaid)
                                                                <span class="badge text-bg-success">Paga</span>
                                                                <div class="text-secondary mt-1">{{ $meta->paid_at?->format('d/m/Y') }}</div>
                                                                @if ($hasPayments)
                                                                    <ul class="list-unstyled mb-0 mt-2">
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
                                                                <div class="text-secondary mt-1">
                                                                    Pago: R$ {{ number_format((float) $meta->paymentsTotal(), 2, ',', '.') }}
                                                                    · Pendente: R$ {{ number_format($remaining, 2, ',', '.') }}
                                                                </div>
                                                                <ul class="list-unstyled mb-0 mt-2">
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
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-3">
                                                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
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
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-outline-primary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editStatementModal"
                                                                data-edit-action="{{ route('credit-card-statements.update', [$cycle->account, $cycle->reference_year, $cycle->reference_month]) }}"
                                                                data-edit-subtitle="{{ $cycle->account->name }} — {{ sprintf('%02d/%d', $cycle->reference_month, $cycle->reference_year) }}"
                                                                data-edit-due="{{ $editDueValue }}"
                                                            >Editar</button>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-outline-secondary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#statementItemsModal"
                                                                data-statement-subtitle="{{ $cycleSubtitle }}"
                                                                data-statement-cycle-key="{{ $cycle->cycle_key }}"
                                                            >Itens da fatura</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                        </div>

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
                                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="payStatementModal" tabindex="-1" aria-labelledby="payStatementModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
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
                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                                            <x-primary-button type="submit" class="rounded-pill px-4">Criar lançamento</x-primary-button>
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
                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                                            <x-primary-button type="submit" class="rounded-pill px-4">Salvar</x-primary-button>
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
                                                tdAmt.className = 'text-end small fw-semibold text-nowrap text-body';
                                                tdAmt.textContent = 'R$ ' + (row.amount_str || '');
                                                tr.appendChild(tdAmt);

                                                const tdLink = document.createElement('td');
                                                tdLink.className = 'text-end pe-3';
                                                const a = document.createElement('a');
                                                a.className = 'btn btn-sm btn-outline-primary rounded-pill px-3';
                                                a.href = url;
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
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
