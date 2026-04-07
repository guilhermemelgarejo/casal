<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">
            Lançamentos
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
                @if (session('error'))
                    <div class="alert alert-danger mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="row g-4">
                    {{-- Lista principal: maior peso visual --}}
                    <div class="col-lg-8">
                        <div class="card border shadow-sm h-100">
                            <div class="card-header bg-white py-3 border-bottom">
                                <div class="vstack gap-0">
                                    <div>
                                        <h3 class="h5 mb-0">Lançamentos</h3>
                                        <p class="small text-secondary mb-0 mt-1">Histórico e movimentações do período</p>
                                    </div>

                                    <div class="border-top pt-3 mt-3 min-w-0">
                                    <form method="GET" action="{{ route('transactions.index') }}" class="d-flex align-items-stretch flex-nowrap gap-2 w-100 min-w-0">
                                        <div class="input-group input-group-sm min-w-0" style="flex: 0 1 6.75rem;">
                                            <span class="input-group-text text-secondary px-2">Mês</span>
                                            <select
                                                id="filter-month"
                                                name="month"
                                                class="form-select min-w-0 px-2"
                                                aria-label="Mês"
                                            >
                                                @for($m = 1; $m <= 12; $m++)
                                                    <option value="{{ $m }}" {{ (int) $selectedMonth === $m ? 'selected' : '' }}>
                                                        {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                                    </option>
                                                @endfor
                                            </select>
                                        </div>

                                        <div class="input-group input-group-sm min-w-0" style="flex: 0 1 7.25rem;">
                                            <span class="input-group-text text-secondary px-2">Ano</span>
                                            <select
                                                id="filter-year"
                                                name="year"
                                                class="form-select min-w-0 px-2"
                                                aria-label="Ano"
                                            >
                                                @foreach($years as $y)
                                                    <option value="{{ $y }}" {{ (int) $selectedYear === (int) $y ? 'selected' : '' }}>
                                                        {{ $y }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="input-group input-group-sm min-w-0" style="flex: 1 1 5rem;">
                                            <span class="input-group-text text-secondary px-2">Conta</span>
                                            <select
                                                id="filter-account"
                                                name="account_id"
                                                class="form-select min-w-0 px-2"
                                                aria-label="Filtrar por conta"
                                            >
                                                <option value="" {{ $filterAccountId === null ? 'selected' : '' }}>Todas</option>
                                                @foreach($accountsSortedForFilter as $acc)
                                                    <option value="{{ $acc->id }}" {{ (int) $filterAccountId === (int) $acc->id ? 'selected' : '' }}>
                                                        @if($acc->isCreditCard())
                                                            {{ $acc->name }} (cartão)
                                                        @else
                                                            {{ $acc->name }}
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="btn-group btn-group-sm flex-shrink-0 align-self-center" role="group" aria-label="Ações do filtro">
                                            <button type="submit" class="btn btn-primary">Filtrar</button>
                                            <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Atual</a>
                                        </div>
                                    </form>
                                    @if ($filteredRegularAccountBalance !== null)
                                        <p class="small text-secondary mb-0 mt-2">
                                            Saldo atual desta conta (todos os lançamentos):
                                            <span class="fw-semibold {{ $filteredRegularAccountBalance >= 0 ? 'text-body' : 'text-danger' }}">R$ {{ number_format($filteredRegularAccountBalance, 2, ',', '.') }}</span>
                                        </p>
                                    @endif
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Data</th>
                                                <th>Descrição</th>
                                                <th>Categoria</th>
                                                <th>Pagamento/Conta</th>
                                                <th class="text-end">Valor</th>
                                                <th class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($transactions as $transaction)
                                                @php
                                                    $delMeta = $transactionDeleteMeta[$transaction->id] ?? [
                                                        'paidInvoice' => false,
                                                        'peerCount' => 1,
                                                        'singleAllowed' => true,
                                                    ];
                                                    $blockedMsg = 'Este lançamento faz parte de um ciclo de fatura de cartão já marcado como pago. Desmarque o pagamento em Faturas de cartão se precisar alterar os lançamentos desse período.';
                                                @endphp
                                                <tr>
                                                    <td class="text-secondary small text-nowrap">
                                                        <div>{{ $transaction->date->format('d/m/Y') }}</div>
                                                        @php
                                                            $refMonth = (int) ($transaction->reference_month ?? $transaction->date->month);
                                                            $refYear = (int) ($transaction->reference_year ?? $transaction->date->year);
                                                            $refLabel = str_pad((string) $refMonth, 2, '0', STR_PAD_LEFT) . '/' . $refYear;
                                                            $dateMonthYear = $transaction->date->format('m/Y');
                                                        @endphp
                                                        @if($refLabel !== $dateMonthYear)
                                                            <div class="text-muted">Ref: {{ $refLabel }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="fw-medium">{{ $transaction->description }}</div>
                                                        <div class="small text-muted">{{ $transaction->user->name }}</div>
                                                    </td>
                                                    <td>
                                                        <span class="badge rounded-pill text-white" style="background-color: {{ $transaction->category->color ?? '#ccc' }}">
                                                            {{ $transaction->category->name }}
                                                        </span>
                                                    </td>
                                                    <td class="small">
                                                        @php $accRow = $transaction->accountModel; @endphp
                                                        @if($accRow?->isCreditCard())
                                                            <div class="fw-medium">Cartão de crédito</div>
                                                            <div class="text-muted">{{ $accRow->name }}</div>
                                                        @elseif($transaction->payment_method || $accRow)
                                                            <div class="fw-medium">{{ $transaction->payment_method ?: '—' }}</div>
                                                            <div class="text-muted">{{ $accRow?->name ?? '—' }}</div>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end fw-bold text-nowrap {{ $transaction->type === 'income' ? 'text-success' : 'text-danger' }}">
                                                        {{ $transaction->type === 'income' ? '+' : '-' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                                                    </td>
                                                    <td class="text-center">
                                                        @if ($delMeta['paidInvoice'])
                                                            <button
                                                                type="button"
                                                                class="btn btn-link text-secondary btn-sm p-0 js-tx-delete-blocked"
                                                                title="Exclusão bloqueada: fatura deste período já paga"
                                                                aria-label="Exclusão bloqueada: lançamento em ciclo de fatura de cartão já pago"
                                                                data-tx-blocked-msg="{{ $blockedMsg }}"
                                                            >
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                                            </button>
                                                        @else
                                                            <form
                                                                action="{{ route('transactions.destroy', $transaction) }}"
                                                                method="POST"
                                                                class="d-inline js-tx-delete-form"
                                                                data-tx-delete-meta="{!! htmlspecialchars(json_encode($delMeta, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') !!}"
                                                            >
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="hidden" name="installment_scope" value="single" class="js-tx-installment-scope">
                                                                <button type="submit" class="btn btn-link text-danger btn-sm p-0" title="Excluir">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-secondary py-5">Nenhum lançamento encontrado.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="px-3 py-3">
                                    {{ $transactions->links() }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Resumo e formulário: mesma linguagem visual da listagem --}}
                    <div class="col-lg-4">
                        <div class="vstack gap-4">
                            <div class="card border shadow-sm">
                                <div class="card-header bg-white py-3 border-bottom">
                                    <h3 class="h5 mb-0">Resumo</h3>
                                    <p class="small text-secondary mb-0 mt-1">
                                        Totais de {{ $selectedMonthYearLabel }}
                                        @if($filterAccountId !== null)
                                            @php $fAcc = $accounts->firstWhere('id', $filterAccountId); @endphp
                                            @if($fAcc)
                                                — só {{ $fAcc->name }}{{ $fAcc->isCreditCard() ? ' (cartão)' : '' }}
                                            @endif
                                        @endif
                                    </p>
                                </div>
                                <div class="card-body p-3">
                                    <div class="rounded bg-body-secondary bg-opacity-50 p-2">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-secondary">Receitas</span>
                                            <span class="text-success fw-semibold">R$ {{ number_format($monthTransactionsAll->where('type', 'income')->sum('amount'), 2, ',', '.') }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small mb-2">
                                            <span class="text-secondary">Despesas</span>
                                            <span class="text-danger fw-semibold">R$ {{ number_format($monthTransactionsAll->where('type', 'expense')->sum('amount'), 2, ',', '.') }}</span>
                                        </div>
                                        <hr class="my-2">
                                        @php $balance = $monthTransactionsAll->where('type', 'income')->sum('amount') - $monthTransactionsAll->where('type', 'expense')->sum('amount'); @endphp
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <span class="small fw-semibold">Saldo</span>
                                            <span class="fw-bold {{ $balance >= 0 ? 'text-success' : 'text-danger' }}">
                                                R$ {{ number_format($balance, 2, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>

                                    @if($byPaymentMethod->count() > 0)
                                        <h4 class="small text-secondary text-uppercase mb-1 mt-3">Por pagamento</h4>
                                        <div class="table-responsive border rounded">
                                            <table class="table table-sm table-borderless mb-0 small">
                                                <tbody>
                                                    @foreach($byPaymentMethod as $method => $total)
                                                        <tr>
                                                            <td class="py-1">{{ $method }}</td>
                                                            <td class="text-end text-danger py-1 text-nowrap">R$ {{ number_format($total, 2, ',', '.') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    @if($byAccount->count() > 0)
                                        <h4 class="small text-secondary text-uppercase mb-1 mt-2">Por conta</h4>
                                        <div class="table-responsive border rounded">
                                            <table class="table table-sm table-borderless mb-0 small">
                                                <tbody>
                                                    @foreach($byAccount as $account => $total)
                                                        <tr>
                                                            <td class="py-1">{{ $account }}</td>
                                                            <td class="text-end text-danger py-1 text-nowrap">R$ {{ number_format($total, 2, ',', '.') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="card border shadow-sm">
                                <div class="card-header bg-white py-3 border-bottom">
                                    <h3 class="h5 mb-0">Novo lançamento</h3>
                                    <p class="small text-secondary mb-0 mt-1">Comece pela forma de pagamento; em seguida escolha o cartão ou a conta compatível.</p>
                                </div>
                                <div class="card-body p-3">
                                    @if($accounts->isEmpty())
                                        <div class="alert alert-warning mb-0">
                                            Cadastre ao menos uma conta em
                                            <a href="{{ route('accounts.index') }}" class="alert-link">Gerenciar contas</a>
                                            para poder lançar movimentações.
                                        </div>
                                    @else
                                    @php
                                        $canPayWithAccount = $regularAccounts->isNotEmpty();
                                        $canPayWithCard = $cardAccounts->isNotEmpty();
                                    @endphp
                                    <form
                                        id="form-new-transaction"
                                        action="{{ route('transactions.store') }}"
                                        method="POST"
                                        data-tx-form-mode="{{ $txFormMode }}"
                                        data-tx-accounts='@json($txAccountsPayload)'
                                        data-tx-old-account-id="{{ old('account_id', '') }}"
                                        data-tx-default-ref-month="{{ $refDefaultMonth }}"
                                        data-tx-default-ref-year="{{ $refDefaultYear }}"
                                    >
                                        @csrf

                                        @if (session('credit_limit_overflow'))
                                            @php $clOverflow = session('credit_limit_overflow'); @endphp
                                            <div class="alert alert-warning mb-0" role="alert">
                                                <p class="mb-2 fw-semibold">Limite do cartão</p>
                                                <p class="small mb-2">Com este lançamento, o uso ultrapassaria o limite total configurado. O limite disponível materializado ficaria negativo.</p>
                                                <ul class="small mb-2 ps-3">
                                                    <li>Limite total: R$ {{ number_format((float) $clOverflow['limit_total'], 2, ',', '.') }}</li>
                                                    <li>Em aberto nas faturas (restante a pagar): R$ {{ number_format((float) $clOverflow['outstanding_before'], 2, ',', '.') }}</li>
                                                    <li>Valor deste lançamento: R$ {{ number_format((float) $clOverflow['purchase_total'], 2, ',', '.') }}</li>
                                                    <li>Limite disponível passaria a: <strong class="text-danger">R$ {{ number_format((float) $clOverflow['projected_available'], 2, ',', '.') }}</strong></li>
                                                </ul>
                                                <p class="small mb-0 text-secondary">Se os dados estão corretos, clique outra vez em «Salvar Lançamento» para confirmar.</p>
                                            </div>
                                            <input type="hidden" name="credit_limit_confirm_token" value="{{ $clOverflow['token'] }}">
                                        @endif

                                        <input type="hidden" name="funding" id="tx-funding" value="@if($txFormMode === 'cards_only')credit_card@elseif($txFormMode === 'regular_only')account@else{{ old('funding', '') }}@endif">

                                        @if($txFormMode !== 'cards_only')
                                            <input type="hidden" name="payment_method" id="tx-payment-method" value="{{ old('payment_method', '') }}" @if($txFormMode === 'both' && old('funding') === 'credit_card') disabled @endif>
                                        @endif

                                        <div class="vstack gap-3">
                                            @if($txFormMode === 'cards_only')
                                                <div>
                                                    <x-input-label for="tx-account-id" value="Cartão de crédito" />
                                                    <select name="account_id" id="tx-account-id" class="form-select mt-1" required>
                                                        <option value="" disabled {{ old('account_id') ? '' : 'selected' }}>Selecione o cartão…</option>
                                                        @foreach($cardAccounts as $account)
                                                            <option value="{{ $account->id }}" {{ (string) old('account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                                                </div>
                                            @else
                                                <div>
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
                                                    <p class="form-text mb-0" id="payment-flow-hint">Depois aparecem só cartões ou contas compatíveis com essa forma.</p>
                                                    <x-input-error :messages="$errors->get('funding')" class="mt-2" />
                                                    <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                                                </div>

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
                                                    <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                                                </div>
                                            @endif

                                            <p class="form-text mb-0">
                                                <a href="{{ route('accounts.index') }}">Gerenciar contas e cartões</a>
                                            </p>

                                            <div>
                                                <x-input-label for="transaction-type" value="Tipo de Lançamento" />
                                                <select id="transaction-type" name="type" class="form-select mt-1">
                                                    <option value="expense" {{ old('type', 'expense') === 'expense' ? 'selected' : '' }}>Despesa (Saída)</option>
                                                    <option value="income" {{ old('type') === 'income' ? 'selected' : '' }}>Receita (Entrada)</option>
                                                </select>
                                                <x-input-error :messages="$errors->get('type')" class="mt-2" />
                                            </div>

                                            <div>
                                                <x-input-label for="category_id" value="Categoria" />
                                                <select id="category_id" name="category_id" class="form-select mt-1">
                                                    @foreach($categories as $c)
                                                        <option
                                                            value="{{ $c->id }}"
                                                            data-type="{{ $c->type }}"
                                                            {{ (string) old('category_id') === (string) $c->id ? 'selected' : '' }}
                                                        >
                                                            {{ $c->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                                            </div>

                                            <div>
                                                <x-input-label for="description" value="Descrição" />
                                                <x-text-input id="description" name="description" type="text" class="mt-1" required value="{{ old('description') }}" placeholder="Ex: Compras do mês" />
                                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                            </div>

                                            <div>
                                                <x-input-label for="amount" value="Valor (R$)" />
                                                <x-text-input id="amount" name="amount" type="number" step="0.01" class="mt-1" required value="{{ old('amount') }}" placeholder="0,00" />
                                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                            </div>

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
                                            @endphp
                                            <div id="installments-wrapper" class="{{ $isCreditRender ? '' : 'd-none' }}">
                                                <x-input-label for="installments" value="Parcelas (crédito)" />
                                                <select
                                                    id="installments"
                                                    name="installments"
                                                    class="form-select mt-1"
                                                    {{ $isCreditRender ? 'required' : '' }}
                                                >
                                                    @for($i = 1; $i <= 12; $i++)
                                                        <option value="{{ $i }}" {{ $installmentsOld === $i ? 'selected' : '' }}>{{ $i }}</option>
                                                    @endfor
                                                </select>
                                                <x-input-error :messages="$errors->get('installments')" class="mt-2" />
                                                <p class="form-text mb-0">Geramos 1 lançamento por mês de referência.</p>
                                            </div>

                                            <div id="reference-wrapper" class="{{ $isCreditRender ? '' : 'd-none' }}">
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
                                                <p class="form-text mb-0">Por padrão usamos o mês seguinte à data de hoje no fuso da aplicação ({{ config('app.timezone') }}). Ajuste se precisar.</p>
                                            </div>

                                            <div>
                                                <x-input-label for="date" value="Data" />
                                                <x-text-input id="date" name="date" type="date" class="mt-1" value="{{ old('date', date('Y-m-d')) }}" required />
                                                <x-input-error :messages="$errors->get('date')" class="mt-2" />
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <x-primary-button class="w-100 justify-content-center">Salvar Lançamento</x-primary-button>
                                        </div>
                                    </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
