<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">
            {{ __('Lançamentos') }}
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

                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <h3 class="h6 mb-3">Novo Lançamento</h3>
                        <form action="{{ route('transactions.store') }}" method="POST">
                            @csrf
                            <div class="vstack gap-3">
                                <div>
                                    <x-input-label for="transaction-type" value="Tipo de Lançamento" />
                                    <select id="transaction-type" name="type" class="form-select mt-1">
                                        <option value="expense" {{ old('type', 'expense') === 'expense' ? 'selected' : '' }}>Despesa (Saída)</option>
                                        <option value="income" {{ old('type') === 'income' ? 'selected' : '' }}>Receita (Entrada)</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="category_id" value="Categoria" />
                                    <select id="category_id" name="category_id" class="form-select mt-1">
                                        @foreach($categories as $c)
                                            <option
                                                value="{{ $c->id }}"
                                                data-type="{{ $c->type }}"
                                                {{ (string) old('category_id') === (string) $c->id ? 'selected' : '' }}
                                            >
                                                {{ $c->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="description" value="Descrição" />
                                    <x-text-input id="description" name="description" type="text" class="mt-1" required value="{{ old('description') }}" placeholder="Ex: Compras do mês" />
                                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="amount" value="Valor (R$)" />
                                    <x-text-input id="amount" name="amount" type="number" step="0.01" class="mt-1" required value="{{ old('amount') }}" placeholder="0,00" />
                                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <x-input-label for="payment_method" value="Forma de Pagamento" />
                                        <select id="payment_method" name="payment_method" class="form-select mt-1">
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

                                    <div class="col-md-6">
                                        <x-input-label for="account_id" value="Conta / Cartão" />
                                        <select id="account_id" name="account_id" class="form-select mt-1">
                                            <option value="">Selecione a conta...</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                                        <p class="form-text mb-0">
                                            <a href="{{ route('accounts.index') }}">Gerenciar contas</a>
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <x-input-label for="date" value="Data" />
                                    <x-text-input id="date" name="date" type="date" class="mt-1" value="{{ old('date', date('Y-m-d')) }}" required />
                                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                                </div>
                            </div>
                            <div class="mt-4">
                                <x-primary-button class="w-100 justify-content-center">Salvar Lançamento</x-primary-button>
                            </div>
                        </form>
                    </div>

                    <div class="col-lg-6">
                        <div class="vstack gap-4">
                            <div>
                                <h3 class="h6 mb-3">Resumo do Mês ({{ date('m/Y') }})</h3>
                                <div class="card bg-body-secondary border-0">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-secondary">Receitas:</span>
                                            <span class="text-success fw-bold">R$ {{ number_format($transactions->where('type', 'income')->sum('amount'), 2, ',', '.') }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-secondary">Despesas:</span>
                                            <span class="text-danger fw-bold">R$ {{ number_format($transactions->where('type', 'expense')->sum('amount'), 2, ',', '.') }}</span>
                                        </div>
                                        <hr>
                                        @php $balance = $transactions->where('type', 'income')->sum('amount') - $transactions->where('type', 'expense')->sum('amount'); @endphp
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-semibold">Saldo:</span>
                                            <span class="fw-bold fs-5 {{ $balance >= 0 ? 'text-success' : 'text-danger' }}">
                                                R$ {{ number_format($balance, 2, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($byPaymentMethod->count() > 0)
                                <div>
                                    <h4 class="small fw-bold text-secondary text-uppercase mb-2">Gastos por Forma de Pagamento</h4>
                                    <div class="table-responsive border rounded">
                                        <table class="table table-sm mb-0">
                                            <tbody>
                                                @foreach($byPaymentMethod as $method => $total)
                                                    <tr>
                                                        <td>{{ $method }}</td>
                                                        <td class="text-end text-danger fw-semibold text-nowrap">R$ {{ number_format($total, 2, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            @if($byAccount->count() > 0)
                                <div>
                                    <h4 class="small fw-bold text-secondary text-uppercase mb-2">Gastos por Conta/Cartão</h4>
                                    <div class="table-responsive border rounded">
                                        <table class="table table-sm mb-0">
                                            <tbody>
                                                @foreach($byAccount as $account => $total)
                                                    <tr>
                                                        <td>{{ $account }}</td>
                                                        <td class="text-end text-danger fw-semibold text-nowrap">R$ {{ number_format($total, 2, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="border-top pt-4 mt-2">
                    <h3 class="h6 mb-3">Histórico Recente</h3>
                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th>Pagamento/Conta</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($transactions as $transaction)
                                    <tr>
                                        <td class="text-secondary small text-nowrap">{{ $transaction->date->format('d/m/Y') }}</td>
                                        <td>
                                            <div class="fw-medium">{{ $transaction->description }}</div>
                                            <div class="small text-muted">{{ $transaction->user->name }}</div>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill text-white" style="background-color: {{ $transaction->category->color ?? '#ccc' }}">
                                                {{ $transaction->category->name }}
                                            </span>
                                        </td>
                                        <td class="small">
                                            @if($transaction->payment_method || $transaction->accountModel)
                                                <div class="fw-medium">{{ $transaction->payment_method ?: '-' }}</div>
                                                <div class="text-muted">{{ $transaction->accountModel ? $transaction->accountModel->name : '-' }}</div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold text-nowrap {{ $transaction->type === 'income' ? 'text-success' : 'text-danger' }}">
                                            {{ $transaction->type === 'income' ? '+' : '-' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            <form action="{{ route('transactions.destroy', $transaction) }}" method="POST" class="d-inline" onsubmit="return confirm('Deseja excluir este lançamento?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link text-danger btn-sm p-0" title="Excluir">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-secondary py-5">Nenhum lançamento encontrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $transactions->links() }}
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
