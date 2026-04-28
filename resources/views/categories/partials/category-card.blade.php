@php
    $isIncome = $category->type === 'income';
    $isFixed = $category->isReservedSystemCategory();
    $showBudgetMeta = ! $isIncome && ! $isFixed;
    $budgetRow = $budgetRow ?? null;
    $spentInMonth = (float) ($spentInMonth ?? 0);
    $coupleIncome = (float) ($coupleIncome ?? 0);
    $editCat = $category->only(['id', 'name', 'type', 'color']);
    $catColor = $category->color ?: '#94a3b8';
@endphp
<div class="card border-0 cat-item-card cat-item-card--clean h-100 {{ $isFixed ? 'cat-item-card--fixed' : '' }}" role="listitem" style="--cat-accent: {{ $catColor }}">
    <div class="cat-item-card__accent" aria-hidden="true"></div>
    <div class="card-body p-0">
        <div class="cat-item-card__top px-3 px-sm-4 py-3">
            <div class="cat-item-card__head-row">
                <div class="cat-item-card__avatar flex-shrink-0 text-white" style="background-color: {{ $catColor }}">
                    @if ($isIncome)
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m7-7H5" /></svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                    @endif
                </div>
                <div class="cat-item-card__text min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                        <h3 class="cat-item-card__title mb-0 text-truncate">{{ $category->name }}</h3>
                        @if ($isFixed)
                            <span class="cat-item-card__type cat-item-card__type--fixed">Fixa</span>
                        @endif
                    </div>
                    @if ($isFixed)
                        <p class="cat-item-card__meta cat-item-card__meta--short small mb-0 mt-1">
                            @if ($category->isCreditCardInvoicePayment())
                                Quitação de fatura — não editável.
                            @else
                                Reservada ao sistema — não editável.
                            @endif
                        </p>
                    @endif
                </div>
                @unless ($isFixed)
                    <div class="cat-item-card__quick-actions flex-shrink-0">
                        <button
                            type="button"
                            class="btn btn-link btn-sm p-0 text-decoration-none cat-item-card__link-edit"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="Editar nome, tipo ou cor desta categoria"
                            data-edit-category='@json($editCat)'
                        >
                            Editar
                        </button>
                        <span class="cat-item-card__action-sep text-secondary" aria-hidden="true">·</span>
                        <form class="cat-item-card__delete-form" action="{{ route('categories.destroy', $category) }}" method="POST" data-confirm-title="Excluir categoria" data-confirm="Deseja excluir esta categoria?" data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-link btn-sm cat-item-card__link-delete p-0 text-danger text-decoration-none" data-bs-toggle="tooltip" data-bs-placement="top" title="Excluir esta categoria">
                                Excluir
                            </button>
                        </form>
                    </div>
                @endunless
            </div>
        </div>

        @if ($showBudgetMeta)
            @php
                $budget = $budgetRow;
                $spent = $spentInMonth;
                $income = $coupleIncome;
                $usagePercent = $budget && (float) $budget->amount > 0 ? ($spent / (float) $budget->amount) * 100 : 0;
                $budgetVsIncome = $budget && $income > 0 ? ((float) $budget->amount / $income) * 100 : 0;
                $isBudgetErrorRow = (int) old('category_id') === (int) $category->id && old('_form') === 'budget-store';
                $barWidth = $budget ? number_format(max(0, min(100, $usagePercent)), 2, '.', '') : '0';
                $barColor = $usagePercent > 100 ? 'bg-danger' : ($usagePercent > 80 ? 'bg-warning' : 'bg-success');
            @endphp
            <div class="cat-item-card__budget px-3 px-sm-4 pb-3 pt-0">
                @if ($budget)
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1 small">
                        <span class="text-secondary text-truncate">
                            <span class="tabular-nums">{{ number_format($spent, 2, ',', '.') }}</span>
                            <span class="text-secondary opacity-75"> / </span>
                            <span class="tabular-nums fw-medium text-body">{{ number_format((float) $budget->amount, 2, ',', '.') }}</span>
                            <span class="d-none d-sm-inline text-secondary"> · {{ number_format($usagePercent, 0) }}%</span>
                        </span>
                        <span class="flex-shrink-0 fw-semibold tabular-nums {{ $spent > (float) $budget->amount ? 'text-danger' : 'text-body-secondary' }}">
                            {{ number_format($budgetVsIncome, 0) }}% renda
                        </span>
                    </div>
                    <div class="progress rounded-pill cat-item-card__budget-progress mb-2">
                        <div class="progress-bar rounded-pill {{ $barColor }}" style="width: {{ $barWidth }}%"></div>
                    </div>
                @endif

                <form action="{{ route('budgets.store') }}" method="POST" class="cat-item-card__budget-form d-flex gap-2 align-items-stretch">
                    @csrf
                    <input type="hidden" name="_form" value="budget-store">
                    <input type="hidden" name="category_id" value="{{ $category->id }}">
                    <input
                        type="number"
                        name="amount"
                        id="budget_amount_{{ $category->id }}"
                        step="0.01"
                        min="0"
                        required
                        class="form-control form-control-sm cat-item-card__budget-input flex-grow-1 {{ $isBudgetErrorRow && $errors->has('amount') ? 'is-invalid' : '' }}"
                        value="{{ $isBudgetErrorRow ? old('amount') : ($budget ? number_format((float) $budget->amount, 2, '.', '') : '') }}"
                        placeholder="Meta no mês (R$)"
                        aria-label="Meta mensal em reais"
                        inputmode="decimal"
                    />
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill cat-item-card__budget-submit px-3 flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="Salvar a meta de orçamento deste mês para a categoria">Salvar</button>
                </form>
                @if ($isBudgetErrorRow)
                    <x-input-error :messages="$errors->get('category_id')" class="mt-1 small" />
                    <x-input-error :messages="$errors->get('amount')" class="mt-1 small" />
                @endif
            </div>
        @endif
    </div>
</div>
