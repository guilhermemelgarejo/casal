<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Planejamento Mensal') }}
            </h2>
            
            <!-- Renda Mensal Simplificada -->
            <div x-data="{ editing: false, income: {{ Auth::user()->couple->monthly_income }} }" class="flex items-center gap-3 bg-white px-4 py-2 rounded-2xl shadow-sm border border-gray-100">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Renda:</span>
                <div class="flex items-center gap-2">
                    <template x-if="!editing">
                        <div class="flex items-center gap-2">
                            <span class="font-black text-gray-900">R$ {{ number_format(Auth::user()->couple->monthly_income, 2, ',', '.') }}</span>
                            <button @click="editing = true" class="text-gray-400 hover:text-indigo-600 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                </svg>
                            </button>
                        </div>
                    </template>
                    <template x-if="editing">
                        <form action="{{ route('budgets.income') }}" method="POST" class="flex items-center gap-2">
                            @csrf
                            <input type="number" name="monthly_income" x-model="income" step="0.01" 
                                class="w-24 border-gray-200 rounded-lg text-sm font-bold p-1 focus:ring-indigo-500" required />
                            <button type="submit" class="text-green-600 hover:text-green-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <button type="button" @click="editing = false" class="text-red-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </form>
                    </template>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Alertas -->
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-r-xl shadow-sm font-medium">
                    {{ session('success') }}
                </div>
            @endif

            @php
                // Cálculo ultra-preciso da soma
                $totalBudgeted = 0;
                foreach($budgets as $b) {
                    $totalBudgeted += (float) $b->amount;
                }
                
                $income = (float) (Auth::user()->couple->monthly_income ?? 0);
                $budgetPercent = $income > 0 ? ($totalBudgeted / $income) * 100 : 0;
                
                // Formatação para CSS (ponto decimal e limite de 100)
                $progressWidth = number_format(max(0, min(100, $budgetPercent)), 2, '.', '');
            @endphp

            <!-- Barra de Resumo Coesa -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 mb-8 flex flex-col md:flex-row items-center gap-6">
                <div class="flex-1 w-full">
                    <div class="flex justify-between items-end mb-3">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Planejamento Total</span>
                        <span class="text-sm md:text-lg font-black {{ $budgetPercent > 100 ? 'text-red-600' : 'text-indigo-600' }}">
                            R$ {{ number_format($totalBudgeted, 2, ',', '.') }} <span class="text-[10px] md:text-xs text-gray-400 font-bold ml-1">de R$ {{ number_format($income, 2, ',', '.') }}</span>
                        </span>
                    </div>
                    <!-- Container da Barra -->
                    <div style="width: 100%; background-color: #e5e7eb; height: 12px; border-radius: 999px; overflow: hidden; position: relative;">
                        <!-- Preenchimento da Barra -->
                        <div style="width: {{ $progressWidth }}%; background-color: {{ $budgetPercent > 100 ? '#ef4444' : '#4f46e5' }}; height: 100%; border-radius: 999px; transition: width 1s ease-in-out;"></div>
                    </div>
                </div>
                
                <!-- Informações de Apoio (Visíveis no Mobile como Grid) -->
                <div class="grid grid-cols-2 md:flex gap-4 md:gap-6 items-center w-full md:w-auto border-t md:border-t-0 md:border-l border-gray-100 pt-4 md:pt-0 md:pl-8 text-center">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Comprometido</p>
                        <p class="text-base md:text-xl font-black {{ $budgetPercent > 100 ? 'text-red-600' : 'text-gray-800' }}">{{ number_format($budgetPercent, 1, ',', '.') }}%</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Disponível</p>
                        <p class="text-base md:text-xl font-black text-green-600">R$ {{ number_format(max(0, $income - $totalBudgeted), 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Coluna Lateral: Ajuste de Limites (Layout Tradicional e Robusto) -->
                <div class="lg:col-span-1 order-1 lg:order-2">
                    <div class="sticky top-6">
                        <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
                            <!-- Cabeçalho do Card -->
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Definir Meta
                                </h3>
                            </div>
                            
                            <!-- Formulário -->
                            <div class="p-6">
                                <form action="{{ route('budgets.store') }}" method="POST" class="space-y-5">
                                    @csrf
                                    
                                    <!-- Categoria -->
                                    <div>
                                        <label for="category_id" class="block text-xs font-bold text-gray-500 uppercase mb-2">Categoria</label>
                                        <select id="category_id" name="category_id" 
                                            class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 px-4 text-sm font-semibold text-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all cursor-pointer">
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Valor -->
                                    <div>
                                        <label for="amount" class="block text-xs font-bold text-gray-500 uppercase mb-2">Valor Mensal (R$)</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                <span class="text-gray-400 font-bold text-sm">R$</span>
                                            </div>
                                            <input type="number" id="amount" name="amount" step="0.01" required
                                                class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-10 pr-4 text-base font-bold text-gray-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder:text-gray-300"
                                                placeholder="0,00">
                                        </div>
                                        <p class="mt-2 text-[10px] text-gray-400 leading-tight">
                                            * Se já existir uma meta para esta categoria, ela será atualizada.
                                        </p>
                                    </div>

                                    <!-- Botão -->
                                    <button type="submit" 
                                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3.5 rounded-xl font-bold text-sm shadow-md shadow-indigo-100 transition-all active:scale-[0.98] flex items-center justify-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        Salvar Planejamento
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if($budgetPercent > 100)
                            <div class="mt-4 p-4 bg-red-50 rounded-2xl border border-red-100 flex items-start gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span class="text-[11px] font-bold text-red-700 leading-tight uppercase tracking-tight">
                                    Atenção: Seu planejamento total ultrapassou sua renda mensal!
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Coluna Principal: Cards de Categorias -->
                <div class="lg:col-span-3 order-2 lg:order-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ($categories as $category)
                            @php
                                $budget = $budgets->where('category_id', $category->id)->first();
                                $spent = Auth::user()->couple->transactions()
                                    ->where('category_id', $category->id)
                                    ->whereMonth('date', date('m'))
                                    ->whereYear('date', date('Y'))
                                    ->sum('amount');
                                $usagePercent = $budget && $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;
                                $budgetVsIncome = $budget && $income > 0 ? ($budget->amount / $income) * 100 : 0;
                            @endphp
                            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 group">
                                <div class="flex justify-between items-start mb-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform" style="background-color: {{ $category->color }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-black text-gray-800 text-lg leading-tight">{{ $category->name }}</h4>
                                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                                                {{ $budget ? number_format($budgetVsIncome, 1, ',', '.') . '% da renda' : 'Sem orçamento' }}
                                            </p>
                                        </div>
                                    </div>
                                    @if($budget)
                                        <div class="text-right">
                                            <p class="text-[10px] text-gray-400 font-bold uppercase">Restante</p>
                                            <p class="font-black {{ $spent > $budget->amount ? 'text-red-500' : 'text-green-500' }}">
                                                R$ {{ number_format(max(0, $budget->amount - $spent), 2, ',', '.') }}
                                            </p>
                                        </div>
                                    @endif
                                </div>

                                @if($budget)
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-end">
                                            <div class="flex flex-col">
                                                <span class="text-[10px] text-gray-400 font-bold uppercase">Gasto</span>
                                                <span class="text-sm font-black text-gray-700 leading-none">R$ {{ number_format($spent, 2, ',', '.') }}</span>
                                            </div>
                                            <div class="flex flex-col items-end">
                                                <span class="text-[10px] text-gray-400 font-bold uppercase">Meta</span>
                                                <span class="text-sm font-black text-gray-400 leading-none">R$ {{ number_format($budget->amount, 2, ',', '.') }}</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Container da Barra de Categoria -->
                                        <div style="width: 100%; background-color: #f3f4f6; height: 12px; border-radius: 999px; overflow: hidden; margin: 12px 0;">
                                            @php
                                                $barWidth = number_format(max(0, min(100, $usagePercent)), 2, '.', '');
                                                $barColor = $usagePercent > 100 ? '#ef4444' : ($usagePercent > 80 ? '#facc15' : '#22c55e');
                                            @endphp
                                            <!-- Preenchimento Real -->
                                            <div style="width: {{ $barWidth }}%; background-color: {{ $barColor }}; height: 100%; border-radius: 999px; transition: width 1s ease-in-out;"></div>
                                        </div>

                                        <div class="flex justify-between items-center text-[10px] font-black uppercase">
                                            <span class="{{ $usagePercent > 100 ? 'text-red-500' : 'text-gray-400' }}">
                                                {{ number_format($usagePercent, 1, ',', '.') }}% Consumido
                                            </span>
                                            @if($usagePercent > 100)
                                                <span class="text-red-500 font-black">Estourou</span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <div class="py-6 text-center bg-gray-50 rounded-2xl border-2 border-dashed border-gray-100">
                                        <p class="text-xs text-gray-400 font-bold uppercase tracking-widest">Defina um limite</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
