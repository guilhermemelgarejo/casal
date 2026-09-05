@php
    use App\Models\Account;

    $regularAccounts = $accounts->where('kind', Account::KIND_REGULAR)->sortByDesc(fn ($account) => (float) $account->balance)->values();
    $creditCardAccounts = $accounts->where('kind', Account::KIND_CREDIT_CARD)->values();
    $storeModalOpen = $errors->any() && old('_form') === 'account-store';
    $transferModalOpen = $errors->any() && old('_form') === 'account-transfer';
    $kindOld = old('_form') === 'account-store' ? old('kind', Account::KIND_REGULAR) : Account::KIND_REGULAR;
    $regularBalanceTotal = (float) $regularAccounts->sum(fn ($account) => (float) $account->balance);
    $trackedCards = $creditCardAccounts->filter(fn ($account) => $account->tracksCreditCardLimit());
    $creditLimitTotal = (float) $trackedCards->sum(fn ($account) => (float) $account->credit_card_limit_total);
    $creditLimitAvailable = (float) $trackedCards->sum(fn ($account) => (float) ($account->credit_card_limit_available ?? 0));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="dz-page-title">Contas & Cartões</h1>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Gerenciamento de contas correntes, cartões e limites
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        <button type="button" class="dz-btn dz-btn-primary" id="btn-new-account" title="Cadastrar conta corrente ou cartão de crédito" data-bs-toggle="modal" data-bs-target="#modalNewAccount">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Nova Conta / Cartão
        </button>
    </x-slot>

    <div class="container-xxl py-4 px-3 px-lg-4 accounts-page">
        @if (session('success'))
            <x-alert type="success" class="mb-4" :message="session('success')" />
        @endif

        @if ($errors->any() && old('_form') === 'account-transfer')
            <x-alert type="danger" class="mb-4" title="Não foi possível concluir a transferência">
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <!-- TOP KPIS DUOZEN 2.0 -->
        <section class="dz-kpi-grid mb-4">
            <!-- Saldo em Contas -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Saldo em Contas Correntes</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                        🏦
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value {{ $regularBalanceTotal < 0 ? 'text-danger' : 'text-primary' }} dz-privacy-blur">
                        R$ {{ number_format($regularBalanceTotal, 2, ',', '.') }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>{{ $regularAccounts->count() }} conta(s) cadastrada(s)</span>
                        @if($canCreateAccountTransfer)
                            <a href="#" data-bs-toggle="modal" data-bs-target="#modalAccountTransfer" style="color: var(--dz-primary); font-weight: 700; text-decoration: none;">Transferir ⇄</a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Total de Contas -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Contas Bancárias</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                        💼
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value" style="color: var(--dz-text-title);">
                        {{ $regularAccounts->count() }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>{{ $accounts->count() }} itens financeiros</span>
                        <button type="button" class="btn btn-link p-0 fw-bold" style="color: var(--dz-primary); text-decoration: none; font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#modalNewAccount">+ Nova conta</button>
                    </div>
                </div>
            </div>

            <!-- Limite Disponível de Cartões -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Limite Disponível (Cartões)</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--warning">
                        💳
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-success dz-privacy-blur">
                        R$ {{ number_format($creditLimitAvailable, 2, ',', '.') }}
                    </div>
                    @php
                        $usedTotalLimit = max(0, $creditLimitTotal - $creditLimitAvailable);
                        $usedLimitPct = $creditLimitTotal > 0 ? min(100, round(($usedTotalLimit / $creditLimitTotal) * 100)) : 0;
                    @endphp
                    <div class="dz-progress-bar">
                        <div class="dz-progress-bar__fill" style="width: {{ $usedLimitPct }}%; background: #F59E0B;"></div>
                    </div>
                    <div class="dz-kpi-card__footer" style="margin-top: 0.5rem;">
                        <span>Limite Total: <strong class="dz-privacy-blur">R$ {{ number_format($creditLimitTotal, 2, ',', '.') }}</strong></span>
                        <a href="{{ route('credit-card-statements.index') }}" style="color: var(--dz-primary); font-weight: 700; text-decoration: none;">Faturas ↗</a>
                    </div>
                </div>
            </div>

            <!-- Total de Cartões -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Cartões de Crédito</span>
                    <div class="dz-kpi-card__icon-box" style="background: rgba(14, 165, 233, 0.15); color: #0284C7;">
                        💎
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value" style="color: var(--dz-text-title);">
                        {{ $creditCardAccounts->count() }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>{{ $trackedCards->count() }} com limite monitorado</span>
                        <a href="{{ route('credit-card-statements.index') }}" style="color: var(--dz-primary); font-weight: 700; text-decoration: none;">Ver faturas ↗</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- SEÇÃO 1: CONTAS BANCÁRIAS & SALDOS -->
        <section class="dz-card p-3 p-lg-4 mb-4" style="background: var(--dz-bg-card); border-radius: var(--dz-radius-lg); border: 1px solid var(--dz-border);">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom" style="border-color: var(--dz-border-subtle) !important;">
                <div class="d-flex align-items-center gap-2">
                    <span style="font-size: 1.1rem;">🏦</span>
                    <h3 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Contas Bancárias & Saldos</h3>
                    <span class="badge rounded-pill" style="background: var(--dz-primary-subtle); color: var(--dz-primary); font-size: 0.72rem; font-weight: 700;">
                        {{ $regularAccounts->count() }}
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if ($canCreateAccountTransfer)
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalAccountTransfer" style="font-size: 0.75rem; font-weight: 700;">
                            Transferir entre contas ⇄
                        </button>
                    @endif
                </div>
            </div>

            @if($regularAccounts->isEmpty())
                <div class="text-center py-4 px-3" style="background: var(--dz-bg-card-subtle); border-radius: var(--dz-radius-md); border: 1px dashed var(--dz-border);">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏦</div>
                    <p class="fw-bold mb-1" style="color: var(--dz-text-title);">Nenhuma conta corrente cadastrada</p>
                    <p class="small text-secondary mb-3">Cadastre suas contas bancárias para registrar receitas, despesas e transferências.</p>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalNewAccount">
                        + Adicionar Conta
                    </button>
                </div>
            @else
                <div class="row g-3">
                    @foreach ($regularAccounts as $account)
                        <div class="col-12 col-md-6 col-xl-4">
                            @include('accounts.partials.account-card', ['account' => $account])
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <!-- SEÇÃO 2: CARTÕES DE CRÉDITO -->
        <section class="dz-card p-3 p-lg-4 mb-4" style="background: var(--dz-bg-card); border-radius: var(--dz-radius-lg); border: 1px solid var(--dz-border);">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom" style="border-color: var(--dz-border-subtle) !important;">
                <div class="d-flex align-items-center gap-2">
                    <span style="font-size: 1.1rem;">💳</span>
                    <h3 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Cartões de Crédito</h3>
                    <span class="badge rounded-pill" style="background: var(--dz-primary-subtle); color: var(--dz-primary); font-size: 0.72rem; font-weight: 700;">
                        {{ $creditCardAccounts->count() }}
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('credit-card-statements.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size: 0.75rem; font-weight: 700;">
                        Ver todas as Faturas ↗
                    </a>
                </div>
            </div>

            @if($creditCardAccounts->isEmpty())
                <div class="text-center py-4 px-3" style="background: var(--dz-bg-card-subtle); border-radius: var(--dz-radius-md); border: 1px dashed var(--dz-border);">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">💳</div>
                    <p class="fw-bold mb-1" style="color: var(--dz-text-title);">Nenhum cartão cadastrado ainda</p>
                    <p class="small text-secondary mb-3">Cadastre seus cartões de crédito para acompanhar faturas e limites.</p>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalNewAccount">
                        + Adicionar Cartão
                    </button>
                </div>
            @else
                <div class="row g-3">
                    @foreach ($creditCardAccounts as $account)
                        <div class="col-12 col-md-6 col-xl-4">
                            @include('accounts.partials.account-card', ['account' => $account])
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
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

                        <div id="account-yields-wrap" class="{{ $kindOld === Account::KIND_CREDIT_CARD ? 'd-none' : '' }}">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="yields_interest" name="yields_interest" value="1" {{ old('_form') === 'account-store' && old('yields_interest') ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="yields_interest">
                                    Conta com rendimentos (juros/cofrinho)
                                </label>
                            </div>
                            <p class="form-text mb-0">Ative se esta conta rende juros/CDI periodicamente para poder lançar rendimentos nela a qualquer momento.</p>
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
                        <x-primary-button type="submit" class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Salvar a nova conta ou cartão">Cadastrar</x-primary-button>
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
                const yieldsWrap = document.getElementById('account-yields-wrap');
                if (!typeSel || !wrap) return;
                const cardKind = @json(Account::KIND_CREDIT_CARD);
                function syncDueDayField() {
                    const isCard = typeSel.value === cardKind;
                    wrap.classList.toggle('d-none', !isCard);
                    if (limitWrap) limitWrap.classList.toggle('d-none', !isCard);
                    if (yieldsWrap) yieldsWrap.classList.toggle('d-none', isCard);
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
