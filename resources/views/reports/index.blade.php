@php
    try {
        $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $period)->locale(app()->getLocale())->translatedFormat('F \d\e Y');
    } catch (\Throwable $e) {
        $periodLabel = $period;
    }

    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $pct = fn ($value) => number_format((float) $value, 2, ',', '.').'%';
    $num = fn ($value) => number_format((float) $value, 0, ',', '.');
    $pressurePct = (float) ($executiveKpis['spending_pressure_pct'] ?? 0);
    $pressureBar = max(4, min(100, $pressurePct));
    $budgetCommitmentPct = (float) ($executiveKpis['budget_commitment_pct'] ?? 0);
    $budgetCommitmentBar = max(4, min(100, $budgetCommitmentPct));
    $sparklineData = function (array $values, array $labels = [], bool $signed = false) {
        $vals = collect($values)->map(fn ($v) => (float) $v)->values();
        if ($vals->isEmpty()) {
            return [
                'points' => collect(),
                'polyline' => '',
                'zero_y' => null,
                'signed' => $signed,
                'labels' => [],
            ];
        }

        $chartW = 230.0;
        $chartH = 72.0;
        $padX = 10.0;
        $padY = 8.0;
        $innerW = $chartW - ($padX * 2);
        $innerH = $chartH - ($padY * 2);
        $count = max(1, $vals->count());
        $stepX = $count > 1 ? ($innerW / ($count - 1)) : 0.0;

        $min = (float) $vals->min();
        $max = (float) $vals->max();
        if ($signed) {
            $min = min(0.0, $min);
            $max = max(0.0, $max);
        } else {
            $min = min(0.0, $min);
        }
        if (abs($max - $min) < 0.00001) {
            $max = $min + 1.0;
        }

        $toY = fn (float $v) => $padY + (($max - $v) / ($max - $min)) * $innerH;
        $zeroY = ($signed && $min < 0.0 && $max > 0.0) ? $toY(0.0) : null;

        $points = $vals->map(function (float $v, int $i) use ($padX, $stepX, $toY, $labels, $vals) {
            $label = $labels[$i] ?? '';
            $x = $padX + ($stepX * $i);
            $y = $toY($v);
            $deltaPct = null;
            if ($i > 0) {
                $prev = (float) $vals[$i - 1];
                if (abs($prev) >= 0.00001) {
                    $deltaPct = (($v - $prev) / abs($prev)) * 100.0;
                }
            }

            return [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'value' => $v,
                'label' => (string) $label,
                'is_negative' => $v < 0,
                'delta_pct' => $deltaPct !== null ? round($deltaPct, 2) : null,
            ];
        })->values();

        $polyline = $points->map(fn (array $p) => $p['x'].','.$p['y'])->implode(' ');

        return [
            'points' => $points,
            'polyline' => $polyline,
            'zero_y' => $zeroY !== null ? round($zeroY, 2) : null,
            'signed' => $signed,
            'labels' => $labels,
        ];
    };

    $netTrendLine = $sparklineData((array) ($executiveTrend['net_values'] ?? []), (array) ($executiveTrend['labels'] ?? []), true);
    $pressureTrendLine = $sparklineData((array) ($executiveTrend['pressure_values'] ?? []), (array) ($executiveTrend['labels'] ?? []));
    $budgetTrendLine = $sparklineData((array) ($budgetCommitmentTrend['commitment_values'] ?? []), (array) ($budgetCommitmentTrend['labels'] ?? []));
    $cardTrendLine = $sparklineData((array) ($cardUtilizationTrend['values'] ?? []), (array) ($cardUtilizationTrend['labels'] ?? []));
    $projectTrendLine = $sparklineData((array) ($projectMonthlyNetTrend['values'] ?? []), (array) ($projectMonthlyNetTrend['labels'] ?? []), true);
    $recurringTrendLine = $sparklineData((array) ($recurringDisciplineTrend['values'] ?? []), (array) ($recurringDisciplineTrend['labels'] ?? []));
@endphp

