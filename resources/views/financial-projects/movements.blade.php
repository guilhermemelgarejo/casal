@php
    if ($period) {
        try {
            $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $period)->locale(app()->getLocale())->translatedFormat('F \d\e Y');
        } catch (\Throwable $e) {
            $periodLabel = $period;
        }
    } else {
        $periodLabel = 'todo o período';
    }

    $periodLabelDisplay = ucfirst($periodLabel);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <p class="small text-secondary mb-1">Cofrinhos</p>
                <h2 class="h5 mb-0 cofrinhos-page-title">Movimentações - {{ $cofrinho->name }}</h2>
                <p class="small text-secondary mb-0 mt-1">{{ $periodLabelDisplay }}</p>
            </div>
            <a href="{{ route('cofrinhos.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Voltar para cofrinhos</a>
        </div>
    </x-slot>

    <div class="py-4 cofrinhos-page cofrinhos-movements-page">
        <div class="container-xxl px-3 px-lg-4 d-grid gap-4">
            <section class="cofrinhos-movements-hero card border-0 shadow-sm" style="--cofrinho-accent: {{ $cofrinho->color ? e($cofrinho->color) : '#0d9488' }}">
                <div class="cofrinhos-project-card__accent" aria-hidden="true"></div>
                <div class="card-body p-4">
                    <div class="row g-4 align-items-end">
                        <div class="col-lg-7">
                            <p class="dz-stat-label mb-2">Resumo do período</p>
                            <div class="cofrinhos-movements-stats">
                                <div class="cofrinhos-mini-stat">
                                    <span>Aportes + juros</span>
                                    <strong class="text-success">R$ {{ number_format($totalAportes, 2, ',', '.') }}</strong>
                                </div>
                                <div class="cofrinhos-mini-stat">
                                    <span>Retiradas</span>
                                    <strong class="text-danger">R$ {{ number_format($totalRetiradas, 2, ',', '.') }}</strong>
                                </div>
                                <div class="cofrinhos-mini-stat">
                                    <span>Saldo do período</span>
                                    <strong class="{{ $saldoPeriodo < 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $saldoPeriodo < 0 ? '-' : '+' }}R$ {{ number_format(abs($saldoPeriodo), 2, ',', '.') }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <form action="{{ route('cofrinhos.movements', $cofrinho) }}" method="GET" class="cofrinhos-filter-shell">
                                <div class="flex-grow-1">
                                    <label class="form-label small text-secondary mb-1" for="cofrinho-movements-period">Período</label>
                                    <input
                                        id="cofrinho-movements-period"
                                        type="text"
                                        name="period"
                                        value="{{ $period ?? '' }}"
                                        class="form-control form-control-sm rounded-pill"
                                        data-duozen-flatpickr="month"
                                        autocomplete="off"
                                    >
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Aplicar</button>
                                    @if($period)
                                        <a href="{{ route('cofrinhos.movements', $cofrinho) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Limpar</a>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <section class="cofrinhos-movements-card card border-0 shadow-sm">
                <div class="card-header border-0 bg-transparent p-4 pb-0">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                        <div>
                            <h3 class="h6 mb-1">Histórico de movimentações</h3>
                            <p class="small text-secondary mb-0">{{ $movements->total() }} registro(s) em {{ $periodLabelDisplay }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive cofrinhos-table-wrap">
                        <table class="table table-sm align-middle mb-0 cofrinhos-movements-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Descrição</th>
                                    <th>Conta</th>
                                    <th>Registrado por</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($movements as $movement)
                                    @php
                                        $isOut = ($movement['kind'] ?? '') === 'retirada';
                                        $amount = (float) ($movement['amount'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td class="text-nowrap">{{ optional($movement['date'])->format('d/m/Y') }}</td>
                                        <td>
                                            @if(($movement['kind'] ?? '') === 'aporte')
                                                <span class="badge rounded-pill text-bg-success-subtle text-success-emphasis border border-success-subtle">Aporte</span>
                                            @elseif(($movement['kind'] ?? '') === 'retirada')
                                                <span class="badge rounded-pill text-bg-danger-subtle text-danger-emphasis border border-danger-subtle">Retirada</span>
                                            @else
                                                <span class="badge rounded-pill text-bg-info-subtle text-info-emphasis border border-info-subtle">Juros</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-medium">{{ $movement['description'] }}</span>
                                            @if(!empty($movement['note']))
                                                <span class="small text-secondary d-block">{{ $movement['note'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $movement['account_name'] ?? '-' }}</td>
                                        <td>{{ $movement['user_name'] ?? '-' }}</td>
                                        <td class="text-end text-nowrap">
                                            <span class="fw-semibold {{ $isOut ? 'text-danger' : 'text-success' }}">
                                                {{ $isOut ? '-' : '+' }}R$ {{ number_format(abs($amount), 2, ',', '.') }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="cofrinhos-empty-row">
                                        <td colspan="6">
                                            <div class="cofrinhos-empty-state text-center">
                                                <div class="cofrinhos-empty-state__icon mx-auto mb-3" aria-hidden="true">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8c-3.866 0-7 1.343-7 3s3.134 3 7 3 7-1.343 7-3-3.134-3-7-3zM5 11v4c0 1.657 3.134 3 7 3s7-1.343 7-3v-4" /></svg>
                                                </div>
                                                <p class="h6 mb-1">Nenhuma movimentação encontrada</p>
                                                <p class="small text-secondary mb-0">Aportes, retiradas e juros lançados para este cofrinho aparecerão aqui.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($movements->hasPages())
                        <div class="mt-4">
                            {{ $movements->links() }}
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
