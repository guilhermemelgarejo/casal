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

                <div class="row g-4">
                    <div class="col-md-6">
                        <h3 class="h6 mb-3" id="category-form-title" data-title-new="Nova Categoria" data-title-edit="Editar Categoria">Nova Categoria</h3>

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
                                    <x-text-input id="name" name="name" type="text" class="mt-1" required />
                                </div>
                                <div>
                                    <x-input-label for="type" value="Tipo" />
                                    <select id="type" name="type" class="form-select mt-1">
                                        <option value="expense">Despesa</option>
                                        <option value="income">Receita</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="color" value="Cor" />
                                    <x-text-input id="color" name="color" type="color" class="mt-1 form-control-color w-100" />
                                </div>
                            </div>
                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <x-primary-button type="submit">
                                    <span id="category-submit-label">Salvar Categoria</span>
                                </x-primary-button>
                                <x-secondary-button type="button" id="category-cancel-edit" class="d-none">
                                    Cancelar
                                </x-secondary-button>
                            </div>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h6 mb-3">Categorias Existentes</h3>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Cor</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($categories as $cat)
                                        <tr>
                                            <td>{{ $cat->name }}</td>
                                            <td class="small text-secondary">
                                                {{ $cat->type === 'expense' ? 'Despesa' : 'Receita' }}
                                            </td>
                                            <td>
                                                <div class="rounded border" style="width: 1.5rem; height: 1.5rem; background-color: {{ $cat->color }}"></div>
                                            </td>
                                            <td class="text-center text-nowrap">
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
                                            </td>
                                        </tr>
                                    @endforeach
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
