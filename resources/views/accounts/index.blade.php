<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Gerenciar Contas e Cartões') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if (session('success'))
                    <div class="mb-4 text-green-600 font-medium p-4 bg-green-50 rounded-lg border border-green-200">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Formulário de Cadastro -->
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Nova Conta / Cartão</h3>
                        <form action="{{ route('accounts.store') }}" method="POST" class="space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="name" value="Nome da Conta" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" required placeholder="Ex: Nubank, Itaú, Carteira..." />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="color" value="Cor de Identificação" />
                                <input type="color" id="color" name="color" value="#4f46e5" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm cursor-pointer">
                                <x-input-error :messages="$errors->get('color')" class="mt-2" />
                            </div>

                            <div class="pt-2">
                                <x-primary-button class="w-full justify-center">
                                    Cadastrar Conta
                                </x-primary-button>
                            </div>
                        </form>
                    </div>

                    <!-- Listagem de Contas -->
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Suas Contas</h3>
                        <div class="space-y-3">
                            @forelse($accounts as $account)
                                <div class="bg-white border border-gray-200 rounded-xl p-4 hover:border-indigo-300 transition-all shadow-sm">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-4">
                                            <!-- Ícone da Conta -->
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white shrink-0 shadow-sm" style="background-color: {{ $account->color }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                            </div>
                                            
                                            <!-- Informações -->
                                            <div>
                                                <h4 class="font-bold text-gray-900">{{ $account->name }}</h4>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[10px] text-gray-400 font-medium">Cadastrada em {{ $account->created_at->format('d/m/Y') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Ações -->
                                        <div class="flex items-center gap-2">
                                            <form action="{{ route('accounts.destroy', $account) }}" method="POST" onsubmit="return confirm('Excluir esta conta? Lançamentos vinculados ficarão sem conta.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Excluir conta">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="py-12 text-center bg-gray-50 rounded-2xl border-2 border-dashed border-gray-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                    <p class="text-sm text-gray-400 font-bold uppercase tracking-widest">Nenhuma conta encontrada</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
