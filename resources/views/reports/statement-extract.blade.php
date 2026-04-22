@php
    $q = array_filter([
        'account_id' => $filterAccountId,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 mb-0 reports-hero-title">Extrato (contas correntes)</h2>
            <p class="small text-secondary mb-0 mt-1">Apenas contas <strong>regular</strong>; transferências internas numa linha. Cartão não entra aqui.</p>
        </div>
    </x-slot>

    <div class="py-4 reports-page">
        <div class="container-xxl px-3 px-lg-4">
            <div class="card border-0 dz-panel dz-panel--filter mb-4">
                <div class="card-body p-4">
                    <h3 class="dz-section-title mb-3">Filtros</h3>
                    <form method="get" action="{{ route('reports.statement-extract') }}" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary" for="ex-account">Conta</label>
                            <select id="ex-account" name="account_id" class="form-select rounded-3">
                                <option value="">Todas as contas correntes</option>
                                @foreach($regularAccounts as $acc)
                                    <option value="{{ $acc->id }}" @selected((int) ($filterAccountId ?? 0) === (int) $acc->id)>{{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary" for="ex-from">Data início</label>
                            <input id="ex-from" type="date" name="date_from" class="form-control rounded-3" required value="{{ $dateFrom }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary" for="ex-to">Data fim</label>
                            <input id="ex-to" type="date" name="date_to" class="form-control rounded-3" required value="{{ $dateTo }}">
                        </div>
                        <div class="col-md-3 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">Filtrar</button>
                            <a class="btn btn-outline-secondary rounded-pill px-3" href="{{ route('reports.statement-extract', array_merge($q, ['format' => 'csv'])) }}">Exportar CSV</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 dz-panel">
                <div class="card-body px-4 pt-4 pb-0">
                    <h3 class="dz-section-title mb-0">Movimentos</h3>
                    <p class="small text-secondary mt-1 mb-0">Valores positivos entradas; negativos saídas.</p>
                </div>
                <div class="card-body p-0 pt-2">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 dz-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Detalhe</th>
                                    <th>Tipo</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $r)
                                    <tr>
                                        <td>{{ $r->date instanceof \DateTimeInterface ? $r->date->format('d/m/Y') : $r->date }}</td>
                                        <td>{{ $r->description }}</td>
                                        <td class="small text-secondary">{{ $r->detail }}</td>
                                        <td>{{ $r->type }}</td>
                                        <td class="text-end fw-semibold {{ $r->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                            R$ {{ number_format((float) $r->amount, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary py-5">Nenhum movimento no intervalo.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
