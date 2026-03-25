<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <h2 class="h5 mb-0">
                {{ __('Planejamento Mensal') }}
            </h2>

            <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded border shadow-sm">
                <span class="small fw-bold text-secondary text-uppercase">Renda:</span>
                <div id="budget-income-display" class="d-flex align-items-center gap-2">
                    <span class="fw-bold">R$ {{ number_format(Auth::user()->couple->monthly_income, 2, ',', '.') }}</span>
                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary" id="btn-income-edit" title="Editar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                    </button>
                </div>
                <div id="budget-income-editor" class="d-none">
                    <form action="{{ route('budgets.income') }}" method="POST" class="d-flex align-items-center gap-2 flex-wrap">
                        @csrf
                        <input type="number" name="monthly_income" class="form-control form-control-sm" style="width: 7rem;" step="0.01" value="{{ Auth::user()->couple->monthly_income }}" required />
                        <button type="submit" class="btn btn-success btn-sm">OK</button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-income-cancel">✕</button>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success mb-4">
                    {{ session('success') }}
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

            <div class="card shadow-sm border mb-4">
                <div class="card-body p-4">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-end mb-2">
                                <span class="small fw-bold text-secondary text-uppercase">Planejamento Total</span>
                                <span class="fw-bold {{ $budgetPercent > 100 ? 'text-danger' : 'text-primary' }}">
                                    R$ {{ number_format($totalBudgeted, 2, ',', '.') }}
                                    <span class="small text-secondary fw-normal ms-1">de R$ {{ number_format($income, 2, ',', '.') }}</span>
                                </span>
                            </div>
                            <div class="progress" style="height: 12px;">
                                <div
                                    class="progress-bar {{ $budgetPercent > 100 ? 'bg-danger' : 'bg-primary' }}"
                                    role="progressbar"
                                    style="width: {{ $progressWidth }}%"
                                    aria-valuenow="{{ $progressWidth }}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                ></div>
                            </div>
                        </div>
                        <div class="col-md-4 border-start-md ps-md-4">
                            <div class="row text-center g-2">
                                <div class="col-6">
                                    <p class="small fw-bold text-secondary text-uppercase mb-1">Comprometido</p>
                                    <p class="fw-bold mb-0 {{ $budgetPercent > 100 ? 'text-danger' : 'text-body' }}">{{ number_format($budgetPercent, 1, ',', '.') }}%</p>
                                </div>
                                <div class="col-6">
                                    <p class="small fw-bold text-secondary text-uppercase mb-1">Disponível</p>
                                    <p class="fw-bold text-success mb-0">R$ {{ number_format(max(0, $income - $totalBudgeted), 2, ',', '.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 order-1 order-lg-2">
                    <div class="sticky top-0" style="top: 1rem;">
                        <div class="card shadow-sm border">
                            <div class="card-header bg-body-secondary py-3">
                                <h3 class="h6 mb-0">Definir Meta</h3>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('budgets.store') }}" method="POST" class="vstack gap-3">
                                    @csrf
                                    <div>
                                        <label for="category_id" class="form-label small fw-bold text-secondary text-uppercase">Categoria</label>
                                        <select id="category_id" name="category_id" class="form-select">
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="amount" class="form-label small fw-bold text-secondary text-uppercase">Valor Mensal (R$)</label>
                                        <input type="number" id="amount" name="amount" step="0.01" required class="form-control" placeholder="0,00">
                                        <p class="form-text small mb-0">* Se já existir uma meta para esta categoria, ela será atualizada.</p>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Salvar Planejamento</button>
                                </form>
                            </div>
                        </div>

                        @if($budgetPercent > 100)
                            <div class="alert alert-danger d-flex align-items-start gap-2 mt-3 small">
                                <span>Atenção: Seu planejamento total ultrapassou sua renda mensal!</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-8 order-2 order-lg-1">
                    <div class="row g-4">
                        @foreach ($categories as $category)
                            @php
                                $budget = $budgets->where('category_id', $category->id)->first();
                                $spent = Auth::user()->couple->transactions()
                                    ->where('category_id', $category->id)
                                    ->whereMonth('date', date('m'))
                                    ->whereYear('date', date('Y'))
                                    ->sum('amount');
                                $usagePercent = $budget && $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;
                                $budgetVsIncome = $budget && $income > 0 ? ($budget->amount / $income) * 100 : 0;
                            @endphp
                            <div class="col-md-6">
                                <div class="card h-100 border shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 3rem; height: 3rem; background-color: {{ $category->color }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                </div>
                                                <div>
                                                    <h4 class="h6 mb-0">{{ $category->name }}</h4>
                                                    <p class="small text-secondary mb-0">
                                                        {{ $budget ? number_format($budgetVsIncome, 1, ',', '.') . '% da renda' : 'Sem orçamento' }}
                                                    </p>
                                                </div>
                                            </div>
                                            @if($budget)
                                                <div class="text-end">
                                                    <p class="small text-secondary mb-0 text-uppercase">Restante</p>
                                                    <p class="fw-bold mb-0 {{ $spent > $budget->amount ? 'text-danger' : 'text-success' }}">
                                                        R$ {{ number_format(max(0, $budget->amount - $spent), 2, ',', '.') }}
                                                    </p>
                                                </div>
                                            @endif
                                        </div>

                                        @if($budget)
                                            <div class="small">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-secondary text-uppercase">Gasto</span>
                                                    <span class="text-secondary text-uppercase">Meta</span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="fw-bold">R$ {{ number_format($spent, 2, ',', '.') }}</span>
                                                    <span class="fw-bold text-secondary">R$ {{ number_format($budget->amount, 2, ',', '.') }}</span>
                                                </div>
                                                @php
                                                    $barWidth = number_format(max(0, min(100, $usagePercent)), 2, '.', '');
                                                    $barColor = $usagePercent > 100 ? 'bg-danger' : ($usagePercent > 80 ? 'bg-warning' : 'bg-success');
                                                @endphp
                                                <div class="progress" style="height: 10px;">
                                                    <div class="progress-bar {{ $barColor }}" style="width: {{ $barWidth }}%"></div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-1 small text-uppercase fw-bold">
                                                    <span class="{{ $usagePercent > 100 ? 'text-danger' : 'text-secondary' }}">{{ number_format($usagePercent, 1, ',', '.') }}% Consumido</span>
                                                    @if($usagePercent > 100)
                                                        <span class="text-danger">Estourou</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div class="text-center py-4 bg-body-secondary rounded border border-dashed">
                                                <p class="small text-secondary text-uppercase fw-bold mb-0">Defina um limite</p>
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
