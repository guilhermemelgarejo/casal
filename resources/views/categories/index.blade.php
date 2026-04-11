<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 mb-0 cat-page-title">Categorias</h2>
            <p class="small text-secondary mb-0 mt-1">Organizam receitas e despesas nos lançamentos e no orçamento. A categoria de quitação de fatura é fixa e não pode ser editada.</p>
        </div>
    </x-slot>

    <div class="py-4 categories-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
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

            <div class="row g-4 align-items-start">
                <div class="col-12 col-lg-5">
                    <div class="card border-0 shadow-sm overflow-hidden cat-form-card h-100">
                        <div class="cat-form-head px-4 py-3">
                            <span
                                id="category-form-title"
                                class="h5 mb-0 fw-semibold d-block"
                                data-title-new="Nova categoria"
                                data-title-edit="Editar categoria"
                            >Nova categoria</span>
                            <p class="small text-secondary mb-0 mt-1">Defina nome, tipo e cor. Use <strong class="fw-medium text-body">Editar</strong> numa linha da lista para alterar.</p>
                        </div>
                        <div class="card-body p-4">
                            <form
                                id="category-form"
                                data-store-url="{{ route('categories.store') }}"
                                action="{{ route('categories.store') }}"
                                method="POST"
                            >
                                @csrf
                                <div class="vstack gap-3">
                                    <div>
                                        <x-input-label for="name" value="Nome" />
                                        <x-text-input id="name" name="name" type="text" class="mt-1" required placeholder="Ex.: Alimentação, Salário…" />
                                    </div>
                                    <div class="row g-3 align-items-end">
                                        <div class="col-sm-7">
                                            <x-input-label for="type" value="Tipo" />
                                            <select id="type" name="type" class="form-select mt-1">
                                                <option value="expense">Despesa</option>
                                                <option value="income">Receita</option>
                                            </select>
                                        </div>
                                        <div class="col-sm-5">
                                            <x-input-label for="color" value="Cor" />
                                            <x-text-input
                                                id="color"
                                                name="color"
                                                type="color"
                                                class="form-control-color w-100 mt-1 category-form-color-input"
                                            />
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 pt-1">
                                        <x-primary-button type="submit" class="rounded-pill px-4">
                                            <span id="category-submit-label">Salvar</span>
                                        </x-primary-button>
                                        <x-secondary-button type="button" id="category-cancel-edit" class="d-none rounded-pill px-3">
                                            Cancelar
                                        </x-secondary-button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="cat-list-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h3 class="h5 mb-0">Suas categorias</h3>
                            <p class="small text-secondary mb-0">Receitas e despesas, ordenadas por nome.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-2">
                                {{ $categoriesIncome->count() }} receita{{ $categoriesIncome->count() === 1 ? '' : 's' }}
                            </span>
                            <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle px-3 py-2">
                                {{ $categoriesExpense->count() }} despesa{{ $categoriesExpense->count() === 1 ? '' : 's' }}
                            </span>
                        </div>
                    </div>

                    <div class="vstack gap-4">
                        <div class="card border-0 shadow-sm overflow-hidden cat-type-card">
                            <div class="cat-type-head cat-type-head--income px-4 py-3 d-flex flex-wrap align-items-center gap-2">
                                <span class="badge rounded-pill bg-success px-3 py-2 fw-semibold">Receitas</span>
                                <span class="small text-secondary mb-0">Entradas de dinheiro</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 cat-table">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Nome</th>
                                            <th>Cor</th>
                                            <th class="text-end pe-4">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($categoriesIncome as $cat)
                                            <tr>
                                                <td class="ps-4 fw-medium">{{ $cat->name }}</td>
                                                <td>
                                                    <div class="cat-swatch" style="background-color: {{ $cat->color }}" title="Cor da categoria"></div>
                                                </td>
                                                <td class="text-end text-nowrap pe-4">
                                                    @if ($cat->isCreditCardInvoicePayment())
                                                        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border">Fixa</span>
                                                    @else
                                                        <button
                                                            type="button"
                                                            class="btn btn-link btn-sm p-0 me-2 text-decoration-none"
                                                            @php($editCat = $cat->only(['id', 'name', 'type', 'color']))
                                                            data-edit-category='@json($editCat)'
                                                        >
                                                            Editar
                                                        </button>
                                                        <form action="{{ route('categories.destroy', $cat) }}" method="POST" class="d-inline" data-confirm-title="Excluir categoria" data-confirm="Deseja excluir esta categoria?" data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0 text-decoration-none">Excluir</button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="cat-empty-row">
                                                <td colspan="3" class="py-4 px-4">
                                                    <div class="cat-empty-box text-center py-4 px-3 small text-secondary mb-0">
                                                        Nenhuma categoria de receita ainda.
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm overflow-hidden cat-type-card">
                            <div class="cat-type-head cat-type-head--expense px-4 py-3 d-flex flex-wrap align-items-center gap-2">
                                <span class="badge rounded-pill bg-danger px-3 py-2 fw-semibold">Despesas</span>
                                <span class="small text-secondary mb-0">Saídas e gastos</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 cat-table">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Nome</th>
                                            <th>Cor</th>
                                            <th class="text-end pe-4">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($categoriesExpense as $cat)
                                            <tr>
                                                <td class="ps-4 fw-medium">{{ $cat->name }}</td>
                                                <td>
                                                    <div class="cat-swatch" style="background-color: {{ $cat->color }}" title="Cor da categoria"></div>
                                                </td>
                                                <td class="text-end text-nowrap pe-4">
                                                    @if ($cat->isCreditCardInvoicePayment())
                                                        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border" title="Categoria do sistema para quitação em Faturas de cartão">Fixa</span>
                                                    @else
                                                        <button
                                                            type="button"
                                                            class="btn btn-link btn-sm p-0 me-2 text-decoration-none"
                                                            @php($editCat = $cat->only(['id', 'name', 'type', 'color']))
                                                            data-edit-category='@json($editCat)'
                                                        >
                                                            Editar
                                                        </button>
                                                        <form action="{{ route('categories.destroy', $cat) }}" method="POST" class="d-inline" data-confirm-title="Excluir categoria" data-confirm="Deseja excluir esta categoria?" data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0 text-decoration-none">Excluir</button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="cat-empty-row">
                                                <td colspan="3" class="py-4 px-4">
                                                    <div class="cat-empty-box text-center py-4 px-3 small text-secondary mb-0">
                                                        Nenhuma categoria de despesa ainda.
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
