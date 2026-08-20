{{-- Resumo global do orçamento (mês corrente). Renda: cabeçalho Receitas (`income-toolbar`). Requer: $budgets, $categoriesExpense --}}
@php
    $budgetCategoryCount = $categoriesExpense->filter(fn ($c) => ! $c->isReservedSystemCategory())->count();
@endphp

@php
    $totalBudgeted = 0;
    foreach ($budgets as $b) {
        $totalBudgeted += (float) $b->amount;
    }
    $income = (float) (Auth::user()->couple->monthly_income ?? 0);
    $budgetPercent = $income > 0 ? ($totalBudgeted / $income) * 100 : 0;
    $progressWidth = number_format(max(0, min(100, $budgetPercent)), 2, '.', '');
@endphp

<div class="card border-0 shadow-sm mb-0 budget-summary-card">
    <div class="budget-summary-head p-3 p-md-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <span class="budget-summary-icon d-none d-sm-inline-flex" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="h5 mb-0 fw-semibold">Resumo do mês</h3>
                    <p class="small text-secondary mb-0">Soma das metas face à renda informada.</p>
                </div>
            </div>
            <span class="badge rounded-pill bg-body-secondary text-body border px-3 py-2 fw-semibold">
                {{ $budgetCategoryCount }} {{ $budgetCategoryCount === 1 ? 'categoria planejável' : 'categorias planejáveis' }}
            </span>
        </div>

        <div class="row g-3 align-items-stretch">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="budget-summary-stat budget-summary-stat--main h-100 d-flex flex-column justify-content-between p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <span class="budget-summary-eyebrow">Planejamento total</span>
                        <span class="fw-bold {{ $budgetPercent > 100 ? 'text-danger' : 'text-primary' }}">
                            R$ {{ number_format($totalBudgeted, 2, ',', '.') }}
                        </span>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center small text-secondary mb-1">
                            <span>Progresso</span>
                            <span>de R$ {{ number_format($income, 2, ',', '.') }}</span>
                        </div>
                        <div class="progress rounded-pill" style="height: 10px;">
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
                </div>
            </div>

            <div class="col-6 col-md-3 col-lg-3.5 flex-grow-1">
                <div class="budget-summary-stat h-100 d-flex flex-column justify-content-center text-center p-3">
                    <span class="budget-summary-eyebrow mb-1">Comprometido</span>
                    <strong class="fs-4 fw-bold {{ $budgetPercent > 100 ? 'text-danger' : 'text-body' }}">{{ number_format($budgetPercent, 1, ',', '.') }}%</strong>
                    <span class="small text-secondary mt-1">da renda total</span>
                </div>
            </div>

            <div class="col-6 col-md-3 col-lg-3.5 flex-grow-1">
                <div class="budget-summary-stat h-100 d-flex flex-column justify-content-center text-center p-3">
                    <span class="budget-summary-eyebrow mb-1">Disponível</span>
                    <strong class="fs-4 fw-bold text-success">R$ {{ number_format(max(0, $income - $totalBudgeted), 2, ',', '.') }}</strong>
                    <span class="small text-secondary mt-1">livre no plano</span>
                </div>
            </div>
        </div>
    </div>
</div>

@if($budgetPercent > 100)
    <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-start gap-3 mt-3 mb-0" role="alert">
        <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01" /></svg>
        </span>
        <span class="small pt-1">O total planejado ultrapassa a renda mensal informada. Ajuste as metas nos cartões ou a renda em Receitas.</span>
    </div>
@endif
