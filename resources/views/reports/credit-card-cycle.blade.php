<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-0 reports-hero-title">Ciclo de fatura — {{ $account->name }}</h2>
                <p class="small text-secondary mb-0 mt-1">Referência <span class="text-body fw-medium">{{ sprintf('%02d/%04d', $referenceMonth, $referenceYear) }}</span> (alinhado ao KPI do painel).</p>
            </div>
            <a href="{{ route('credit-card-statements.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill align-self-lg-center">← Faturas de cartão</a>
        </div>
    </x-slot>

    <div class="py-4 reports-page">
        <div class="container-xxl px-3 px-lg-4">
            @if($isOpen)
                <div class="alert alert-warning border-0 rounded-4 shadow-sm mb-4 d-flex align-items-start gap-3" role="status">
                    <span class="rounded-3 bg-warning-subtle text-warning d-flex align-items-center justify-content-center flex-shrink-0 p-2" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <div class="small pt-1">
                        <strong>Parcial</strong> — valores até {{ $generatedAt->format('d/m/Y H:i') }}; podem mudar com novos lançamentos ou pagamentos.
                    </div>
                </div>
            @endif

            <div class="row g-3 g-lg-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 dz-stat-card h-100">
                        <div class="card-body p-4">
                            <p class="dz-kpi-label mb-0">Total do ciclo</p>
                            <p class="h4 mb-0 fw-semibold mt-2">R$ {{ number_format($spentTotal, 2, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 dz-stat-card h-100">
                        <div class="card-body p-4">
                            <p class="dz-kpi-label mb-0">Estado</p>
                            <div class="mt-2">
                                @if($isPaid && ! $hasPartial)
                                    <span class="badge text-bg-success rounded-pill px-3 py-2">Quitada</span>
                                @elseif($hasPartial)
                                    <span class="badge text-bg-warning text-dark rounded-pill px-3 py-2">Pagamento parcial</span>
                                @else
                                    <span class="badge text-bg-secondary rounded-pill px-3 py-2">Em aberto</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 dz-stat-card h-100">
                        <div class="card-body p-4">
                            <p class="dz-kpi-label mb-0">A pagar (metadados)</p>
                            <p class="h5 mb-0 fw-semibold mt-2">R$ {{ number_format($statement?->remainingToPay() ?? max(0, $spentTotal), 2, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 dz-panel mb-4">
                <div class="card-body p-4">
                    <h3 class="dz-section-title mb-3">Gasto por categoria (splits)</h3>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 dz-table">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categoryTotals as $ct)
                                    <tr>
                                        <td>{{ $ct->category_name }}</td>
                                        <td class="text-end fw-semibold">R$ {{ number_format((float) $ct->total, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-secondary small py-4">Sem despesas categorizadas neste ciclo.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 dz-panel">
                <div class="card-body p-4">
                    <h3 class="dz-section-title mb-1">Parcelas futuras deste ciclo</h3>
                    <p class="small text-secondary mb-3">Compras parceladas com referência em meses posteriores ao ciclo.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 dz-table">
                            <thead>
                                <tr>
                                    <th>Descrição</th>
                                    <th>Ref.</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($futureInstallments as $tx)
                                    <tr>
                                        <td>{{ $tx->description }}</td>
                                        <td>{{ sprintf('%02d/%04d', $tx->reference_month, $tx->reference_year) }}</td>
                                        <td class="text-end fw-semibold">R$ {{ number_format((float) $tx->amount, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-secondary small py-4">Nenhuma parcela futura identificada a partir deste ciclo.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
