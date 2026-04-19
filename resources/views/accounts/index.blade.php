@php
    use App\Models\Account;

    $regularAccounts = $accounts->where('kind', Account::KIND_REGULAR)->values();
    $creditCardAccounts = $accounts->where('kind', Account::KIND_CREDIT_CARD)->values();
    $storeModalOpen = $errors->any() && old('_form') === 'account-store';
    $transferModalOpen = $errors->any() && old('_form') === 'account-transfer';
    $kindOld = old('_form') === 'account-store' ? old('kind', Account::KIND_REGULAR) : Account::KIND_REGULAR;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-0 accounts-hero-title">Gerenciar contas e cartões</h2>
                <p class="small text-secondary mb-0 mt-1">Cadastre contas correntes e cartões para lançamentos e faturas.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 justify-content-md-end">
                @if ($canCreateAccountTransfer)
                    <button
                        type="button"
                        class="btn btn-outline-primary rounded-pill px-4 py-2 flex-shrink-0"
                        id="btn-account-transfer"
                        title="Registar transferência entre contas correntes"
                        data-bs-toggle="modal"
                        data-bs-target="#modalAccountTransfer"
                    >
                        Transferir entre contas
                    </button>
                @endif
                <button type="button" class="btn btn-primary rounded-pill px-4 py-2 flex-shrink-0" id="btn-new-account" title="Cadastrar conta corrente ou cartão de crédito" data-bs-toggle="modal" data-bs-target="#modalNewAccount">
                    Nova conta ou cartão
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-4 accounts-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif

            @if ($errors->any() && old('_form') === 'account-transfer')
                <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <div class="pt-1">
                        <p class="fw-semibold mb-1">Não foi possível concluir a transferência</p>
                        <ul class="mb-0 ps-3 small">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="row g-4 align-items-start">
                <div class="col-12">
                    <div class="row g-4 g-lg-3 gx-lg-4 align-items-start accounts-lists-row">
                        <div class="col-12 col-lg-6">
                            <section class="h-100" aria-labelledby="accounts-regular-heading">
                                <div class="accounts-list-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <div class="min-w-0">
                                        <h3 class="h5 mb-0" id="accounts-regular-heading">Suas contas</h3>
                                        <p class="small text-secondary mb-0">Contas correntes — dinheiro, débito, Pix ou boleto.</p>
                                    </div>
                                    @if ($regularAccounts->isNotEmpty())
                                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2 flex-shrink-0">
                                            {{ $regularAccounts->count() }} {{ $regularAccounts->count() === 1 ? 'conta' : 'contas' }}
                                        </span>
                                    @endif
                                </div>

                                <div class="vstack gap-3">
                                    @forelse ($regularAccounts as $account)
                                        @include('accounts.partials.account-card', ['account' => $account])
                                    @empty
                                        <div class="accounts-empty text-center py-4 px-3">
                                            <div class="rounded-3 bg-white bg-opacity-50 d-inline-flex align-items-center justify-content-center p-3 mb-3 shadow-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="rgb(var(--bs-primary-rgb))" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                            </div>
                                            <p class="fw-semibold text-body mb-1">Nenhuma conta corrente ainda</p>
                                            <p class="small text-secondary mb-0 mx-auto" style="max-width: 18rem;">Use <strong>Nova conta ou cartão</strong> e escolha tipo Conta.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </section>
                        </div>

                        <div class="col-12 col-lg-6">
                            <section class="h-100" aria-labelledby="accounts-cards-heading">
                                <div class="accounts-list-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <div class="min-w-0">
                                        <h3 class="h5 mb-0" id="accounts-cards-heading">Seus cartões</h3>
                                        <p class="small text-secondary mb-0">Cartões — faturas, parcelas e limite.</p>
                                    </div>
                                    @if ($creditCardAccounts->isNotEmpty())
                                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2 flex-shrink-0">
                                            {{ $creditCardAccounts->count() }} {{ $creditCardAccounts->count() === 1 ? 'cartão' : 'cartões' }}
                                        </span>
                                    @endif
                                </div>

                                <div class="vstack gap-3">
                                    @forelse ($creditCardAccounts as $account)
                                        @include('accounts.partials.account-card', ['account' => $account])
                                    @empty
                                        <div class="accounts-empty text-center py-4 px-3">
                                            <div class="rounded-3 bg-white bg-opacity-50 d-inline-flex align-items-center justify-content-center p-3 mb-3 shadow-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="rgb(var(--bs-primary-rgb))" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                            </div>
                                            <p class="fw-semibold text-body mb-1">Nenhum cartão ainda</p>
                                            <p class="small text-secondary mb-0 mx-auto" style="max-width: 18rem;">Use <strong>Nova conta ou cartão</strong> e escolha <strong>Cartão de crédito</strong>.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="modalNewAccount"
        tabindex="-1"
        aria-labelledby="modalNewAccountLabel"
        aria-hidden="true"
        data-open-on-load="{{ $storeModalOpen ? '1' : '0' }}"
    >
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form action="{{ route('accounts.store') }}" method="POST" class="d-flex flex-column">
                    @csrf
                    <input type="hidden" name="_form" value="account-store">

                    <div class="modal-header align-items-start accounts-modal-new-head">
                        <div class="pe-3">
                            <h2 class="modal-title h5 mb-1" id="modalNewAccountLabel">Nova conta ou cartão</h2>
                            <p class="small text-secondary mb-0 fw-normal">Nome, cor e tipo — campos extras para cartão de crédito.</p>
                        </div>
                        <button type="button" class="btn-close flex-shrink-0 mt-1" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body vstack gap-4">
                        <div>
                            <x-input-label for="name" value="Nome" />
                            <x-text-input id="name" name="name" type="text" class="mt-1" required placeholder="Ex: Nubank, Itaú, carteira..." value="{{ old('_form') === 'account-store' ? old('name') : '' }}" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="type" value="Tipo" />
                            <select id="type" name="kind" class="form-select mt-1" required>
                                <option value="{{ Account::KIND_REGULAR }}" {{ $kindOld === Account::KIND_REGULAR ? 'selected' : '' }}>Conta</option>
                                <option value="{{ Account::KIND_CREDIT_CARD }}" {{ $kindOld === Account::KIND_CREDIT_CARD ? 'selected' : '' }}>Cartão de crédito</option>
                            </select>
                            <x-input-error :messages="$errors->get('kind')" class="mt-2" />
                            <p class="form-text mb-0">Conta: dinheiro, débito, Pix ou boleto. Cartão: faturas e parcelamento.</p>
                        </div>

                        <div>
                            <x-input-label for="color" value="Cor de identificação" />
                            <input type="color" id="color" name="color" value="{{ old('_form') === 'account-store' ? old('color', '#4f46e5') : '#4f46e5' }}" class="form-control form-control-color w-100 mt-1">
                            <x-input-error :messages="$errors->get('color')" class="mt-2" />
                        </div>

                        <div id="account-due-day-wrap" class="{{ $kindOld === Account::KIND_CREDIT_CARD ? '' : 'd-none' }}">
                            <x-input-label for="credit_card_invoice_due_day" value="Dia de vencimento da fatura" />
                            <x-text-input id="credit_card_invoice_due_day" name="credit_card_invoice_due_day" type="number" min="1" max="31" class="mt-1" placeholder="Ex.: 10 (padrão se vazio)" value="{{ old('_form') === 'account-store' ? old('credit_card_invoice_due_day') : '' }}" />
                            <x-input-error :messages="$errors->get('credit_card_invoice_due_day')" class="mt-2" />
                        </div>

                        <div id="account-limit-wrap" class="{{ $kindOld === Account::KIND_CREDIT_CARD ? '' : 'd-none' }}">
                            <x-input-label for="credit_card_limit_total" value="Limite total do cartão (R$)" />
                            <x-text-input id="credit_card_limit_total" name="credit_card_limit_total" type="number" step="0.01" min="0.01" class="mt-1" placeholder="Opcional — ex.: 5000" value="{{ old('_form') === 'account-store' ? old('credit_card_limit_total') : '' }}" />
                            <x-input-error :messages="$errors->get('credit_card_limit_total')" class="mt-2" />
                            <p class="form-text mb-0">Sem limite, o cartão não é controlado nos lançamentos. Com limite, o disponível considera faturas em aberto.</p>
                        </div>
                    </div>

                    <div class="modal-footer flex-wrap gap-2 border-top">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar sem cadastrar" data-bs-dismiss="modal">Cancelar</button>
                        <x-primary-button type="submit" class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Guardar a nova conta ou cartão">Cadastrar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($canCreateAccountTransfer)
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
                                <x-input-label for="transfer_from" value="Conta de origem" />
                                <select id="transfer_from" name="from_account_id" class="form-select mt-1" required>
                                    <option value="" disabled @selected(old('_form') !== 'account-transfer' || ! old('from_account_id'))>Selecione…</option>
                                    @foreach ($regularAccounts as $acc)
                                        <option
                                            value="{{ $acc->id }}"
                                            data-balance-label="{{ number_format((float) $acc->balance, 2, ',', '.') }}"
                                            @selected(old('_form') === 'account-transfer' && (int) old('from_account_id') === $acc->id)
                                        >
                                            {{ $acc->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="form-text mb-0" id="transfer_from_meta" aria-live="polite"></p>
                                <x-input-error :messages="$errors->get('from_account_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_to" value="Conta de destino" />
                                <select id="transfer_to" name="to_account_id" class="form-select mt-1" required>
                                    <option value="" disabled @selected(old('_form') !== 'account-transfer' || ! old('to_account_id'))>Selecione…</option>
                                    @foreach ($regularAccounts as $acc)
                                        <option
                                            value="{{ $acc->id }}"
                                            data-balance-label="{{ number_format((float) $acc->balance, 2, ',', '.') }}"
                                            @selected(old('_form') === 'account-transfer' && (int) old('to_account_id') === $acc->id)
                                        >
                                            {{ $acc->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="form-text mb-0" id="transfer_to_meta" aria-live="polite"></p>
                                <x-input-error :messages="$errors->get('to_account_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_amount" value="Valor (R$)" />
                                <x-text-input
                                    id="transfer_amount"
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
                                <x-input-label for="transfer_date" value="Data" />
                                <x-text-input
                                    id="transfer_date"
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
                                <x-input-label for="transfer_pm" value="Forma de pagamento (registro)" />
                                <select id="transfer_pm" name="payment_method" class="form-select mt-1" required>
                                    @foreach ($transferPaymentMethods as $pm)
                                        <option value="{{ $pm }}" @selected(old('_form') === 'account-transfer' ? old('payment_method') === $pm : $loop->first)>
                                            {{ $pm }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="transfer_desc" value="Descrição (opcional)" />
                                <x-text-input
                                    id="transfer_desc"
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
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar sem transferir" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Registar a transferência entre as contas escolhidas">Confirmar transferência</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            (function () {
                const modalEl = document.getElementById('modalNewAccount');
                const typeSel = document.getElementById('type');
                const wrap = document.getElementById('account-due-day-wrap');
                const limitWrap = document.getElementById('account-limit-wrap');
                if (!typeSel || !wrap) return;
                const cardKind = @json(Account::KIND_CREDIT_CARD);
                function syncDueDayField() {
                    const isCard = typeSel.value === cardKind;
                    wrap.classList.toggle('d-none', !isCard);
                    if (limitWrap) limitWrap.classList.toggle('d-none', !isCard);
                }
                typeSel.addEventListener('change', syncDueDayField);
                syncDueDayField();
                if (modalEl) {
                    modalEl.addEventListener('shown.bs.modal', function () {
                        syncDueDayField();
                        const nameInput = document.getElementById('name');
                        if (nameInput) nameInput.focus();
                    });
                    if (modalEl.dataset.openOnLoad === '1') {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                }
                const transferModal = document.getElementById('modalAccountTransfer');
                if (transferModal && transferModal.dataset.openOnLoad === '1') {
                    bootstrap.Modal.getOrCreateInstance(transferModal).show();
                }

                const fromSel = document.getElementById('transfer_from');
                const toSel = document.getElementById('transfer_to');
                const fromMeta = document.getElementById('transfer_from_meta');
                const toMeta = document.getElementById('transfer_to_meta');

                const syncTransferMeta = () => {
                    if (fromMeta && fromSel) {
                        const opt = fromSel.selectedOptions?.[0];
                        const bal = opt?.dataset?.balanceLabel;
                        fromMeta.textContent = fromSel.value ? `Saldo atual: R$ ${bal || '—'}` : '';
                    }
                    if (toMeta && toSel) {
                        const opt = toSel.selectedOptions?.[0];
                        const bal = opt?.dataset?.balanceLabel;
                        toMeta.textContent = toSel.value ? `Saldo atual: R$ ${bal || '—'}` : '';
                    }
                };

                fromSel?.addEventListener('change', syncTransferMeta);
                toSel?.addEventListener('change', syncTransferMeta);
                syncTransferMeta();
            })();
        </script>
    @endpush
</x-app-layout>
