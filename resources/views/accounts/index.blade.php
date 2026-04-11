@php
    use App\Models\Account;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 gap-md-3">
            <div>
                <h2 class="h5 mb-0 accounts-hero-title">Gerenciar contas e cartões</h2>
                <p class="small text-secondary mb-0 mt-1">Cadastre contas correntes e cartões para lançamentos e faturas.</p>
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

            <div class="row g-4 align-items-start">
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm accounts-new-card h-100">
                        <div class="accounts-new-head">
                            <h3 class="h5 mb-1">Nova conta ou cartão</h3>
                            <p class="small text-secondary mb-0">Defina nome, cor e tipo — campos extras aparecem para cartão de crédito.</p>
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('accounts.store') }}" method="POST" class="vstack gap-4">
                                @csrf
                                <input type="hidden" name="_form" value="account-store">

                                <div>
                                    <x-input-label for="name" value="Nome" />
                                    <x-text-input id="name" name="name" type="text" class="mt-1" required placeholder="Ex: Nubank, Itaú, carteira..." value="{{ old('_form') === 'account-store' ? old('name') : '' }}" />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="type" value="Tipo" />
                                    <select id="type" name="kind" class="form-select mt-1" required>
                                        @php
                                            $kindOld = old('_form') === 'account-store' ? old('kind', Account::KIND_REGULAR) : Account::KIND_REGULAR;
                                        @endphp
                                        <option value="{{ Account::KIND_REGULAR }}" {{ $kindOld === Account::KIND_REGULAR ? 'selected' : '' }}>Conta</option>
                                        <option value="{{ Account::KIND_CREDIT_CARD }}" {{ $kindOld === Account::KIND_CREDIT_CARD ? 'selected' : '' }}>Cartão de crédito</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('kind')" class="mt-2" />
                                    <p class="form-text mb-0">Conta: Dinheiro, débito, Pix ou Boleto. Cartão: faturas e parcelamento.</p>
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

                                <x-primary-button class="w-100 justify-content-center rounded-pill py-2">
                                    Cadastrar
                                </x-primary-button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="accounts-list-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h3 class="h5 mb-0">Suas contas</h3>
                            <p class="small text-secondary mb-0">Toque em Editar para alterar nome, cor ou dados do cartão.</p>
                        </div>
                        @if ($accounts->isNotEmpty())
                            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2">
                                {{ $accounts->count() }} {{ $accounts->count() === 1 ? 'item' : 'itens' }}
                            </span>
                        @endif
                    </div>

                    <div class="vstack gap-3">
                        @forelse($accounts as $account)
                            @php
                                $editOpen = $errors->any() && old('_form') === 'account-update-'.$account->id;
                                $typeLabel = match ($account->kind) {
                                    Account::KIND_CREDIT_CARD => 'Cartão de crédito',
                                    default => 'Conta corrente',
                                };
                                $isCard = $account->isCreditCard();
                            @endphp
                            <div class="card border-0 shadow-sm accounts-item-card" style="--account-accent: {{ $account->color }}">
                                <div class="card-body p-4">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                                        <div class="d-flex align-items-start gap-3 min-w-0 flex-grow-1">
                                            <div
                                                class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0 shadow-sm"
                                                style="width: 3rem; height: 3rem; background-color: {{ $account->color }}"
                                            >
                                                @if ($isCard)
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                                @else
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v2a2 2 0 002 2z" /></svg>
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                    <h4 class="h5 mb-0 text-truncate">{{ $account->name }}</h4>
                                                    <span class="badge rounded-pill {{ $isCard ? 'bg-primary-subtle text-primary-emphasis border border-primary-subtle' : 'bg-secondary-subtle text-secondary-emphasis border' }}">
                                                        {{ $typeLabel }}
                                                    </span>
                                                </div>
                                                <p class="small text-secondary mb-3">Cadastrada em {{ $account->created_at->format('d/m/Y') }}</p>

                                                @if ($isCard)
                                                    @if($account->tracksCreditCardLimit())
                                                        <div class="row g-2 g-sm-3">
                                                            <div class="col-sm-6">
                                                                <div class="accounts-stat h-100">
                                                                    <span class="d-block small text-secondary text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.04em;">Limite total</span>
                                                                    <span class="fw-semibold">R$ {{ number_format((float) $account->credit_card_limit_total, 2, ',', '.') }}</span>
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <div class="accounts-stat h-100">
                                                                    <span class="d-block small text-secondary text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.04em;">Disponível</span>
                                                                    <span class="fw-semibold {{ (float) ($account->credit_card_limit_available ?? 0) < 0 ? 'text-danger' : '' }}">R$ {{ number_format((float) ($account->credit_card_limit_available ?? 0), 2, ',', '.') }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <p class="small text-secondary mb-0">Sem limite configurado neste cartão.</p>
                                                    @endif
                                                @else
                                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                                        @foreach ($account->getEffectivePaymentMethods() as $pm)
                                                            <span class="badge rounded-pill bg-body-secondary text-body border">{{ $pm }}</span>
                                                        @endforeach
                                                    </div>
                                                    @php
                                                        $accBal = (float) $account->balance;
                                                    @endphp
                                                    <div class="accounts-stat d-inline-block">
                                                        <span class="d-block small text-secondary text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.04em;">Saldo atual</span>
                                                        <span class="fw-semibold {{ $accBal >= 0 ? '' : 'text-danger' }}">R$ {{ number_format($accBal, 2, ',', '.') }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-auto">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#edit-account-{{ $account->id }}"
                                                aria-expanded="{{ $editOpen ? 'true' : 'false' }}"
                                                aria-controls="edit-account-{{ $account->id }}"
                                            >
                                                Editar
                                            </button>
                                            <form action="{{ route('accounts.destroy', $account) }}" method="POST" data-confirm-title="Excluir conta" data-confirm="Excluir esta conta? Lançamentos vinculados ficarão sem conta." data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" title="Excluir conta">
                                                    Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="collapse {{ $editOpen ? 'show' : '' }} mt-3" id="edit-account-{{ $account->id }}">
                                        <div class="accounts-edit-panel">
                                            <form action="{{ route('accounts.update', $account) }}" method="POST" class="vstack gap-4">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="_form" value="account-update-{{ $account->id }}">

                                                <div>
                                                    <x-input-label for="edit-name-{{ $account->id }}" value="Nome" />
                                                    <x-text-input id="edit-name-{{ $account->id }}" name="name" type="text" class="mt-1" required value="{{ old('_form') === 'account-update-'.$account->id ? old('name') : $account->name }}" />
                                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                                </div>

                                                <div>
                                                    <span class="d-block small fw-medium text-secondary">Tipo</span>
                                                    <p class="mb-0 mt-1">{{ $typeLabel }} <span class="text-secondary">(não pode ser alterado)</span></p>
                                                </div>

                                                <div>
                                                    <x-input-label for="edit-color-{{ $account->id }}" value="Cor" />
                                                    <input type="color" id="edit-color-{{ $account->id }}" name="color" value="{{ old('_form') === 'account-update-'.$account->id ? old('color', $account->color) : $account->color }}" class="form-control form-control-color w-100 mt-1">
                                                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                                                </div>

                                                @if ($account->isCreditCard())
                                                    <div>
                                                        <x-input-label for="edit-due-day-{{ $account->id }}" value="Dia de vencimento da fatura" />
                                                        <x-text-input id="edit-due-day-{{ $account->id }}" name="credit_card_invoice_due_day" type="number" min="1" max="31" class="mt-1" placeholder="Ex.: 10" value="{{ old('_form') === 'account-update-'.$account->id ? old('credit_card_invoice_due_day') : $account->credit_card_invoice_due_day }}" />
                                                        <p class="form-text mb-0">Vazio = sem data sugerida automaticamente nas faturas deste cartão.</p>
                                                        <x-input-error :messages="$errors->get('credit_card_invoice_due_day')" class="mt-2" />
                                                    </div>
                                                    <div>
                                                        <x-input-label for="edit-limit-{{ $account->id }}" value="Limite total do cartão (R$)" />
                                                        <x-text-input id="edit-limit-{{ $account->id }}" name="credit_card_limit_total" type="number" step="0.01" min="0.01" class="mt-1" placeholder="Ex.: 5000" value="{{ old('_form') === 'account-update-'.$account->id ? old('credit_card_limit_total') : ($account->credit_card_limit_total !== null ? $account->credit_card_limit_total : '') }}" />
                                                        <p class="form-text mb-0">Ao salvar, o limite disponível é recalculado com base nas faturas em aberto. Vazio = deixar de usar limite neste cartão.</p>
                                                        <x-input-error :messages="$errors->get('credit_card_limit_total')" class="mt-2" />
                                                    </div>
                                                @endif

                                                <div class="d-flex flex-wrap gap-2 pt-1">
                                                    <x-primary-button type="submit" class="rounded-pill">Salvar alterações</x-primary-button>
                                                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-toggle="collapse" data-bs-target="#edit-account-{{ $account->id }}">Fechar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="accounts-empty text-center py-5 px-4">
                                <div class="rounded-3 bg-white bg-opacity-50 d-inline-flex align-items-center justify-content-center p-3 mb-3 shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="rgb(var(--bs-primary-rgb))" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                </div>
                                <p class="fw-semibold text-body mb-1">Nenhuma conta ainda</p>
                                <p class="small text-secondary mb-0 mx-auto" style="max-width: 22rem;">Use o formulário ao lado para criar a primeira conta ou cartão. Elas aparecem nos lançamentos e nas faturas.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
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
            })();
        </script>
    @endpush
</x-app-layout>
