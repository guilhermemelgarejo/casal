@php
    $catFormMode = old('_form', 'category-store');
    $editingCategoryId = old('editing_category_id');
    $categoryModalOpen = $errors->any() && in_array($catFormMode, ['category-store', 'category-update'], true);
    $formAction =
        $catFormMode === 'category-update' && $editingCategoryId
            ? route('categories.update', ['category' => $editingCategoryId])
            : route('categories.store');
    $typeOld = $categoryModalOpen ? old('type', 'expense') : 'expense';
    $colorOld = $categoryModalOpen ? (old('color') ?: '#000000') : '#000000';
    $budgetableExpenseCount = $categoriesExpense->filter(fn ($c) => ! $c->isReservedSystemCategory())->count();
    $reservedExpenseCount = $categoriesExpense->filter(fn ($c) => $c->isReservedSystemCategory())->count();
    $totalBudgeted = (float) $budgets->sum(fn ($budget) => (float) $budget->amount);
    $totalSpent = (float) collect($spentByCategory)->sum(fn ($amount) => (float) $amount);
    $coupleIncome = (float) (Auth::user()->couple->monthly_income ?? 0);
    $budgetUsagePct = $totalBudgeted > 0 ? min(100, ($totalSpent / $totalBudgeted) * 100) : 0;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <p class="small text-secondary mb-1">Organização financeira</p>
                <h2 class="h5 mb-0 cat-page-title">Categorias</h2>
                <p class="small text-secondary mb-0 mt-1">Classifique lançamentos e acompanhe metas mensais por despesa.</p>
            </div>
            <button
                type="button"
                class="btn btn-primary rounded-pill px-4 py-2 flex-shrink-0"
                id="btn-new-category"
                title="Criar uma nova categoria de receita ou despesa"
                data-bs-toggle="modal"
                data-bs-target="#modalCategoryForm"
            >
                Nova categoria
            </button>
        </div>
    </x-slot>

    <div class="py-4 categories-page">
        <div class="container-xxl px-3 px-lg-4 d-grid gap-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-0 d-flex align-items-start gap-3 rounded-4" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif
            @if ($errors->any() && ! $categoryModalOpen && old('_form') !== 'budget-store')
                <div class="alert alert-danger border-0 shadow-sm mb-0 d-flex align-items-start gap-3 rounded-4" role="alert">
                    <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <div class="pt-1">
                        <p class="fw-semibold mb-1">Não foi possível salvar</p>
                        <ul class="mb-0 ps-3 small">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <section class="cat-hero card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5">
                            <span class="cat-hero__badge">Visão geral</span>
                            <h3 class="cat-hero__title h4 mt-3 mb-2">Categorias e orçamento no mesmo lugar.</h3>
                            <p class="text-secondary mb-0">Receitas organizam entradas; despesas também recebem metas mensais para acompanhar o planejado contra o gasto real.</p>
                        </div>
                        <div class="col-lg-7">
                            <div class="cat-summary-grid">
                                <div class="cat-summary-card cat-summary-card--success">
                                    <span class="cat-summary-card__label">Receitas</span>
                                    <strong class="cat-summary-card__value">{{ $categoriesIncome->count() }}</strong>
                                    <span class="cat-summary-card__hint">categorias de entrada</span>
                                </div>
                                <div class="cat-summary-card cat-summary-card--danger">
                                    <span class="cat-summary-card__label">Despesas</span>
                                    <strong class="cat-summary-card__value">{{ $categoriesExpense->count() }}</strong>
                                    <span class="cat-summary-card__hint">{{ $reservedExpenseCount }} reservada(s)</span>
                                </div>
                                <div class="cat-summary-card cat-summary-card--primary">
                                    <span class="cat-summary-card__label">Metas</span>
                                    <strong class="cat-summary-card__money">R$ {{ number_format($totalBudgeted, 2, ',', '.') }}</strong>
                                    <span class="cat-summary-card__hint">{{ $budgetableExpenseCount }} despesa(s) planejáveis</span>
                                </div>
                                <div class="cat-summary-card cat-summary-card--warning">
                                    <span class="cat-summary-card__label">Gasto real</span>
                                    <strong class="cat-summary-card__money">R$ {{ number_format($totalSpent, 2, ',', '.') }}</strong>
                                    <span class="cat-summary-card__hint">{{ number_format($budgetUsagePct, 1, ',', '.') }}% das metas</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="cat-hero__strip mt-4">
                        <span>Renda: R$ {{ number_format($coupleIncome, 2, ',', '.') }}</span>
                        <span>Planejado: R$ {{ number_format($totalBudgeted, 2, ',', '.') }}</span>
                        <span>Disponível no plano: R$ {{ number_format(max(0, $coupleIncome - $totalBudgeted), 2, ',', '.') }}</span>
                    </div>
                </div>
            </section>

            <div id="orcamento" class="budgets-page">
                @include('budgets.partials.embedded')
            </div>

            <div class="row g-4 g-lg-3 gx-lg-4 align-items-start cat-lists-row">
                <div class="col-12 col-lg-6">
                    <section class="h-100" aria-labelledby="cat-income-heading">
                        <div class="cat-list-header cat-list-header--income mb-3">
                            <div class="cat-list-header__income-top d-flex flex-nowrap align-items-center justify-content-between gap-3">
                                <div class="cat-list-header__title-wrap min-w-0 flex-shrink-1">
                                    <span class="cat-list-header__icon cat-list-header__icon--income" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m7-7H5" /></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <span class="cat-section-kicker">Entradas</span>
                                        <h3 class="h5 mb-0" id="cat-income-heading">Receitas</h3>
                                    </div>
                                </div>
                                <div class="cat-list-header__income-actions d-flex flex-nowrap align-items-center justify-content-end gap-2 flex-shrink-0">
                                    @if ($categoriesIncome->isNotEmpty())
                                        <span class="cat-list-header__count badge bg-success-subtle text-success-emphasis border border-success-subtle">
                                            {{ $categoriesIncome->count() }} {{ $categoriesIncome->count() === 1 ? 'categoria' : 'categorias' }}
                                        </span>
                                    @endif
                                    @include('budgets.partials.income-toolbar')
                                </div>
                            </div>
                        </div>

                        <div class="cat-mosaic" role="list">
                            @forelse ($categoriesIncome as $cat)
                                @include('categories.partials.category-card', ['category' => $cat])
                            @empty
                                <div class="cat-empty text-center py-4 px-3">
                                    <div class="rounded-3 bg-white bg-opacity-50 d-inline-flex align-items-center justify-content-center p-3 mb-3 shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="rgb(var(--bs-success-rgb))" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m7-7H5" /></svg>
                                    </div>
                                    <p class="fw-semibold text-body mb-1">Nenhuma categoria de receita</p>
                                    <p class="small text-secondary mb-0 mx-auto" style="max-width: 18rem;">Use <strong>Nova categoria</strong> e tipo <strong>Receita</strong>.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="col-12 col-lg-6">
                    <section class="h-100" aria-labelledby="cat-expense-heading">
                        <div class="cat-list-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div class="cat-list-header__title-wrap min-w-0">
                                <span class="cat-list-header__icon cat-list-header__icon--expense" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                                </span>
                                <div class="min-w-0">
                                    <span class="cat-section-kicker">Saídas</span>
                                    <h3 class="h5 mb-0" id="cat-expense-heading">Despesas</h3>
                                </div>
                            </div>
                            @if ($categoriesExpense->isNotEmpty())
                                <span class="cat-list-header__count badge bg-danger-subtle text-danger-emphasis border border-danger-subtle flex-shrink-0">
                                    {{ $categoriesExpense->count() }} {{ $categoriesExpense->count() === 1 ? 'categoria' : 'categorias' }}
                                </span>
                            @endif
                        </div>

                        <div class="cat-mosaic" role="list">
                            @forelse ($categoriesExpense as $cat)
                                @include('categories.partials.category-card', [
                                    'category' => $cat,
                                    'budgetRow' => $cat->isReservedSystemCategory() ? null : $budgets->firstWhere('category_id', $cat->id),
                                    'spentInMonth' => $cat->isReservedSystemCategory() ? null : (float) ($spentByCategory[$cat->id] ?? 0),
                                    'coupleIncome' => $cat->isReservedSystemCategory() ? null : (float) (Auth::user()->couple->monthly_income ?? 0),
                                ])
                            @empty
                                <div class="cat-empty text-center py-4 px-3">
                                    <div class="rounded-3 bg-white bg-opacity-50 d-inline-flex align-items-center justify-content-center p-3 mb-3 shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="rgb(var(--bs-danger-rgb))" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 12H4" /></svg>
                                    </div>
                                    <p class="fw-semibold text-body mb-1">Nenhuma categoria de despesa</p>
                                    <p class="small text-secondary mb-0 mx-auto" style="max-width: 18rem;">Use <strong>Nova categoria</strong> com tipo <strong>Despesa</strong>.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="modalCategoryForm"
        tabindex="-1"
        aria-labelledby="modalCategoryFormLabel"
        aria-hidden="true"
        data-open-on-load="{{ $categoryModalOpen ? '1' : '0' }}"
    >
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-cat-form">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form
                    id="category-form"
                    action="{{ $formAction }}"
                    method="POST"
                    class="d-flex flex-column"
                    data-store-url="{{ route('categories.store') }}"
                >
                    @csrf
                    @if ($catFormMode === 'category-update' && $editingCategoryId)
                        @method('PUT')
                    @endif
                    <input type="hidden" name="_form" id="category-form-mode" value="{{ $catFormMode }}">
                    <input type="hidden" name="editing_category_id" id="category-editing-id" value="{{ $editingCategoryId }}">

                    <div class="modal-header align-items-start cat-modal-form-head">
                        <div class="pe-3">
                            <h2 class="modal-title h5 mb-1" id="modalCategoryFormLabel">
                                <span
                                    id="category-form-title"
                                    data-title-new="Nova categoria"
                                    data-title-edit="Editar categoria"
                                >{{ $catFormMode === 'category-update' ? 'Editar categoria' : 'Nova categoria' }}</span>
                            </h2>
                            <p class="small text-secondary mb-0 fw-normal">Nome, tipo e cor.</p>
                        </div>
                        <button type="button" class="btn-close flex-shrink-0 mt-1" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body vstack gap-3 py-3">
                        <div>
                            <x-input-label for="name" value="Nome" />
                            <x-text-input id="name" name="name" type="text" class="mt-1" required placeholder="Ex.: Alimentação, Salário…" value="{{ $categoryModalOpen ? old('name') : '' }}" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-sm-7">
                                <x-input-label for="type" value="Tipo" />
                                <select id="type" name="type" class="form-select mt-1">
                                    <option value="expense" {{ $typeOld === 'expense' ? 'selected' : '' }}>Despesa</option>
                                    <option value="income" {{ $typeOld === 'income' ? 'selected' : '' }}>Receita</option>
                                </select>
                                <x-input-error :messages="$errors->get('type')" class="mt-2" />
                            </div>
                            <div class="col-sm-5">
                                <x-input-label for="color" value="Cor" />
                                <input
                                    type="color"
                                    id="color"
                                    name="color"
                                    value="{{ $colorOld }}"
                                    class="form-control form-control-color w-100 mt-1 category-form-color-input"
                                >
                                <x-input-error :messages="$errors->get('color')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer flex-wrap gap-2 justify-content-between border-top">
                        <x-secondary-button type="button" id="category-cancel-edit" class="rounded-pill px-4 {{ $catFormMode === 'category-update' ? '' : 'd-none' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Descartar edição e fechar o formulário">
                            Cancelar
                        </x-secondary-button>
                        <div class="ms-auto">
                            <x-primary-button type="submit" class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Salvar a categoria com os dados indicados">
                                <span id="category-submit-label">{{ $catFormMode === 'category-update' ? 'Atualizar' : 'Salvar' }}</span>
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
