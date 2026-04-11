<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">
            Categorias
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
                @if ($errors->any())
                    <div class="alert alert-danger mb-4">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <div class="rounded-3 border px-3 py-3">
                            <span
                                id="category-form-title"
                                class="d-block small text-secondary mb-2"
                                data-title-new="Nova categoria"
                                data-title-edit="Editar categoria"
                            >Nova categoria</span>

                            <form
                                id="category-form"
                                data-store-url="{{ route('categories.store') }}"
                                action="{{ route('categories.store') }}"
                                method="POST"
                            >
                                @csrf
                                <div class="row g-2 align-items-end">
                                    <div class="col-12">
                                        <x-input-label for="name" value="Nome" class="form-label small text-secondary mb-1" />
                                        <x-text-input id="name" name="name" type="text" class="form-control-sm w-100" required />
                                    </div>
                                    <div class="col-6 col-xl-5">
                                        <x-input-label for="type" value="Tipo" class="form-label small text-secondary mb-1" />
                                        <select id="type" name="type" class="form-select form-select-sm">
                                            <option value="expense">Despesa</option>
                                            <option value="income">Receita</option>
                                        </select>
                                    </div>
                                    <div class="col-6 col-xl-auto">
                                        <x-input-label for="color" value="Cor" class="form-label small text-secondary mb-1" />
                                        <x-text-input
                                            id="color"
                                            name="color"
                                            type="color"
                                            class="form-control-sm form-control-color category-form-color-input"
                                        />
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex gap-2 flex-wrap align-items-center">
                                            <x-primary-button type="submit" class="btn-sm">
                                                <span id="category-submit-label">Salvar</span>
                                            </x-primary-button>
                                            <x-secondary-button type="button" id="category-cancel-edit" class="d-none btn-sm">
                                                Cancelar
                                            </x-secondary-button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <h3 class="h6 mb-3">Suas categorias</h3>
                        <div class="vstack gap-4">
                            <div class="rounded-3 border border-success border-opacity-50 overflow-hidden shadow-sm">
                            <div class="px-3 py-2 bg-success bg-opacity-10 border-bottom border-success border-opacity-25 d-flex align-items-center gap-2">
                                <span class="rounded-pill bg-success px-2 py-1 small fw-semibold text-white">Receitas</span>
                                <span class="small text-secondary">Entradas de dinheiro</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nome</th>
                                            <th>Cor</th>
                                            <th class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($categoriesIncome as $cat)
                                            <tr>
                                                <td>{{ $cat->name }}</td>
                                                <td>
                                                    <div class="rounded border" style="width: 1.5rem; height: 1.5rem; background-color: {{ $cat->color }}"></div>
                                                </td>
                                                <td class="text-end text-nowrap">
                                                    @if ($cat->isCreditCardInvoicePayment())
                                                        <span class="small text-secondary" title="Categoria do sistema para quitação em Faturas de cartão">Fixa</span>
                                                    @else
                                                        <button
                                                            type="button"
                                                            class="btn btn-link btn-sm p-0 me-2"
                                                            @php($editCat = $cat->only(['id', 'name', 'type', 'color']))
                                                            data-edit-category='@json($editCat)'
                                                        >
                                                            Editar
                                                        </button>
                                                        <form action="{{ route('categories.destroy', $cat) }}" method="POST" class="d-inline" data-confirm-title="Excluir categoria" data-confirm="Deseja excluir esta categoria?" data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0">Excluir</button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-secondary small py-3 px-3">Nenhuma categoria de receita ainda.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            </div>

                            <div class="rounded-3 border border-danger border-opacity-50 overflow-hidden shadow-sm">
                            <div class="px-3 py-2 bg-danger bg-opacity-10 border-bottom border-danger border-opacity-25 d-flex align-items-center gap-2">
                                <span class="rounded-pill bg-danger px-2 py-1 small fw-semibold text-white">Despesas</span>
                                <span class="small text-secondary">Saídas e gastos</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nome</th>
                                            <th>Cor</th>
                                            <th class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($categoriesExpense as $cat)
                                            <tr>
                                                <td>{{ $cat->name }}</td>
                                                <td>
                                                    <div class="rounded border" style="width: 1.5rem; height: 1.5rem; background-color: {{ $cat->color }}"></div>
                                                </td>
                                                <td class="text-end text-nowrap">
                                                    @if ($cat->isCreditCardInvoicePayment())
                                                        <span class="small text-secondary" title="Categoria do sistema para quitação em Faturas de cartão">Fixa</span>
                                                    @else
                                                        <button
                                                            type="button"
                                                            class="btn btn-link btn-sm p-0 me-2"
                                                            @php($editCat = $cat->only(['id', 'name', 'type', 'color']))
                                                            data-edit-category='@json($editCat)'
                                                        >
                                                            Editar
                                                        </button>
                                                        <form action="{{ route('categories.destroy', $cat) }}" method="POST" class="d-inline" data-confirm-title="Excluir categoria" data-confirm="Deseja excluir esta categoria?" data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0">Excluir</button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-secondary small py-3 px-3">Nenhuma categoria de despesa ainda.</td>
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
        </div>
    </div>
</x-app-layout>
