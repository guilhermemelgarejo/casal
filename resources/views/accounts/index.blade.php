@php
    use App\Support\PaymentMethods;
    use App\Models\Account;
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">
            Gerenciar contas e cartões
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container-xxl px-3 px-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                @if (session('success'))
                    <div class="alert alert-success mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card border shadow-sm h-100">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h3 class="h5 mb-0">Nova conta ou cartão</h3>
                                <p class="small text-secondary mb-0 mt-1">Nome, cor e como você usa no dia a dia</p>
                            </div>
                            <div class="card-body p-3">
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
                                        <select id="type" name="kind" class="form-select mt-1" required data-account-kind-select data-pm-target="#pm-block-store">
                                            @php
                                                $kindOld = old('_form') === 'account-store' ? old('kind', Account::KIND_REGULAR) : Account::KIND_REGULAR;
                                            @endphp
                                            <option value="{{ Account::KIND_REGULAR }}" {{ $kindOld === Account::KIND_REGULAR ? 'selected' : '' }}>Conta</option>
                                            <option value="{{ Account::KIND_CREDIT_CARD }}" {{ $kindOld === Account::KIND_CREDIT_CARD ? 'selected' : '' }}>Cartão de crédito</option>
                                        </select>
                                        <x-input-error :messages="$errors->get('kind')" class="mt-2" />
                                        <p class="form-text mb-0">Cartões são só para faturas/parcelas; contas bancárias usam Pix, débito, dinheiro, etc.</p>
                                    </div>

                                    <div>
                                        <x-input-label for="color" value="Cor de identificação" />
                                        <input type="color" id="color" name="color" value="{{ old('_form') === 'account-store' ? old('color', '#4f46e5') : '#4f46e5' }}" class="form-control form-control-color w-100 mt-1">
                                        <x-input-error :messages="$errors->get('color')" class="mt-2" />
                                    </div>

                                    <div id="pm-block-store" class="rounded border bg-body-tertiary p-3" data-account-pm-block>
                                        <div class="js-payment-methods-editor">
                                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                                                <div>
                                                    <span class="fw-semibold d-block">Formas de pagamento</span>
                                                    <span class="small text-secondary">Só para contas: como você costuma pagar por essa conta.</span>
                                                </div>
                                                <div class="d-flex gap-1 flex-shrink-0">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary js-pm-check-all">Marcar todas</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary js-pm-check-none">Limpar</button>
                                                </div>
                                            </div>
                                            @include('accounts.partials.payment-method-checkboxes', [
                                                'paymentMethodOptions' => $paymentMethodOptions,
                                                'selected' => old('_form') === 'account-store' ? (array) (old('payment_methods') ?? []) : PaymentMethods::forRegularAccounts(),
                                                'prefix' => 'new',
                                            ])
                                        </div>
                                        <x-input-error :messages="$errors->get('payment_methods')" class="mt-2" />
                                    </div>

                                    <x-primary-button class="w-100 justify-content-center">
                                        Cadastrar conta
                                    </x-primary-button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <h3 class="h6 mb-3">Suas contas</h3>
                        <div class="vstack gap-3">
                            @forelse($accounts as $account)
                                @php
                                    $editOpen = $errors->any() && old('_form') === 'account-update-'.$account->id;
                                    $selectedForEdit = $account->isCreditCard()
                                        ? []
                                        : ($account->allowed_payment_methods !== null
                                            ? $account->allowed_payment_methods
                                            : PaymentMethods::forRegularAccounts());
                                    if (old('_form') === 'account-update-'.$account->id) {
                                        $selectedForEdit = (array) old('payment_methods', []);
                                    }
                                    $typeLabel = match ($account->kind) {
                                        Account::KIND_CREDIT_CARD => 'Cartão de crédito',
                                        default => 'Conta',
                                    };
                                @endphp
                                <div class="card border shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                                            <div class="d-flex align-items-center gap-3 min-w-0">
                                                <div class="rounded d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 2.5rem; height: 2.5rem; background-color: {{ $account->color }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <h4 class="h6 mb-1">{{ $account->name }}</h4>
                                                    <div class="small text-secondary mb-1">{{ $typeLabel }}</div>
                                                    <div class="d-flex flex-wrap gap-1 mb-1">
                                                        @if($account->isCreditCard())
                                                            <span class="badge rounded-pill bg-body-secondary text-body border">Cartão de crédito</span>
                                                        @else
                                                            @foreach ($account->getEffectivePaymentMethods() as $pm)
                                                                <span class="badge rounded-pill bg-body-secondary text-body border">{{ $pm }}</span>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                    <p class="small text-secondary mb-0">Cadastrada em {{ $account->created_at->format('d/m/Y') }}</p>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary"
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
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir conta">
                                                        Excluir
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="collapse {{ $editOpen ? 'show' : '' }} mt-3" id="edit-account-{{ $account->id }}">
                                            <div class="border-top pt-3">
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

                                                    <div id="pm-block-edit-{{ $account->id }}" class="rounded border bg-body-tertiary p-3" data-account-pm-block data-fixed-kind="{{ $account->kind }}">
                                                        <div class="js-payment-methods-editor">
                                                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                                                                <div>
                                                                    <span class="fw-semibold d-block">Formas de pagamento</span>
                                                                    <span class="small text-secondary">Só para contas.</span>
                                                                </div>
                                                                <div class="d-flex gap-1 flex-shrink-0">
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary js-pm-check-all">Marcar todas</button>
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary js-pm-check-none">Limpar</button>
                                                                </div>
                                                            </div>
                                                            @include('accounts.partials.payment-method-checkboxes', [
                                                                'paymentMethodOptions' => $paymentMethodOptions,
                                                                'selected' => $selectedForEdit,
                                                                'prefix' => 'edit-'.$account->id,
                                                            ])
                                                        </div>
                                                        <x-input-error :messages="$errors->get('payment_methods')" class="mt-2" />
                                                    </div>

                                                    <div class="d-flex flex-wrap gap-2">
                                                        <x-primary-button type="submit">Salvar alterações</x-primary-button>
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#edit-account-{{ $account->id }}">Fechar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5 border border-2 border-dashed rounded bg-body-secondary">
                                    <p class="small text-secondary text-uppercase fw-bold mb-0">Nenhuma conta encontrada</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
