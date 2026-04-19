@php
    use App\Models\Account;

    $editOpen = $errors->any() && old('_form') === 'account-update-'.$account->id;
    $typeLabel = match ($account->kind) {
        Account::KIND_CREDIT_CARD => 'Cartão de crédito',
        default => 'Conta corrente',
    };
    $isCard = $account->isCreditCard();
@endphp
<div class="card border-0 accounts-item-card shadow-sm" style="--account-accent: {{ $account->color }}">
    <div class="accounts-item-card__accent" aria-hidden="true"></div>
    <div class="card-body p-0">
        <div class="accounts-item-card__top px-3 px-sm-4 pt-3 pb-3">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3 min-w-0 flex-grow-1">
                    <div class="accounts-item-card__avatar flex-shrink-0 text-white" style="background-color: {{ $account->color }}">
                        @if ($isCard)
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v2a2 2 0 002 2z" /></svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 gap-sm-3 mb-1">
                            <h3 class="accounts-item-card__title mb-0 text-truncate">{{ $account->name }}</h3>
                            <span class="accounts-item-card__type {{ $isCard ? 'accounts-item-card__type--card' : 'accounts-item-card__type--regular' }}">
                                {{ $typeLabel }}
                            </span>
                        </div>
                        <p class="accounts-item-card__meta small mb-0">
                            <span class="accounts-item-card__meta-label">Desde</span>
                            {{ $account->created_at->format('d/m/Y') }}
                        </p>
                    </div>
                </div>
                <div class="accounts-item-card__toolbar d-flex align-items-center gap-2 flex-shrink-0">
                    <button
                        type="button"
                        class="btn btn-sm accounts-item-card__btn-edit"
                        title="Mostrar ou ocultar o formulário de edição desta conta"
                        data-bs-toggle="collapse"
                        data-bs-target="#edit-account-{{ $account->id }}"
                        aria-expanded="{{ $editOpen ? 'true' : 'false' }}"
                        aria-controls="edit-account-{{ $account->id }}"
                    >
                        Editar
                    </button>
                    <form class="d-inline" action="{{ route('accounts.destroy', $account) }}" method="POST" data-confirm-title="Excluir conta" data-confirm="Excluir esta conta? Movimentações vinculadas ficarão sem conta." data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm accounts-item-card__btn-delete" data-bs-toggle="tooltip" data-bs-placement="top" title="Excluir esta conta permanentemente">
                            Excluir
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="accounts-item-card__body px-3 px-sm-4 pb-4">
            @if ($isCard)
                @if($account->tracksCreditCardLimit())
                    <div class="accounts-item-card__metrics">
                        <div class="accounts-metric">
                            <span class="accounts-metric__label">Limite total</span>
                            <span class="accounts-metric__value">R$ {{ number_format((float) $account->credit_card_limit_total, 2, ',', '.') }}</span>
                        </div>
                        <div class="accounts-metric">
                            <span class="accounts-metric__label">Disponível</span>
                            <span class="accounts-metric__value {{ (float) ($account->credit_card_limit_available ?? 0) < 0 ? 'text-danger' : 'accounts-metric__value--positive' }}">R$ {{ number_format((float) ($account->credit_card_limit_available ?? 0), 2, ',', '.') }}</span>
                        </div>
                    </div>
                @else
                    <p class="accounts-item-card__hint mb-0 small">Sem limite configurado — o uso do cartão não é acompanhado nos lançamentos.</p>
                @endif
            @else
                @if (count($account->getEffectivePaymentMethods()) > 0)
                    <div class="accounts-item-card__chips mb-3">
                        @foreach ($account->getEffectivePaymentMethods() as $pm)
                            <span class="accounts-pm-chip">{{ $pm }}</span>
                        @endforeach
                    </div>
                @endif
                @php
                    $accBal = (float) $account->balance;
                @endphp
                <div class="accounts-metric accounts-metric--solo">
                    <span class="accounts-metric__label">Saldo atual</span>
                    <span class="accounts-metric__value accounts-metric__value--lg {{ $accBal >= 0 ? 'accounts-metric__value--positive' : 'text-danger' }}">R$ {{ number_format($accBal, 2, ',', '.') }}</span>
                </div>
            @endif
        </div>

        <div class="collapse {{ $editOpen ? 'show' : '' }}" id="edit-account-{{ $account->id }}">
            <div class="accounts-edit-panel mx-3 mx-sm-4 mb-4">
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
                        <x-primary-button type="submit" class="rounded-pill" data-bs-toggle="tooltip" data-bs-placement="top" title="Guardar alterações a nome, cor, vencimento ou limite">Salvar alterações</x-primary-button>
                        <button type="button" class="btn btn-outline-secondary rounded-pill" title="Fechar o formulário sem sair da página" data-bs-toggle="collapse" data-bs-target="#edit-account-{{ $account->id }}">Fechar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
