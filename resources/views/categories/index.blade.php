<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Categorias') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ 
        editing: false, 
        action: '{{ route('categories.store') }}',
        category: { name: '', type: 'expense', color: '#000000' },
        edit(cat) {
            this.editing = true;
            this.category = { ...cat };
            this.action = '{{ url('categories') }}/' + cat.id;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        reset() {
            this.editing = false;
            this.category = { name: '', type: 'expense', color: '#000000' };
            this.action = '{{ route('categories.store') }}';
        }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if (session('success'))
                    <div class="mb-4 text-green-600 font-medium">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Formulário -->
                    <div>
                        <h3 class="text-lg font-medium mb-4" x-text="editing ? 'Editar Categoria' : 'Nova Categoria'"></h3>
                        
                        <form :action="action" method="POST">
                            @csrf
                            <template x-if="editing">
                                <input type="hidden" name="_method" value="PUT">
                            </template>

                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="name" value="Nome" />
                                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" required x-model="category.name" />
                                </div>
                                <div>
                                    <x-input-label for="type" value="Tipo" />
                                    <select id="type" name="type" x-model="category.type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <option value="expense">Despesa</option>
                                        <option value="income">Receita</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="color" value="Cor" />
                                    <x-text-input id="color" name="color" type="color" class="mt-1 block w-full h-10" x-model="category.color" />
                                </div>
                            </div>
                            <div class="mt-4 flex gap-2">
                                <x-primary-button>
                                    <span x-text="editing ? 'Atualizar Categoria' : 'Salvar Categoria'"></span>
                                </x-primary-button>
                                <x-secondary-button x-show="editing" @click="reset()">
                                    Cancelar
                                </x-secondary-button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabela -->
                    <div>
                        <h3 class="text-lg font-medium mb-4">Categorias Existentes</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cor</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($categories as $cat)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap">{{ $cat->name }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $cat->type === 'expense' ? 'Despesa' : 'Receita' }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="w-6 h-6 rounded border border-gray-200" style="background-color: {{ $cat->color }}"></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                <div class="flex justify-center gap-3">
                                                    <button @click="edit({{ json_encode($cat) }})" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                                    
                                                    <form action="{{ route('categories.destroy', $cat) }}" method="POST" onsubmit="return confirm('Excluir?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900">Excluir</button>
                                                    </form>
                                                </div>
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
</x-app-layout>
