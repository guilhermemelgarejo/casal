<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
            <div>
                <h2 class="h5 mb-0 budget-page-title">Planejamento mensal</h2>
                <p class="small text-secondary mb-0 mt-1">Metas por categoria no <strong class="fw-medium text-body">mês corrente</strong>; gasto real vem dos lançamentos (sem pagamentos de fatura de cartão).</p>
            </div>

            <div class="budget-income-toolbar ms-lg-auto">
                <span class="small fw-semibold text-secondary text-uppercase flex-shrink-0" style="font-size: 0.65rem; letter-spacing: 0.05em;">Renda</span>
                <div id="budget-income-display" class="d-flex align-items-center gap-2 flex-shrink-0">
                    <span class="fw-bold text-nowrap">R$ {{ number_format(Auth::user()->couple->monthly_income, 2, ',', '.') }}</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-0" id="btn-income-edit" title="Editar renda" aria-label="Editar renda mensal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                    </button>
                </div>
                <div id="budget-income-editor" class="d-none w-100 flex-grow-1">
                    <form action="{{ route('budgets.income') }}" method="POST" class="d-flex align-items-center flex-wrap gap-2">
                        @csrf
                        <input type="number" name="monthly_income" class="form-control form-control-sm rounded-3" style="width: 7.5rem; min-width: 6rem;" step="0.01" value="{{ Auth::user()->couple->monthly_income }}" required aria-label="Valor da renda mensal" />
                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">Salvar</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="btn-income-cancel">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-4 budgets-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif

            @php
                $totalBudgeted = 0;
                foreach($budgets as $b) {
                    $totalBudgeted += (float) $b->amount;
                }
                $income = (float) (Auth::user()->couple->monthly_income ?? 0);
                $budgetPercent = $income > 0 ? ($totalBudgeted / $income) * 100 : 0;
                $progressWidth = number_format(max(0, min(100, $budgetPercent)), 2, '.', '');
            @endphp

            <div class="card border-0 shadow-sm mb-4 budget-summary-card">
                <div class="budget-summary-head px-4 py-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h3 class="h5 mb-0 fw-semibold">Resumo do mês</h3>
                            <p class="small text-secondary mb-0">Soma das metas face à renda informada.</p>
                        </div>
                        <span class="badge rounded-pill bg-body-secondary text-body border px-3 py-2 fw-semibold">
                            {{ $categories->count() }} {{ $categories->count() === 1 ? 'categoria' : 'categorias' }}
                        </span>
                    </div>
                    <div class="row g-3 g-lg-4 align-items-center">
                        <div class="col-lg-8">
                            <div class="d-flex justify-content-between align-items-end mb-2 flex-wrap gap-2">
                                <span class="small fw-semibold text-secondary text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.06em;">Planejamento total</span>
                                <span class="fw-bold {{ $budgetPercent > 100 ? 'text-danger' : 'text-primary' }}">
                                    R$ {{ number_format($totalBudgeted, 2, ',', '.') }}
                                    <span class="small text-secondary fw-normal ms-1">de R$ {{ number_format($income, 2, ',', '.') }}</span>
                                </span>
                            </div>
                            <div class="progress rounded-pill" style="height: 12px;">
                                <div
                                    class="progress-bar rounded-pill {{ $budgetPercent > 100 ? 'bg-danger' : 'bg-primary' }}"
                                    role="progressbar"
                                    style="width: {{ $progressWidth }}%"
                                    aria-valuenow="{{ $progressWidth }}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                ></div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="budget-summary-stat h-100 text-center">
                                        <p class="small fw-semibold text-secondary text-uppercase mb-1" style="font-size: 0.65rem; letter-spacing: 0.04em;">Comprometido</p>
                                        <p class="fw-bold mb-0 {{ $budgetPercent > 100 ? 'text-danger' : 'text-body' }}">{{ number_format($budgetPercent, 1, ',', '.') }}%</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="budget-summary-stat h-100 text-center">
                                        <p class="small fw-semibold text-secondary text-uppercase mb-1" style="font-size: 0.65rem; letter-spacing: 0.04em;">Disponível</p>
                                        <p class="fw-bold text-success mb-0">R$ {{ number_format(max(0, $income - $totalBudgeted), 2, ',', '.') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-4 order-1 order-lg-2">
                    <div class="budget-form-sticky">
                        <div class="card border-0 shadow-sm budget-meta-card">
                            <div class="budget-meta-head px-4 py-3">
                                <h3 class="h5 mb-0 fw-semibold">Definir meta</h3>
                                <p class="small text-secondary mb-0 mt-1">Escolha a categoria e o valor mensal. Se já existir meta, ela é atualizada.</p>
                            </div>
                            <div class="card-body p-4">
                                <form action="{{ route('budgets.store') }}" method="POST" class="vstack gap-3">
                                    @csrf
                                    <div>
                                        <label for="category_id" class="form-label">Categoria</label>
                                        <select id="category_id" name="category_id" class="form-select mt-1">
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="amount" class="form-label">Valor mensal (R$)</label>
                                        <input type="number" id="amount" name="amount" step="0.01" required class="form-control mt-1" placeholder="0,00">
                                        <p class="form-text small mb-0">Categorias de quitação de fatura não entram no orçamento.</p>
                                    </div>
                                    <x-primary-button class="w-100 justify-content-center rounded-pill py-2">
                                        Salvar planejamento
                                    </x-primary-button>
                                </form>
                            </div>
                        </div>

                        @if($budgetPercent > 100)
                            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-3 mt-3 mb-0" role="alert">
                                <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01" /></svg>
                                </span>
                                <span class="small pt-1">O total planejado ultrapassa a renda mensal informada. Ajuste as metas ou a renda.</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-8 order-2 order-lg-1">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h3 class="h5 mb-0 fw-semibold">Por categoria</h3>
                        <span class="small text-secondary">Gasto real vs meta</span>
                    </div>
                    <div class="row g-3">
                        @foreach ($categories as $category)
                            @php
                                $budget = $budgets->where('category_id', $category->id)->first();
                                $spent = (float) ($spentByCategory[$category->id] ?? 0);
                                $usagePercent = $budget && $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;
                                $budgetVsIncome = $budget && $income > 0 ? ($budget->amount / $income) * 100 : 0;
                            @endphp
                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm budget-cat-card" style="--budget-cat-accent: {{ $category->color }}">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
                                            <div class="d-flex align-items-center gap-3 min-w-0">
                                                <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0 shadow-sm" style="width: 3rem; height: 3rem; background-color: {{ $category->color }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <h4 class="h6 mb-0 text-truncate" title="{{ $category->name }}">{{ $category->name }}</h4>
                                                    <p class="small text-secondary mb-0">
                                                        {{ $budget ? number_format($budgetVsIncome, 1, ',', '.') . '% da renda' : 'Sem meta definida' }}
                                                    </p>
                                                </div>
                                            </div>
                                            @if($budget)
                                                <div class="text-end flex-shrink-0">
                                                    <p class="small text-secondary mb-0 text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.04em;">Restante</p>
                                                    <p class="fw-bold mb-0 {{ $spent > $budget->amount ? 'text-danger' : 'text-success' }}">
                                                        R$ {{ number_format(max(0, $budget->amount - $spent), 2, ',', '.') }}
                                                    </p>
                                                </div>
                                            @endif
                                        </div>

                                        @if($budget)
                                            <div class="small">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.04em;">Gasto</span>
                                                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.04em;">Meta</span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="fw-bold">R$ {{ number_format($spent, 2, ',', '.') }}</span>
                                                    <span class="fw-bold text-secondary">R$ {{ number_format($budget->amount, 2, ',', '.') }}</span>
                                                </div>
                                                @php
                                                    $barWidth = number_format(max(0, min(100, $usagePercent)), 2, '.', '');
                                                    $barColor = $usagePercent > 100 ? 'bg-danger' : ($usagePercent > 80 ? 'bg-warning' : 'bg-success');
                                                @endphp
                                                <div class="progress rounded-pill" style="height: 10px;">
                                                    <div class="progress-bar rounded-pill {{ $barColor }}" style="width: {{ $barWidth }}%"></div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-2 small">
                                                    <span class="fw-semibold text-uppercase {{ $usagePercent > 100 ? 'text-danger' : 'text-secondary' }}" style="font-size: 0.65rem; letter-spacing: 0.04em;">{{ number_format($usagePercent, 1, ',', '.') }}% usado</span>
                                                    @if($usagePercent > 100)
                                                        <span class="text-danger fw-semibold small">Acima da meta</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div class="budget-cat-empty text-center py-4 px-3">
                                                <p class="small text-secondary fw-semibold mb-0">Defina um limite ao lado</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
