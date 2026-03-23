<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-xl text-gray-800 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            
            <!-- Filtro de Calendário Compacto -->
            <form action="{{ route('dashboard') }}" method="GET" class="flex items-center gap-2">
                <div class="relative">
                    <input type="month" name="period" value="{{ $period }}" 
                        class="rounded-xl border-gray-300 text-sm font-bold text-gray-700 focus:ring-indigo-500 focus:border-indigo-500 py-1.5 px-3 shadow-sm bg-gray-50">
                </div>

                <x-primary-button type="submit" class="py-1.5 px-4 text-[10px] shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    {{ __('Filtrar') }}
                </x-primary-button>

                @if(request()->has('period'))
                    <a href="{{ route('dashboard') }}" class="w-9 h-9 rounded-xl flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all border border-transparent hover:border-red-100" title="Limpar Filtro">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Os Cards de Resumo começam aqui -->
            <!-- Cards de Resumo -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-12 h-12 bg-green-100 text-green-600 rounded-2xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Receitas</p>
                        <p class="text-xl font-black text-gray-900">R$ {{ number_format($totalIncome, 2, ',', '.') }}</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-12 h-12 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Despesas</p>
                        <p class="text-xl font-black text-gray-900">R$ {{ number_format($totalExpense, 2, ',', '.') }}</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-12 h-12 {{ $balance >= 0 ? 'bg-indigo-100 text-indigo-600' : 'bg-red-100 text-red-600' }} rounded-2xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Saldo do Período</p>
                        <p class="text-xl font-black {{ $balance >= 0 ? 'text-indigo-600' : 'text-red-600' }}">R$ {{ number_format($balance, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Seção Onde e Como você gastou - Versão Final Refinada -->
                <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
                        <div>
                            <h3 class="text-xl font-black text-gray-900 tracking-tight">Onde e como você gastou</h3>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mt-1">Detalhamento por conta e pagamento</p>
                        </div>
                        <div class="p-3 bg-white rounded-2xl shadow-sm border border-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>

                    <div class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            @forelse($crossSummary as $item)
                                <div class="flex flex-col h-full bg-white border border-gray-100 rounded-[2rem] p-6 hover:shadow-xl hover:shadow-gray-100 transition-all duration-300">
                                    <!-- Header da Conta -->
                                    <div class="flex items-center gap-4 mb-6">
                                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white shadow-sm" style="background-color: {{ $item['account_color'] }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-black text-gray-900 uppercase text-sm tracking-tighter">{{ $item['account_name'] }}</h4>
                                            <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">Total na Conta</p>
                                        </div>
                                    </div>

                                    <!-- Lista de Métodos -->
                                    <div class="flex-1 space-y-4">
                                        @foreach($item['methods'] as $method => $total)
                                            <div class="flex justify-between items-center group">
                                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide group-hover:text-gray-600 transition-colors">{{ $method }}</span>
                                                <span class="text-sm font-black text-gray-700">R$ {{ number_format($total, 2, ',', '.') }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <!-- Total Rodapé do Card -->
                                    <div class="mt-8 pt-5 border-t border-gray-50 flex justify-between items-center">
                                        <span class="text-[10px] font-black text-gray-300 uppercase tracking-[0.2em]">Total</span>
                                        <span class="text-xl font-black text-gray-900 tracking-tighter">
                                            R$ {{ number_format(array_sum($item['methods']->toArray()), 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-full py-20 text-center bg-gray-50/50 rounded-[2.5rem] border-2 border-dashed border-gray-100">
                                    <p class="text-xs font-black text-gray-400 uppercase tracking-[0.3em]">Nenhuma movimentação detalhada</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Últimos Lançamentos (Lista Completa de Filtro) -->
                <div class="lg:col-span-2 bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100">
                    <h3 class="text-lg font-black text-gray-900 tracking-tighter mb-6">Lançamentos do Período</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Data</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Descrição</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Categoria</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Conta</th>
                                    <th class="px-4 py-3 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse ($transactions as $transaction)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 font-bold">{{ $transaction->date->format('d/m/Y') }}</td>
                                        <td class="px-4 py-4 text-sm font-bold text-gray-900">{{ $transaction->description }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-3 py-1 rounded-full text-[10px] font-black text-white uppercase" style="background-color: {{ $transaction->category->color ?? '#ccc' }}">
                                                {{ $transaction->category->name }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 font-bold">
                                            {{ $transaction->accountModel->name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right font-black {{ $transaction->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $transaction->type === 'income' ? '+' : '-' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-gray-400 font-bold uppercase text-xs">Nenhum lançamento neste período</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
