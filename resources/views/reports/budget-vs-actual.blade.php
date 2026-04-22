<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-0 reports-hero-title">Orçamento previsto vs realizado</h2>
                <p class="small text-secondary mb-0 mt-1">Mês <span class="text-body fw-medium">{{ sprintf('%02d/%04d', $month, $year) }}</span> — compare o planeado com o gasto por categoria.</p>
            </div>
            <form method="get" action="{{ route('reports.budget-vs-actual') }}" class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label small fw-semibold text-secondary mb-1" for="br-month">Mês</label>
                    <select id="br-month" name="month" class="form-select form-select-sm rounded-3">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected($month === $m)>{{ sprintf('%02d', $m) }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-semibold text-secondary mb-1" for="br-year">Ano</label>
                    <input id="br-year" type="number" name="year" class="form-control form-control-sm rounded-3" value="{{ $year }}" min="2000" max="2100">
                </div>
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Atualizar</button>
            </form>
        </div>
    </x-slot>

    <div class="py-4 reports-page">
        <div class="container-xxl px-3 px-lg-4">
            <x-cofrinho-promo variant="compact" class="mb-3" />
            <div class="card border-0 dz-panel">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 dz-table">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th class="text-end">Previsto</th>
                                    <th class="text-end">Realizado</th>
                                    <th class="text-end">% utilizado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    @php
                                        $pct = $row->pct;
                                        $barPct = $pct !== null ? min(100, $pct) : 0;
                                        $barClass = 'bg-success';
                                        if ($pct !== null && $pct >= 100) {
                                            $barClass = 'bg-danger';
                                        } elseif ($pct !== null && $pct >= 85) {
                                            $barClass = 'bg-warning';
                                        }
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ $row->category->name }}
                                            @if($row->fora)
                                                <span class="badge text-bg-secondary ms-1">Fora do plano</span>
                                            @endif
                                            @if($row->over)
                                                <span class="badge text-bg-danger ms-1">Estourado</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($row->planned !== null)
                                                R$ {{ number_format($row->planned, 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">R$ {{ number_format($row->realized, 2, ',', '.') }}</td>
                                        <td class="text-end">
                                            @if($pct !== null)
                                                {{ number_format($pct, 1, ',', '.') }}%
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td style="min-width: 120px;">
                                            @if($row->planned !== null && $row->planned > 0)
                                                <div class="progress rounded-pill bg-body-secondary" style="height: 8px;">
                                                    <div class="progress-bar {{ $barClass }}" style="width: {{ number_format($barPct, 2, '.', '') }}%"></div>
                                                </div>
                                            @endif
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
</x-app-layout>
