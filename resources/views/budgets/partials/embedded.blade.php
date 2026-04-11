{{-- Resumo global do orçamento (mês corrente). Renda: cabeçalho Receitas (`income-toolbar`). Requer: $budgets, $categoriesExpense --}}
@php
    $budgetCategoryCount = $categoriesExpense->filter(fn ($c) => ! $c->isCreditCardInvoicePayment())->count();
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
    <div class="budget-summary-head px-4 py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h3 class="h5 mb-0 fw-semibold">Resumo do mês</h3>
                <p class="small text-secondary mb-0">Soma das metas face à renda informada.</p>
            </div>
            <span class="badge rounded-pill bg-body-secondary text-body border px-3 py-2 fw-semibold">
                {{ $budgetCategoryCount }} {{ $budgetCategoryCount === 1 ? 'categoria' : 'categorias' }}
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

@if($budgetPercent > 100)
    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-3 mt-3 mb-0" role="alert">
        <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01" /></svg>
        </span>
        <span class="small pt-1">O total planejado ultrapassa a renda mensal informada. Ajuste as metas nos cartões ou a renda em Receitas.</span>
    </div>
@endif
