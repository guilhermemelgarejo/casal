@php
    use App\Models\Account;

    $regularAccounts = $accounts->where('kind', Account::KIND_REGULAR)->values();
    $creditCardAccounts = $accounts->where('kind', Account::KIND_CREDIT_CARD)->values();
    $storeModalOpen = $errors->any() && old('_form') === 'account-store';
    $kindOld = old('_form') === 'account-store' ? old('kind', Account::KIND_REGULAR) : Account::KIND_REGULAR;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-0 accounts-hero-title">Gerenciar contas e cartões</h2>
                <p class="small text-secondary mb-0 mt-1">Cadastre contas correntes e cartões para lançamentos e faturas.</p>
            </div>
            <button type="button" class="btn btn-primary rounded-pill px-4 py-2 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modalNewAccount">
                Nova conta ou cartão
            </button>
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
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <x-primary-button type="submit" class="rounded-pill px-4">Cadastrar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
            })();
        </script>
    @endpush
</x-app-layout>
