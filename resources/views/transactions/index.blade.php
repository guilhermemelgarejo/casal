<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Lançamentos') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if (session('success'))
                    <div class="mb-4 text-green-600 font-medium">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <!-- Novo Lançamento -->
                    <div x-data="{ 
                        type: '{{ old('type', 'expense') }}',
                        categories: {{ json_encode($categories) }},
                        get filteredCategories() {
                            return this.categories.filter(c => c.type === this.type);
                        }
                    }" x-init="$watch('type', () => { document.getElementById('category_id').value = '' })">
                        <h3 class="text-lg font-medium mb-4">Novo Lançamento</h3>
                        <form action="{{ route('transactions.store') }}" method="POST">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="type" value="Tipo de Lançamento" />
                                    <select id="type" name="type" x-model="type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <option value="expense">Despesa (Saída)</option>
                                        <option value="income">Receita (Entrada)</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="category_id" value="Categoria" />
                                    <select id="category_id" name="category_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <template x-for="cat in filteredCategories" :key="cat.id">
                                            <option :value="cat.id" x-text="cat.name" :selected="cat.id == '{{ old('category_id') }}'"></option>
                                        </template>
                                    </select>
                                    <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="description" value="Descrição" />
                                    <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" required value="{{ old('description') }}" placeholder="Ex: Compras do mês" />
                                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="amount" value="Valor (R$)" />
                                    <x-text-input id="amount" name="amount" type="number" step="0.01" class="mt-1 block w-full" required value="{{ old('amount') }}" placeholder="0,00" />
                                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="payment_method" value="Forma de Pagamento" />
                                        <select id="payment_method" name="payment_method" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                            <option value="">Selecione...</option>
                                            <option value="Dinheiro" {{ old('payment_method') == 'Dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                                            <option value="Cartão de Crédito" {{ old('payment_method') == 'Cartão de Crédito' ? 'selected' : '' }}>Cartão de Crédito</option>
                                            <option value="Cartão de Débito" {{ old('payment_method') == 'Cartão de Débito' ? 'selected' : '' }}>Cartão de Débito</option>
                                            <option value="Pix" {{ old('payment_method') == 'Pix' ? 'selected' : '' }}>Pix</option>
                                            <option value="Boleto" {{ old('payment_method') == 'Boleto' ? 'selected' : '' }}>Boleto</option>
                                            <option value="Outros" {{ old('payment_method') == 'Outros' ? 'selected' : '' }}>Outros</option>
                                        </select>
                                        <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="account_id" value="Conta / Cartão" />
                                        <select id="account_id" name="account_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                            <option value="">Selecione a conta...</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                                        <p class="mt-1 text-[10px] text-gray-400">
                                            <a href="{{ route('accounts.index') }}" class="text-indigo-500 hover:underline">Gerenciar contas</a>
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <x-input-label for="date" value="Data" />
                                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" value="{{ old('date', date('Y-m-d')) }}" required />
                                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                                </div>
                            </div>
                            <div class="mt-6">
                                <x-primary-button class="w-full justify-center">Salvar Lançamento</x-primary-button>
                            </div>
                        </form>
                    </div>

                    <!-- Resumos e Gastos -->
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-medium mb-4">Resumo do Mês ({{ date('m/Y') }})</h3>
                            <div class="bg-gray-50 p-6 rounded-xl border border-gray-100">
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Receitas:</span>
                                        <span class="text-green-600 font-bold text-lg">R$ {{ number_format($transactions->where('type', 'income')->sum('amount'), 2, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Despesas:</span>
                                        <span class="text-red-600 font-bold text-lg">R$ {{ number_format($transactions->where('type', 'expense')->sum('amount'), 2, ',', '.') }}</span>
                                    </div>
                                    <div class="pt-4 border-t border-gray-200 flex justify-between items-center">
                                        <span class="text-gray-800 font-bold">Saldo:</span>
                                        @php $balance = $transactions->where('type', 'income')->sum('amount') - $transactions->where('type', 'expense')->sum('amount'); @endphp
                                        <span class="{{ $balance >= 0 ? 'text-green-600' : 'text-red-600' }} font-extrabold text-xl">
                                            R$ {{ number_format($balance, 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($byPaymentMethod->count() > 0)
                            <div>
                                <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Gastos por Forma de Pagamento</h4>
                                <div class="bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($byPaymentMethod as $method => $total)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-2 text-sm text-gray-700 font-medium">{{ $method }}</td>
                                                    <td class="px-4 py-2 text-sm text-red-600 font-bold text-right text-nowrap">R$ {{ number_format($total, 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if($byAccount->count() > 0)
                            <div>
                                <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Gastos por Conta/Cartão</h4>
                                <div class="bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($byAccount as $account => $total)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-2 text-sm text-gray-700 font-medium">{{ $account }}</td>
                                                    <td class="px-4 py-2 text-sm text-red-600 font-bold text-right text-nowrap">R$ {{ number_format($total, 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Histórico -->
                <div class="mt-10">
                    <h3 class="text-lg font-medium mb-4">Histórico Recente</h3>
                    <div class="overflow-x-auto rounded-xl border border-gray-100">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pagamento/Conta</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($transactions as $transaction)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">{{ $transaction->date->format('d/m/Y') }}</td>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-900">
                                            {{ $transaction->description }}
                                            <p class="text-xs text-gray-400 font-normal">{{ $transaction->user->name }}</p>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-white text-xs font-bold" style="background-color: {{ $transaction->category->color ?? '#ccc' }}">
                                                {{ $transaction->category->name }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                            @if($transaction->payment_method || $transaction->accountModel)
                                                <div class="flex flex-col">
                                                    <span class="font-medium text-gray-700">{{ $transaction->payment_method ?: '-' }}</span>
                                                    <span class="text-xs text-gray-400">{{ $transaction->accountModel ? $transaction->accountModel->name : '-' }}</span>
                                                </div>
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right font-bold {{ $transaction->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $transaction->type === 'income' ? '+' : '-' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-center text-sm">
                                            <form action="{{ route('transactions.destroy', $transaction) }}" method="POST" onsubmit="return confirm('Deseja excluir este lançamento?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-400 hover:text-red-600 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-gray-400">Nenhum lançamento encontrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6">
                        {{ $transactions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
