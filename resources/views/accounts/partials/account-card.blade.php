@php
    use App\Models\Account;

    $editOpen = $errors->any() && old('_form') === 'account-update-'.$account->id;
    $isCard = $account->isCreditCard();
    $yields = $account->yieldsInterest();
    $accBal = (float) $account->balance;
    $typeLabel = $isCard ? 'Cartão' : 'Conta';
@endphp

<div class="card border-0 accounts-modern-card shadow-sm h-100" style="--card-accent: {{ $account->color }}">
    <div class="accounts-modern-card__accent" aria-hidden="true"></div>

    <div class="card-body p-3 p-sm-4 d-flex flex-column justify-content-between">
        <div>
            {{-- Cabeçalho --}}
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <div class="accounts-modern-card__avatar flex-shrink-0" style="background-color: {{ $account->color }}">
                        @if ($isCard)
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v2a2 2 0 002 2z" /></svg>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h3 class="accounts-modern-card__title mb-1 text-truncate">{{ $account->name }}</h3>
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <span class="accounts-modern-card__badge {{ $isCard ? 'accounts-modern-card__badge--card' : 'accounts-modern-card__badge--regular' }}">
                                {{ $typeLabel }}
                            </span>
                            @if($yields)
                                <span class="accounts-modern-card__badge accounts-modern-card__badge--yield">
                                    📈 Rende juros
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="accounts-modern-card__actions d-flex align-items-center gap-1 flex-shrink-0">
                    @if($yields)
                        <button
                            type="button"
                            class="btn btn-sm btn-success rounded-pill px-2.5 py-1 fw-semibold shadow-sm d-inline-flex align-items-center gap-1 text-white me-1"
                            style="font-size: 0.75rem;"
                            title="Lançar rendimento/juros nesta conta"
                            data-bs-toggle="modal"
                            data-bs-target="#modalAccountYield{{ $account->id }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Rendimento
                        </button>
                    @endif
                    <button
                        type="button"
                        class="btn btn-sm rounded-circle accounts-action-btn"
                        title="Editar conta"
                        data-bs-toggle="collapse"
                        data-bs-target="#edit-account-{{ $account->id }}"
                        aria-expanded="{{ $editOpen ? 'true' : 'false' }}"
                        aria-controls="edit-account-{{ $account->id }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    </button>
                    <form class="d-inline" action="{{ route('accounts.destroy', $account) }}" method="POST" data-confirm-title="Excluir conta" data-confirm="Excluir esta conta? Movimentações vinculadas ficarão sem conta." data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm text-danger rounded-circle accounts-action-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Excluir conta permanentemente">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Métricas e Valores --}}
            @if ($isCard)
                @php
                    $currentInvoice = (float) ($account->current_invoice_amount ?? $account->currentInvoiceAmount());
                    $hasLimit = $account->tracksCreditCardLimit();
                    $limitTot = $hasLimit ? (float) $account->credit_card_limit_total : 0;
                    $limitAvail = $hasLimit ? (float) ($account->credit_card_limit_available ?? 0) : 0;
                    $limitUsed = max(0, $limitTot - $limitAvail);
                    $percentUsed = $limitTot > 0 ? min(100, round(($limitUsed / $limitTot) * 100)) : 0;
                @endphp
                <div class="mb-3">
                    @if($hasLimit)
                        <div class="d-flex align-items-baseline justify-content-between gap-2 mb-1">
                            <div>
                                <span class="text-secondary fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 0.65rem;">Disponível</span>
                                <div class="fs-4 fw-bold {{ $limitAvail < 0 ? 'text-danger' : 'text-success' }} duozen-privacy-blur">
                                    R$ {{ number_format($limitAvail, 2, ',', '.') }}
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="text-secondary small" style="font-size: 0.72rem;">Limite total</span>
                                <div class="fw-semibold text-body duozen-privacy-blur">
                                    R$ {{ number_format($limitTot, 2, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        <div class="progress mt-2" style="height: 6px; background-color: var(--bs-tertiary-bg);">
                            <div class="progress-bar {{ $percentUsed > 85 ? 'bg-danger' : ($percentUsed > 60 ? 'bg-warning' : 'bg-primary') }}" role="progressbar" style="width: {{ $percentUsed }}%;" aria-valuenow="{{ $percentUsed }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-secondary" style="font-size: 0.72rem;">{{ $percentUsed }}% utilizado</span>
                        </div>
                    @else
                        <div class="accounts-card-empty-hint mb-3 p-3 rounded-3 text-center">
                            <span class="small text-secondary">Sem limite configurado</span>
                            <div class="mt-0.5 small text-secondary opacity-75" style="font-size: 0.75rem;">O uso do cartão não é acompanhado nas faturas.</div>
                        </div>
                    @endif
                </div>
            @else
                {{-- Conta Corrente: Saldo Principal --}}
                <div class="mb-3">
                    <span class="text-secondary fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 0.65rem;">Saldo em conta</span>
                    <div class="fs-3 fw-bold {{ $accBal >= 0 ? 'text-body' : 'text-danger' }} duozen-privacy-blur mt-0.5">
                        R$ {{ number_format($accBal, 2, ',', '.') }}
                    </div>
                </div>
            @endif
        </div>

        {{-- Rodapé --}}
        <div class="pt-1 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if(!$isCard)
                    @if(count($account->getEffectivePaymentMethods()) > 0)
                        @foreach ($account->getEffectivePaymentMethods() as $pm)
                            <span class="accounts-mini-chip">{{ $pm }}</span>
                        @endforeach
                    @endif
                @else
                    <span class="accounts-mini-chip accounts-mini-chip--invoice">
                        Fatura atual: <strong class="duozen-privacy-blur">R$ {{ number_format($currentInvoice, 2, ',', '.') }}</strong>
                    </span>
                    @if($account->credit_card_invoice_due_day)
                        <span class="accounts-mini-chip">Vencimento dia {{ $account->credit_card_invoice_due_day }}</span>
                    @endif
                @endif
            </div>
            <span class="text-secondary" style="font-size: 0.72rem;">Criada em {{ $account->created_at->format('d/m/Y') }}</span>
        </div>

        {{-- Painel de Edição --}}
        <div class="collapse {{ $editOpen ? 'show' : '' }} mt-3" id="edit-account-{{ $account->id }}">
            <div class="accounts-edit-panel rounded-3 p-3 border border-secondary-subtle bg-body-tertiary">
                <form action="{{ route('accounts.update', $account) }}" method="POST" class="vstack gap-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="account-update-{{ $account->id }}">

                    <div>
                        <x-input-label for="edit-name-{{ $account->id }}" value="Nome da conta" />
                        <x-text-input id="edit-name-{{ $account->id }}" name="name" type="text" class="mt-1 rounded-3" required value="{{ old('_form') === 'account-update-'.$account->id ? old('name') : $account->name }}" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="edit-color-{{ $account->id }}" value="Cor de identificação" />
                        <input type="color" id="edit-color-{{ $account->id }}" name="color" value="{{ old('_form') === 'account-update-'.$account->id ? old('color', $account->color) : $account->color }}" class="form-control form-control-color w-100 mt-1 rounded-3">
                        <x-input-error :messages="$errors->get('color')" class="mt-1" />
                    </div>

                    @if ($account->isCreditCard())
                        <div>
                            <x-input-label for="edit-due-day-{{ $account->id }}" value="Dia de vencimento da fatura" />
                            <x-text-input id="edit-due-day-{{ $account->id }}" name="credit_card_invoice_due_day" type="number" min="1" max="31" class="mt-1 rounded-3" placeholder="Ex.: 10" value="{{ old('_form') === 'account-update-'.$account->id ? old('credit_card_invoice_due_day') : $account->credit_card_invoice_due_day }}" />
                            <x-input-error :messages="$errors->get('credit_card_invoice_due_day')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="edit-limit-{{ $account->id }}" value="Limite total do cartão (R$)" />
                            <x-text-input id="edit-limit-{{ $account->id }}" name="credit_card_limit_total" type="number" step="0.01" min="0.01" class="mt-1 rounded-3" placeholder="Ex.: 5000" value="{{ old('_form') === 'account-update-'.$account->id ? old('credit_card_limit_total') : ($account->credit_card_limit_total !== null ? $account->credit_card_limit_total : '') }}" />
                            <x-input-error :messages="$errors->get('credit_card_limit_total')" class="mt-1" />
                        </div>
                    @else
                        <div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="edit-yields-{{ $account->id }}" name="yields_interest" value="1" {{ (old('_form') === 'account-update-'.$account->id ? old('yields_interest') : $account->yields_interest) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="edit-yields-{{ $account->id }}">
                                    Conta com rendimentos (juros/cofrinho)
                                </label>
                            </div>
                            <p class="form-text mb-0 small text-secondary">Ative para poder lançar rendimentos de juros/CDI periodicamente nesta conta.</p>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-2 pt-2">
                        <x-primary-button type="submit" class="rounded-pill px-4">Salvar alterações</x-primary-button>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#edit-account-{{ $account->id }}">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if($account->yieldsInterest())
    <div class="modal fade" id="modalAccountYield{{ $account->id }}" tabindex="-1" aria-labelledby="modalAccountYieldLabel{{ $account->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div>
                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle px-2.5 py-1 small fw-semibold mb-2">
                            📈 Rendimento na conta
                        </span>
                        <h2 class="modal-title h5 mb-1" id="modalAccountYieldLabel{{ $account->id }}">Lançar rendimentos — {{ $account->name }}</h2>
                        <p class="small text-secondary mb-0">Registra o valor recebido de juros/CDI como receita, aumentando o saldo atual.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post" action="{{ route('accounts.interest.store', $account) }}">
                    @csrf
                    <div class="modal-body vstack gap-3 p-4">
                        <div>
                            <x-input-label for="yield_amount_{{ $account->id }}" value="Valor do rendimento (R$)" />
                            <x-text-input id="yield_amount_{{ $account->id }}" name="amount" type="number" step="0.01" min="0.01" class="mt-1 rounded-3" placeholder="0,00" required autofocus />
                        </div>
                        <div>
                            <x-input-label for="yield_date_{{ $account->id }}" value="Data do rendimento" />
                            <x-text-input id="yield_date_{{ $account->id }}" name="date" type="date" class="mt-1 rounded-3" value="{{ now()->toDateString() }}" required />
                        </div>
                        <div>
                            <x-input-label for="yield_desc_{{ $account->id }}" value="Descrição / Observação (opcional)" />
                            <x-text-input id="yield_desc_{{ $account->id }}" name="description" type="text" class="mt-1 rounded-3" placeholder="Ex.: Rendimento CDI de {{ now()->translatedFormat('F') }}" />
                        </div>
                    </div>
                    <div class="modal-footer border-secondary-subtle px-4 pb-4 pt-2">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <x-primary-button class="rounded-pill px-4">Confirmar rendimento</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