<x-app-layout>
    @php
        $repDate = \Carbon\Carbon::createFromFormat('Y-m', $period);
        $repPrev = $repDate->copy()->subMonth()->format('Y-m');
        $repNext = $repDate->copy()->addMonth()->format('Y-m');
    @endphp

    <x-slot name="header">
        <div>
            <h1 class="dz-page-title">Relatórios Financeiros</h1>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Análise executiva e tendências de gastos
            </div>
        </div>

        <!-- Navegador de Período Inline -->
        <div class="dz-period-nav">
            <a href="{{ route('reports.index', ['period' => $repPrev]) }}" class="dz-period-nav__btn" title="Mês anterior">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="dz-period-nav__label">{{ ucfirst($periodLabel) }}</span>
            <a href="{{ route('reports.index', ['period' => $repNext]) }}" class="dz-period-nav__btn" title="Próximo mês">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </x-slot>

    <div class="container-xxl py-4 px-3 px-lg-4 reports-page">

        <!-- TOP KPIS DUOZEN 2.0 -->
        <section class="dz-kpi-grid mb-4">
            <!-- Receitas -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Total de Receitas</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                        🟢
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-success dz-privacy-blur">
                        {{ $money($executiveKpis['total_income']) }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Entradas no período</span>
                    </div>
                </div>
            </div>

            <!-- Despesas -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Total de Despesas</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--danger">
                        🔴
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-danger dz-privacy-blur">
                        {{ $money($executiveKpis['total_expense']) }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Saídas e compras no cartão</span>
                    </div>
                </div>
            </div>

            <!-- Resultado -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Resultado Líquido</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                        ⚖️
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value {{ $executiveKpis['net_result'] >= 0 ? 'text-success' : 'text-danger' }} dz-privacy-blur">
                        {{ $money($executiveKpis['net_result']) }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Economia do período</span>
                    </div>
                </div>
            </div>

            <!-- Pressão de Gasto -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Pressão de Gasto</span>
                    <div class="dz-kpi-card__icon-box" style="background: {{ $pressurePct >= 80 ? 'rgba(244, 63, 94, 0.15)' : 'rgba(245, 158, 11, 0.15)' }}; color: {{ $pressurePct >= 80 ? 'var(--dz-danger)' : '#D97706' }};">
                        ⚡
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value {{ $pressurePct >= 80 ? 'text-danger' : ($pressurePct >= 60 ? 'text-warning' : 'text-success') }}">
                        {{ $pct($pressurePct) }}
                    </div>
                    <div class="dz-progress-bar">
                        <div class="dz-progress-bar__fill {{ $pressurePct >= 80 ? 'dz-progress-bar__fill--danger' : 'dz-progress-bar__fill--warning' }}" style="width: {{ $pressureBar }}%;"></div>
                    </div>
                    <div class="dz-kpi-card__footer" style="margin-top: 0.5rem;">
                        <span>Renda base: <strong class="dz-privacy-blur">{{ $money($executiveKpis['planned_income']) }}</strong></span>
                    </div>
                </div>
            </div>
        </section>

            <section class="reports-section card border-0 shadow-sm mb-4">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div class="reports-section-title-wrap">
                            <span class="reports-section-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17l6-6 4 4 8-8" /></svg>
                            </span>
                            <div>
                                <span class="reports-section-kicker">Histórico</span>
                                <h3 class="h6 mb-1">Tendências dos últimos 6 meses</h3>
                                <p class="small text-secondary mb-0">Leitura rápida de direção dos indicadores principais.</p>
                            </div>
                        </div>
                    </div>
                    <div class="reports-trend-grid">
                        <article class="reports-trend-card">
                            <p class="reports-trend-title">Resultado mensal</p>
                            @if($netTrendLine['points']->isNotEmpty())
                                <svg class="reports-sparkline-svg is-signed" viewBox="0 0 230 72" role="img" aria-label="Tendência de resultado mensal">
                                    @if($netTrendLine['zero_y'] !== null)
                                        <line x1="10" y1="{{ $netTrendLine['zero_y'] }}" x2="220" y2="{{ $netTrendLine['zero_y'] }}" class="reports-sparkline-zero"></line>
                                    @endif
                                    <polyline class="reports-sparkline-line is-positive" points="{{ $netTrendLine['polyline'] }}"></polyline>
                                    @foreach($netTrendLine['points'] as $point)
                                        <circle
                                            cx="{{ $point['x'] }}"
                                            cy="{{ $point['y'] }}"
                                            r="2.8"
                                            class="reports-sparkline-dot {{ $point['is_negative'] ? 'is-negative' : 'is-positive' }}"
                                            tabindex="0"
                                            data-tip-label="{{ $point['label'] }}"
                                            data-tip-value="{{ $money($point['value']) }}"
                                            data-tip-delta="{{ $point['delta_pct'] !== null ? number_format((float) $point['delta_pct'], 2, '.', '') : '' }}"
                                        >
                                            <title>{{ $point['label'] }}: {{ $money($point['value']) }}</title>
                                        </circle>
                                    @endforeach
                                </svg>
                                <div class="reports-spark-labels">
                                    @foreach($netTrendLine['labels'] as $label)
                                        <span>{{ $label }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="small text-secondary mb-0">Sem histórico suficiente.</p>
                            @endif
                        </article>

                        <article class="reports-trend-card">
                            <p class="reports-trend-title">Pressão de gasto</p>
                            @if($pressureTrendLine['points']->isNotEmpty())
                                <svg class="reports-sparkline-svg" viewBox="0 0 230 72" role="img" aria-label="Tendência de pressão de gasto">
                                    <polyline class="reports-sparkline-line" points="{{ $pressureTrendLine['polyline'] }}"></polyline>
                                    @foreach($pressureTrendLine['points'] as $point)
                                        <circle
                                            cx="{{ $point['x'] }}"
                                            cy="{{ $point['y'] }}"
                                            r="2.8"
                                            class="reports-sparkline-dot"
                                            tabindex="0"
                                            data-tip-label="{{ $point['label'] }}"
                                            data-tip-value="{{ $pct($point['value']) }}"
                                            data-tip-delta="{{ $point['delta_pct'] !== null ? number_format((float) $point['delta_pct'], 2, '.', '') : '' }}"
                                        >
                                            <title>{{ $point['label'] }}: {{ $pct($point['value']) }}</title>
                                        </circle>
                                    @endforeach
                                </svg>
                                <div class="reports-spark-labels">
                                    @foreach($pressureTrendLine['labels'] as $label)
                                        <span>{{ $label }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="small text-secondary mb-0">Sem histórico suficiente.</p>
                            @endif
                        </article>

                        <article class="reports-trend-card">
                            <p class="reports-trend-title">Comprometimento do orçamento</p>
                            @if($budgetTrendLine['points']->isNotEmpty())
                                <svg class="reports-sparkline-svg" viewBox="0 0 230 72" role="img" aria-label="Tendência de comprometimento do orçamento">
                                    <polyline class="reports-sparkline-line" points="{{ $budgetTrendLine['polyline'] }}"></polyline>
                                    @foreach($budgetTrendLine['points'] as $point)
                                        <circle
                                            cx="{{ $point['x'] }}"
                                            cy="{{ $point['y'] }}"
                                            r="2.8"
                                            class="reports-sparkline-dot"
                                            tabindex="0"
                                            data-tip-label="{{ $point['label'] }}"
                                            data-tip-value="{{ $pct($point['value']) }}"
                                            data-tip-delta="{{ $point['delta_pct'] !== null ? number_format((float) $point['delta_pct'], 2, '.', '') : '' }}"
                                        >
                                            <title>{{ $point['label'] }}: {{ $pct($point['value']) }}</title>
                                        </circle>
                                    @endforeach
                                </svg>
                                <div class="reports-spark-labels">
                                    @foreach($budgetTrendLine['labels'] as $label)
                                        <span>{{ $label }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="small text-secondary mb-0">Sem histórico suficiente.</p>
                            @endif
                        </article>

                        <article class="reports-trend-card">
                            <p class="reports-trend-title">Utilização de cartões</p>
                            @if($cardTrendLine['points']->isNotEmpty())
                                <svg class="reports-sparkline-svg" viewBox="0 0 230 72" role="img" aria-label="Tendência de utilização de cartões">
                                    <polyline class="reports-sparkline-line" points="{{ $cardTrendLine['polyline'] }}"></polyline>
                                    @foreach($cardTrendLine['points'] as $point)
                                        <circle
                                            cx="{{ $point['x'] }}"
                                            cy="{{ $point['y'] }}"
                                            r="2.8"
                                            class="reports-sparkline-dot"
                                            tabindex="0"
                                            data-tip-label="{{ $point['label'] }}"
                                            data-tip-value="{{ $pct($point['value']) }}"
                                            data-tip-delta="{{ $point['delta_pct'] !== null ? number_format((float) $point['delta_pct'], 2, '.', '') : '' }}"
                                        >
                                            <title>{{ $point['label'] }}: {{ $pct($point['value']) }}</title>
                                        </circle>
                                    @endforeach
                                </svg>
                                <div class="reports-spark-labels">
                                    @foreach($cardTrendLine['labels'] as $label)
                                        <span>{{ $label }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="small text-secondary mb-0">Sem histórico de cartão.</p>
                            @endif
                        </article>

                        <article class="reports-trend-card">
                            <p class="reports-trend-title">Aporte líquido em cofrinhos</p>
                            @if($projectTrendLine['points']->isNotEmpty())
                                <svg class="reports-sparkline-svg is-signed" viewBox="0 0 230 72" role="img" aria-label="Tendência de aporte líquido em cofrinhos">
                                    @if($projectTrendLine['zero_y'] !== null)
                                        <line x1="10" y1="{{ $projectTrendLine['zero_y'] }}" x2="220" y2="{{ $projectTrendLine['zero_y'] }}" class="reports-sparkline-zero"></line>
                                    @endif
                                    <polyline class="reports-sparkline-line is-positive" points="{{ $projectTrendLine['polyline'] }}"></polyline>
                                    @foreach($projectTrendLine['points'] as $point)
                                        <circle
                                            cx="{{ $point['x'] }}"
                                            cy="{{ $point['y'] }}"
                                            r="2.8"
                                            class="reports-sparkline-dot {{ $point['is_negative'] ? 'is-negative' : 'is-positive' }}"
                                            tabindex="0"
                                            data-tip-label="{{ $point['label'] }}"
                                            data-tip-value="{{ $money($point['value']) }}"
                                            data-tip-delta="{{ $point['delta_pct'] !== null ? number_format((float) $point['delta_pct'], 2, '.', '') : '' }}"
                                        >
                                            <title>{{ $point['label'] }}: {{ $money($point['value']) }}</title>
                                        </circle>
                                    @endforeach
                                </svg>
                                <div class="reports-spark-labels">
                                    @foreach($projectTrendLine['labels'] as $label)
                                        <span>{{ $label }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="small text-secondary mb-0">Sem histórico suficiente.</p>
                            @endif
                        </article>

                        <article class="reports-trend-card">
                            <p class="reports-trend-title">Disciplina de recorrências</p>
                            @if($recurringTrendLine['points']->isNotEmpty())
                                <svg class="reports-sparkline-svg" viewBox="0 0 230 72" role="img" aria-label="Tendência de disciplina de recorrências">
                                    <polyline class="reports-sparkline-line" points="{{ $recurringTrendLine['polyline'] }}"></polyline>
                                    @foreach($recurringTrendLine['points'] as $point)
                                        <circle
                                            cx="{{ $point['x'] }}"
                                            cy="{{ $point['y'] }}"
                                            r="2.8"
                                            class="reports-sparkline-dot"
                                            tabindex="0"
                                            data-tip-label="{{ $point['label'] }}"
                                            data-tip-value="{{ $pct($point['value']) }}"
                                            data-tip-delta="{{ $point['delta_pct'] !== null ? number_format((float) $point['delta_pct'], 2, '.', '') : '' }}"
                                        >
                                            <title>{{ $point['label'] }}: {{ $pct($point['value']) }}</title>
                                        </circle>
                                    @endforeach
                                </svg>
                                <div class="reports-spark-labels">
                                    @foreach($recurringTrendLine['labels'] as $label)
                                        <span>{{ $label }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="small text-secondary mb-0">Sem recorrências ativas.</p>
                            @endif
                        </article>
                    </div>
                </div>
            </section>

            <section class="reports-section card border-0 shadow-sm mb-4">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div class="reports-section-title-wrap">
                            <span class="reports-section-icon reports-section-icon--success" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2 2 4-4M7 4h10a2 2 0 012 2v14l-4-2-4 2-4-2-4 2V6a2 2 0 012-2z" /></svg>
                            </span>
                            <div>
                                <span class="reports-section-kicker">Planejamento</span>
                                <h3 class="h6 mb-1">Orçamento por Categoria</h3>
                                <p class="small text-secondary mb-0">Planejado x realizado sem transferências internas e sem pagamento de fatura.</p>
                            </div>
                        </div>
                        <span class="reports-chip">{{ $num($budgetRows->count()) }} categorias com dados</span>
                    </div>

                    <div class="reports-stat-grid reports-stat-grid--three mb-3">
                        <article class="reports-stat-card">
                            <span class="small text-secondary d-block">Planejado</span>
                            <strong class="duozen-privacy-blur">{{ $money($budgetTotal) }}</strong>
                        </article>
                        <article class="reports-stat-card">
                            <span class="small text-secondary d-block">Realizado</span>
                            <strong class="duozen-privacy-blur">{{ $money($budgetSpentTotal) }}</strong>
                        </article>
                        <article class="reports-stat-card">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <span class="small text-secondary">Comprometimento</span>
                                <span class="reports-kpi-badge {{ $budgetCommitmentPct >= 100 ? 'is-danger' : ($budgetCommitmentPct >= 80 ? 'is-warning' : 'is-ok') }}">{{ $pct($budgetCommitmentPct) }}</span>
                            </div>
                            <div class="progress reports-mini-progress mt-2">
                                <div class="progress-bar {{ $budgetCommitmentPct >= 100 ? 'bg-danger' : ($budgetCommitmentPct >= 80 ? 'bg-warning' : 'bg-primary') }}" style="width: {{ $budgetCommitmentBar }}%"></div>
                            </div>
                        </article>
                    </div>

                    <div class="table-responsive reports-table-wrap">
                        <table class="table table-sm align-middle mb-0 reports-table">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Planejado</th>
                                    <th>Realizado</th>
                                    <th>Variação</th>
                                    <th>Execução</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($budgetRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td><span class="duozen-privacy-blur">{{ $money($row['budget']) }}</span></td>
                                        <td><span class="duozen-privacy-blur">{{ $money($row['spent']) }}</span></td>
                                        <td>
                                            <span class="fw-semibold {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }} duozen-privacy-blur">{{ $money($row['variance']) }}</span>
                                        </td>
                                        <td>
                                            @if($row['execution_pct'] !== null)
                                                <span class="reports-kpi-badge {{ $row['execution_pct'] > 100 ? 'is-danger' : ($row['execution_pct'] >= 80 ? 'is-warning' : 'is-ok') }}">{{ $pct($row['execution_pct']) }}</span>
                                            @else
                                                <span class="text-secondary">Sem meta</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="reports-empty-row">
                                        <td colspan="5">
                                            <div class="reports-empty-state">
                                                <span class="reports-empty-state__icon" aria-hidden="true">—</span>
                                                <div>
                                                    <strong>Sem dados de orçamento</strong>
                                                    <span>Este período ainda não tem metas ou gastos classificados.</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($topCategoryShare->isNotEmpty())
                        <div class="mt-3 reports-share-list">
                            <p class="small text-secondary mb-1">Participação no gasto</p>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($topCategoryShare as $row)
                                    <span class="reports-chip">{{ $row['name'] }}: {{ $pct($row['share_pct']) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="reports-section card border-0 shadow-sm mb-4">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div class="reports-section-title-wrap">
                            <span class="reports-section-icon reports-section-icon--warning" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" /></svg>
                            </span>
                            <div>
                                <span class="reports-section-kicker">Crédito</span>
                                <h3 class="h6 mb-1">Cartões e Faturas</h3>
                                <p class="small text-secondary mb-0">Utilização de limite, saldos em aberto e vencimentos.</p>
                            </div>
                        </div>
                    </div>
                    <div class="reports-stat-grid reports-stat-grid--three mb-3">
                        <article class="reports-stat-card"><span class="small text-secondary d-block">Limite total</span><strong class="duozen-privacy-blur">{{ $money($totalLimit) }}</strong></article>
                        <article class="reports-stat-card"><span class="small text-secondary d-block">Em aberto</span><strong class="duozen-privacy-blur">{{ $money($totalOutstanding) }}</strong></article>
                        <article class="reports-stat-card"><span class="small text-secondary d-block">Utilização consolidada</span><strong>{{ $pct($overallCardUtilizationPct) }}</strong></article>
                    </div>
                    <div class="table-responsive mb-3 reports-table-wrap">
                        <table class="table table-sm align-middle mb-0 reports-table">
                            <thead>
                                <tr>
                                    <th>Cartão</th>
                                    <th>Limite</th>
                                    <th>Em aberto</th>
                                    <th>Utilização</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cardRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td><span class="duozen-privacy-blur">{{ $row['limit_total'] !== null ? $money($row['limit_total']) : 'Sem limite' }}</span></td>
                                        <td><span class="duozen-privacy-blur">{{ $money($row['outstanding']) }}</span></td>
                                        <td>
                                            @if($row['utilization_pct'] !== null)
                                                <span class="reports-kpi-badge {{ $row['utilization_pct'] >= 80 ? 'is-danger' : ($row['utilization_pct'] >= 60 ? 'is-warning' : 'is-ok') }}">{{ $pct($row['utilization_pct']) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="reports-empty-row">
                                        <td colspan="4">
                                            <div class="reports-empty-state">
                                                <span class="reports-empty-state__icon" aria-hidden="true">—</span>
                                                <div>
                                                    <strong>Nenhum cartão encontrado</strong>
                                                    <span>Cadastre cartões para acompanhar limite e utilização.</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive reports-table-wrap">
                        <table class="table table-sm align-middle mb-0 reports-table">
                            <thead>
                                <tr>
                                    <th>Fatura</th>
                                    <th>Em aberto</th>
                                    <th>Vencimento</th>
                                    <th>Dias p/ vencer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($openStatements as $statement)
                                    <tr>
                                        <td>{{ $statement['account_name'] }} - {{ $statement['reference_label'] }}</td>
                                        <td><span class="duozen-privacy-blur">{{ $money($statement['remaining']) }}</span></td>
                                        <td>{{ $statement['due_label'] ?? '-' }}</td>
                                        <td>
                                            @if($statement['days_to_due'] === null)
                                                -
                                            @elseif($statement['days_to_due'] < 0)
                                                <span class="reports-kpi-badge is-danger">{{ abs($statement['days_to_due']) }} atrasado</span>
                                            @elseif($statement['days_to_due'] <= 3)
                                                <span class="reports-kpi-badge is-warning">{{ $statement['days_to_due'] }}</span>
                                            @else
                                                {{ $statement['days_to_due'] }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="reports-empty-row">
                                        <td colspan="4">
                                            <div class="reports-empty-state">
                                                <span class="reports-empty-state__icon" aria-hidden="true">—</span>
                                                <div>
                                                    <strong>Sem faturas em aberto</strong>
                                                    <span>Nenhum ciclo pendente para o período analisado.</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="reports-section card border-0 shadow-sm mb-4">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div class="reports-section-title-wrap">
                            <span class="reports-section-icon reports-section-icon--info" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-3.866 0-7 1.343-7 3s3.134 3 7 3 7-1.343 7-3-3.134-3-7-3zM5 11v4c0 1.657 3.134 3 7 3s7-1.343 7-3v-4" /></svg>
                            </span>
                            <div>
                                <span class="reports-section-kicker">Reservas</span>
                                <h3 class="h6 mb-1">Metas (Cofrinhos)</h3>
                                <p class="small text-secondary mb-0">Progresso acumulado e aporte líquido no período.</p>
                            </div>
                        </div>
                    </div>
                    <div class="reports-stat-grid reports-stat-grid--two mb-3">
                        <article class="reports-stat-card">
                            <span class="small text-secondary d-block">Progresso médio (com meta)</span>
                            <strong>{{ $avgProjectProgressPct !== null ? $pct($avgProjectProgressPct) : 'Sem metas definidas' }}</strong>
                        </article>
                        <article class="reports-stat-card">
                            <span class="small text-secondary d-block">Projetos acompanhados</span>
                            <strong>{{ $num($projectRows->count()) }}</strong>
                        </article>
                    </div>
                    <div class="table-responsive reports-table-wrap">
                        <table class="table table-sm align-middle mb-0 reports-table">
                            <thead>
                                <tr>
                                    <th>Cofrinho</th>
                                    <th>Acumulado</th>
                                    <th>Meta</th>
                                    <th>Progresso</th>
                                    <th>Aporte líquido mês</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($projectRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td><span class="duozen-privacy-blur">{{ $money($row['saved']) }}</span></td>
                                        <td><span class="duozen-privacy-blur">{{ $row['target'] !== null ? $money($row['target']) : 'Sem meta' }}</span></td>
                                        <td>
                                            @if($row['progress_pct'] !== null)
                                                <span class="reports-kpi-badge {{ $row['progress_pct'] >= 100 ? 'is-ok' : ($row['progress_pct'] >= 60 ? 'is-warning' : '') }}">{{ $pct($row['progress_pct']) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td><span class="duozen-privacy-blur">{{ $money($row['monthly_net']) }}</span></td>
                                    </tr>
                                @empty
                                    <tr class="reports-empty-row">
                                        <td colspan="5">
                                            <div class="reports-empty-state">
                                                <span class="reports-empty-state__icon" aria-hidden="true">—</span>
                                                <div>
                                                    <strong>Nenhum cofrinho cadastrado</strong>
                                                    <span>Crie cofrinhos para acompanhar metas e aportes no relatório.</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="reports-section card border-0 shadow-sm mb-4">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div class="reports-section-title-wrap">
                            <span class="reports-section-icon reports-section-icon--secondary" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            </span>
                            <div>
                                <span class="reports-section-kicker">Automação manual</span>
                                <h3 class="h6 mb-1">Recorrências</h3>
                                <p class="small text-secondary mb-0">Previsto x realizado para os modelos ativos no mês.</p>
                            </div>
                        </div>
                    </div>
                    <div class="reports-stat-grid reports-stat-grid--three mb-3">
                        <article class="reports-stat-card"><span class="small text-secondary d-block">Modelos ativos</span><strong>{{ $num($activeRecurringCount) }}</strong></article>
                        <article class="reports-stat-card"><span class="small text-secondary d-block">Realizados</span><strong>{{ $num($completedRecurring) }}</strong></article>
                        <article class="reports-stat-card"><span class="small text-secondary d-block">Disciplina</span><strong>{{ $recurringDisciplinePct !== null ? $pct($recurringDisciplinePct) : '-' }}</strong></article>
                    </div>
                    <div class="table-responsive reports-table-wrap">
                        <table class="table table-sm align-middle mb-0 reports-table">
                            <thead>
                                <tr>
                                    <th>Recorrência pendente</th>
                                    <th>Valor</th>
                                    <th>Dia sugerido</th>
                                    <th>Conta</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingRecurringRows as $row)
                                    <tr>
                                        <td>
                                            <span>{{ $row['description'] }}</span>
                                            @if(!empty($row['is_multiple']))
                                                <span class="badge rounded-pill text-bg-info text-dark-emphasis ms-1" style="font-size: 0.68rem;">Múltiplo</span>
                                            @endif
                                        </td>
                                        <td><span class="duozen-privacy-blur">{{ $money($row['amount']) }}</span></td>
                                        <td>{{ !empty($row['is_multiple']) ? 'Dia atual' : ($row['day_of_month'] ?? '—') }}</td>
                                        <td>{{ $row['account_name'] }}</td>
                                    </tr>
                                @empty
                                    <tr class="reports-empty-row">
                                        <td colspan="4">
                                            <div class="reports-empty-state">
                                                <span class="reports-empty-state__icon" aria-hidden="true">—</span>
                                                <div>
                                                    <strong>Sem pendências no período</strong>
                                                    <span>Todos os modelos ativos foram tratados ou não há recorrências.</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const dots = Array.from(document.querySelectorAll('.reports-sparkline-dot[data-tip-label][data-tip-value]'));
                if (!dots.length) return;

                const tooltip = document.createElement('div');
                tooltip.className = 'reports-custom-tooltip';
                tooltip.setAttribute('role', 'status');
                tooltip.setAttribute('aria-live', 'polite');
                tooltip.innerHTML = '<div class="reports-custom-tooltip__label"></div><div class="reports-custom-tooltip__value duozen-privacy-blur"></div><div class="reports-custom-tooltip__delta"></div>';
                document.body.appendChild(tooltip);

                const labelEl = tooltip.querySelector('.reports-custom-tooltip__label');
                const valueEl = tooltip.querySelector('.reports-custom-tooltip__value');
                const deltaEl = tooltip.querySelector('.reports-custom-tooltip__delta');

                let visible = false;

                function positionTooltip(x, y) {
                    const offset = 14;
                    const rect = tooltip.getBoundingClientRect();
                    const maxLeft = window.innerWidth - rect.width - 8;
                    const maxTop = window.innerHeight - rect.height - 8;
                    const left = Math.max(8, Math.min(maxLeft, x + offset));
                    const top = Math.max(8, Math.min(maxTop, y - rect.height - 10));
                    tooltip.style.left = left + 'px';
                    tooltip.style.top = top + 'px';
                }

                function formatDelta(deltaRaw) {
                    const parsed = Number(deltaRaw);
                    if (!Number.isFinite(parsed)) return null;
                    const sign = parsed > 0 ? '+' : '';
                    return {
                        text: `${sign}${parsed.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}% vs mês anterior`,
                        cls: parsed > 0 ? 'is-up' : (parsed < 0 ? 'is-down' : 'is-flat'),
                    };
                }

                function showTooltip(target, x, y) {
                    labelEl.textContent = target.dataset.tipLabel || '';
                    valueEl.textContent = target.dataset.tipValue || '';
                    deltaEl.textContent = '';
                    deltaEl.className = 'reports-custom-tooltip__delta';
                    const deltaInfo = formatDelta(target.dataset.tipDelta || '');
                    if (deltaInfo !== null) {
                        deltaEl.textContent = deltaInfo.text;
                        deltaEl.classList.add(deltaInfo.cls);
                    } else {
                        deltaEl.textContent = 'Sem comparativo';
                        deltaEl.classList.add('is-muted');
                    }
                    tooltip.classList.add('is-visible');
                    visible = true;
                    positionTooltip(x, y);
                }

                function hideTooltip() {
                    tooltip.classList.remove('is-visible');
                    visible = false;
                }

                dots.forEach((dot) => {
                    dot.addEventListener('mouseenter', (event) => {
                        showTooltip(dot, event.clientX, event.clientY);
                    });
                    dot.addEventListener('mousemove', (event) => {
                        if (!visible) return;
                        positionTooltip(event.clientX, event.clientY);
                    });
                    dot.addEventListener('mouseleave', hideTooltip);

                    dot.addEventListener('focus', () => {
                        const rect = dot.getBoundingClientRect();
                        showTooltip(dot, rect.left + rect.width / 2, rect.top);
                    });
                    dot.addEventListener('blur', hideTooltip);
                });

                window.addEventListener('scroll', () => {
                    if (visible) hideTooltip();
                }, { passive: true });
            });
        </script>
    @endpush
</x-app-layout>
