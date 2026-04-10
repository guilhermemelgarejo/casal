<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <h2 class="h5 mb-0">Painel</h2>

            <form action="{{ route('dashboard') }}" method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                <input type="month" name="period" value="{{ $period }}" class="form-control form-control-sm" style="width: auto;">

                <x-primary-button type="submit" class="btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="me-1" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    Filtrar
                </x-primary-button>

                @if(request()->has('period'))
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm" title="Limpar Filtro">✕</a>
                @endif
            </form>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-xxl px-3 px-lg-4">
            @if($showAlert)
                <div class="alert alert-danger d-flex align-items-start gap-3 mb-4 border-start border-danger border-4">
                    <div class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <div>
                        <h3 class="h6 text-danger-emphasis mb-1">Atenção com os Gastos!</h3>
                        <p class="small mb-0 text-danger">
                            Vocês já atingiram <strong>{{ number_format($thresholdPercentage, 0) }}%</strong> da renda mensal planejada (R$ {{ number_format($thresholdAmount, 2, ',', '.') }}).
                            Atualmente os gastos somam <strong>R$ {{ number_format($totalExpense, 2, ',', '.') }}</strong>.
                        </p>
                    </div>
                </div>
            @endif

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-success-subtle text-success p-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
                            </div>
                            <div>
                                <p class="small text-secondary text-uppercase fw-bold mb-1">Receitas</p>
                                <p class="h5 mb-0">R$ {{ number_format($totalIncome, 2, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-3 bg-danger-subtle text-danger p-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" /></svg>
                                </div>
                                <div>
                                    <p class="small text-secondary text-uppercase fw-bold mb-1">Despesas</p>
                                    <p class="h5 mb-0">R$ {{ number_format($totalExpense, 2, ',', '.') }}</p>
                                </div>
                            </div>
                            @php
                                $incomeForProgress = $couple->monthly_income ?? 0;
                                $percentage = $incomeForProgress > 0 ? ($totalExpense / $incomeForProgress) * 100 : 0;
                                $threshold = $couple->spending_alert_threshold ?? 80;
                                $isOverThreshold = $percentage >= $threshold;
                            @endphp
                            @if($incomeForProgress > 0)
                                <div class="small">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-uppercase fw-bold {{ $isOverThreshold ? 'text-warning' : 'text-secondary' }}">Uso da Renda</span>
                                        <span class="fw-bold {{ $isOverThreshold ? 'text-warning' : 'text-secondary' }}">{{ number_format($percentage, 1, ',', '.') }}%</span>
                                    </div>
                                    <div class="progress" style="height: 12px;">
                                        <div class="progress-bar {{ $isOverThreshold ? 'bg-warning' : 'bg-primary' }}" style="width: {{ number_format(min($percentage, 100), 2, '.', '') }}%"></div>
                                    </div>
                                </div>
                            @else
                                <p class="small text-secondary bg-body-secondary rounded p-2 mb-0 text-center">Configure a renda mensal em &quot;Casal&quot; para ver o progresso.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-3 p-3 {{ $balance >= 0 ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <p class="small text-secondary text-uppercase fw-bold mb-1">Saldo do Período</p>
                                <p class="h5 mb-0 {{ $balance >= 0 ? 'text-primary' : 'text-danger' }}">R$ {{ number_format($balance, 2, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-body-secondary d-flex justify-content-between align-items-center py-3">
                    <div>
                        <h3 class="h6 mb-0">Onde e como vocês gastaram</h3>
                        <p class="small text-secondary mb-0">Detalhamento por conta e pagamento</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        @forelse($crossSummary as $item)
                            <div class="col-md-6 col-xl-4">
                                <div class="card border h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <div class="rounded-3 text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 3rem; height: 3rem; background-color: {{ $item['account_color'] }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                            </div>
                                            <div>
                                                <h4 class="h6 mb-0 text-uppercase">{{ $item['account_name'] }}</h4>
                                                <p class="small text-secondary text-uppercase mb-0">Total na Conta</p>
                                            </div>
                                        </div>
                                        <div class="vstack gap-2 mb-3">
                                            @foreach($item['methods'] as $method => $total)
                                                <div class="d-flex justify-content-between small">
                                                    <span class="text-secondary text-uppercase">{{ $method }}</span>
                                                    <span class="fw-semibold">R$ {{ number_format($total, 2, ',', '.') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="border-top pt-2 d-flex justify-content-between align-items-center">
                                            <span class="small text-uppercase text-secondary">Total</span>
                                            <span class="h6 mb-0">R$ {{ number_format(array_sum($item['methods']->toArray()), 2, ',', '.') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <p class="text-center text-secondary py-5 mb-0 border rounded bg-body-secondary">Nenhuma movimentação detalhada</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border">
                <div class="card-header py-3">
                    <h3 class="h6 mb-0">Lançamentos do Período</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th>Conta</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($transactions as $transaction)
                                    <tr>
                                        <td class="text-secondary small text-nowrap">{{ $transaction->date->format('d/m/Y') }}</td>
                                        <td class="fw-medium">{{ $transaction->description }}</td>
                                        <td>
                                            <div class="d-flex flex-column gap-1 align-items-start">
                                                @forelse($transaction->categorySplits as $sp)
                                                    <span class="badge rounded-pill text-white" style="background-color: {{ $sp->category->color ?? '#ccc' }}">{{ $sp->category->name }} · R$ {{ number_format((float) $sp->amount, 2, ',', '.') }}</span>
                                                @empty
                                                    <span class="text-secondary small">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="small">{{ $transaction->accountModel->name ?? '-' }}</td>
                                        <td class="text-end fw-bold text-nowrap {{ $transaction->type === 'income' ? 'text-success' : 'text-danger' }}">
                                            {{ $transaction->type === 'income' ? '+' : '-' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-5">Nenhum lançamento neste período</td>
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
