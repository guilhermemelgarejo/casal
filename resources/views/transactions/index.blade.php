<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 mb-0 tx-page-title">Lançamentos</h2>
            <p class="small text-secondary mb-0 mt-1">Registre receitas e despesas; no cartão, o período da lista segue a <strong class="fw-medium text-body">data da compra</strong> — parcelas do mesmo pagamento aparecem numa linha com o total.</p>
        </div>
    </x-slot>

    <div class="py-4 transactions-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <span class="pt-1">{{ session('error') }}</span>
                </div>
            @endif

            <div class="card border-0 shadow-sm overflow-hidden tx-index-card">
                <div class="tx-index-head px-4 py-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div class="min-w-0">
                            <h3 class="h5 mb-1 fw-semibold">Lista do período</h3>
                            <p class="small text-secondary mb-0">Demais lançamentos usam o <strong class="fw-medium text-body">mês de referência</strong> escolhido abaixo.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-end flex-shrink-0" id="onboarding-tx-actions" role="group" aria-label="Novo lançamento">
                            <button
                                type="button"
                                class="btn btn-outline-success rounded-pill px-3"
                                data-bs-toggle="modal"
                                data-bs-target="#modalNewTransaction"
                                data-tx-open-preset="income"
                            >
                                + Receita
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-danger rounded-pill px-3"
                                data-bs-toggle="modal"
                                data-bs-target="#modalNewTransaction"
                                data-tx-open-preset="expense"
                            >
                                + Despesa
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tx-index-filters px-4 py-3">
                    <form method="GET" action="{{ route('transactions.index') }}" class="row g-3 align-items-end">
                        <div class="col-6 col-md-4 col-lg-2 min-w-0 tx-filter-field">
                            <label class="form-label" for="filter-month">Mês</label>
                            <select
                                id="filter-month"
                                name="month"
                                class="form-select form-select-sm"
                                aria-label="Mês"
                            >
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ (int) $selectedMonth === $m ? 'selected' : '' }}>
                                        {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                    </option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-6 col-md-4 col-lg-2 min-w-0 tx-filter-field">
                            <label class="form-label" for="filter-year">Ano</label>
                            <select
                                id="filter-year"
                                name="year"
                                class="form-select form-select-sm"
                                aria-label="Ano"
                            >
                                @foreach($years as $y)
                                    <option value="{{ $y }}" {{ (int) $selectedYear === (int) $y ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-4 col-lg min-w-0 tx-filter-field">
                            <label class="form-label" for="filter-account">Conta</label>
                            <select
                                id="filter-account"
                                name="account_id"
                                class="form-select form-select-sm"
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

                        <div class="col-12 col-lg-auto">
                            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Ações do filtro">
                                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 flex-grow-1 flex-md-grow-0">Filtrar</button>
                                <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 flex-grow-1 flex-md-grow-0">Mês atual</a>
                            </div>
                        </div>
                    </form>
                    @if ($filteredRegularAccountBalance !== null)
                        <div class="mt-3 mb-0">
                            <span class="tx-balance-pill text-secondary">
                                <span class="small text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.04em;">Saldo da conta</span>
                                <span class="fw-semibold {{ $filteredRegularAccountBalance >= 0 ? 'text-body' : 'text-danger' }}">R$ {{ number_format($filteredRegularAccountBalance, 2, ',', '.') }}</span>
                                <span class="small d-none d-md-inline">(todos os lançamentos)</span>
                            </span>
                        </div>
                    @endif
                </div>

                <div class="list-group list-group-flush" role="list">
                    @include('transactions.partials.transaction-list-rows')
                </div>
                <div class="tx-pagination-wrap px-3 py-3">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
    </div>

    @include('transactions.partials.transaction-modals')


</x-app-layout>
