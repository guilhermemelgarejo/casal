<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-0 reports-hero-title">Fechamento do mês</h2>
                <p class="small text-secondary mb-0 mt-1">Resumo para fechar <span class="text-body fw-medium">{{ sprintf('%02d/%04d', $month, $year) }}</span>.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary btn-sm rounded-pill px-3" href="{{ route('month-closing.show', ['period' => $prevPeriod]) }}">← Mês anterior</a>
                <a class="btn btn-outline-secondary btn-sm rounded-pill px-3" href="{{ route('month-closing.show', ['period' => $nextPeriod]) }}">Mês seguinte →</a>
            </div>
        </div>
    </x-slot>

    <div class="py-4 reports-page">
        <div class="container-xxl px-3 px-lg-4">
            <div class="row g-3 g-lg-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 dz-stat-card h-100">
                        <div class="card-body p-4">
                            <p class="dz-kpi-label mb-0">Receitas</p>
                            <p class="h5 mb-0 fw-semibold mt-2">R$ {{ number_format($kpi['income'], 2, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 dz-stat-card h-100">
                        <div class="card-body p-4">
                            <p class="dz-kpi-label mb-0">Despesas (caixa)</p>
                            <p class="h5 mb-0 fw-semibold mt-2">R$ {{ number_format($kpi['expense'], 2, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 dz-stat-card h-100">
                        <div class="card-body p-4">
                            <p class="dz-kpi-label mb-0">Saldo do período</p>
                            <p class="h5 mb-0 fw-semibold mt-2 {{ $kpi['balance'] >= 0 ? 'text-primary' : 'text-danger' }}">R$ {{ number_format($kpi['balance'], 2, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 dz-stat-card h-100">
                        <div class="card-body p-4">
                            <p class="dz-kpi-label mb-0">Cartão (ciclo {{ sprintf('%02d/%04d', $cycle['month'], $cycle['year']) }})</p>
                            <p class="h5 mb-0 fw-semibold mt-2">R$ {{ number_format($cardSpendCycle, 2, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <x-cofrinho-promo variant="compact" class="mb-3" />

            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 dz-panel h-100">
                        <div class="card-body p-4">
                            <h3 class="dz-section-title mb-2">Fluxo em contas correntes</h3>
                            <p class="small text-secondary mb-3">Referência {{ sprintf('%02d/%04d', $month, $year) }}</p>
                            <p class="small mb-2">Entradas: <strong class="text-success">R$ {{ number_format($regularFlow['in'], 2, ',', '.') }}</strong></p>
                            <p class="small mb-0">Saídas: <strong class="text-danger">R$ {{ number_format($regularFlow['out'], 2, ',', '.') }}</strong></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 dz-panel h-100">
                        <div class="card-body p-4">
                            <h3 class="dz-section-title mb-2">Cartões (ciclo alinhado ao painel)</h3>
                            <ul class="list-unstyled small mb-0">
                                @forelse($cardUsage as $row)
                                    <li class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                                        <span>{{ $row['account']->name }}</span>
                                        <span class="fw-semibold">R$ {{ number_format($row['total'], 2, ',', '.') }}</span>
                                    </li>
                                    <li class="d-flex justify-content-between py-2 small text-secondary">
                                        <span>Limite disponível (hoje)</span>
                                        <span>R$ {{ number_format($row['limit_available'], 2, ',', '.') }}</span>
                                    </li>
                                @empty
                                    <li class="text-secondary py-2">Nenhum cartão cadastrado.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 dz-panel mb-4">
                <div class="card-body p-4">
                    <h3 class="dz-section-title mb-3">Faturas (ciclos ligados ao mês)</h3>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 dz-table">
                            <thead>
                                <tr>
                                    <th>Cartão</th>
                                    <th>Referência</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">A pagar</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($statements as $st)
                                    <tr>
                                        <td>{{ $st->account?->name }}</td>
                                        <td>{{ sprintf('%02d/%04d', $st->reference_month, $st->reference_year) }}</td>
                                        <td class="text-end">R$ {{ number_format((float) $st->spent_total, 2, ',', '.') }}</td>
                                        <td class="text-end fw-semibold">R$ {{ number_format($st->remainingToPay(), 2, ',', '.') }}</td>
                                        <td>
                                            @if($st->isPaid())
                                                <span class="badge text-bg-success">Quitada</span>
                                            @elseif($st->hasPartialPayments())
                                                <span class="badge text-bg-warning text-dark">Parcial</span>
                                            @else
                                                <span class="badge text-bg-secondary">Em aberto</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-secondary small py-4">Sem metadados de fatura neste recorte.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 dz-panel h-100">
                        <div class="card-body p-4">
                            <h3 class="dz-section-title mb-3">Top categorias de despesa</h3>
                            <ul class="list-unstyled small mb-0">
                                @foreach($top as $row)
                                    <li class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                                        <span>{{ $row->name }}</span>
                                        <span class="fw-semibold">R$ {{ number_format($row->total, 2, ',', '.') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 dz-panel h-100">
                        <div class="card-body p-4">
                            <h3 class="dz-section-title mb-3">Maiores despesas do mês</h3>
                            <ul class="list-unstyled small mb-0">
                                @foreach($largestExpenses as $tx)
                                    <li class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                                        <span class="text-truncate me-2" title="{{ $tx->description }}">{{ $tx->description }}</span>
                                        <span class="flex-shrink-0 fw-semibold">R$ {{ number_format((float) $tx->amount, 2, ',', '.') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
