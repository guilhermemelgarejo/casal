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
    <x-slot name="header">
        <div class="reports-head-wrap">
            <div class="reports-title-block">
                <p class="reports-title-kicker">visão financeira</p>
                <h2 class="h4 mb-0 reports-title">Relatórios</h2>
                <p class="small text-secondary mb-0 mt-1">{{ ucfirst($periodLabel) }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-4 reports-page">
        <div class="container-xxl px-3 px-lg-4 d-grid gap-4">
            <section class="reports-hero card border-0 shadow-sm">
                <div class="card-body">
                    <div class="reports-hero-top">
                        <div>
                            <h3 class="h5 mb-1 reports-hero-title">Resumo executivo do período</h3>
                            <p class="small text-secondary mb-0">Painel consolidado com os mesmos critérios de cálculo para manter consistência entre telas.</p>
                        </div>
                        @if($selectedAccount)
                            <span class="reports-chip">Conta filtrada: {{ $selectedAccount->name }}</span>
                        @endif
                    </div>
                    <form action="{{ route('reports.index') }}" method="GET" class="reports-filter-shell">
                        <div class="reports-filter-grid">
                            <div class="reports-filter-field">
                                <label class="reports-filter-label" for="reports-period">Período</label>
                                <input
                                    id="reports-period"
                                    type="text"
                                    name="period"
                                    value="{{ $period }}"
                                    class="form-control form-control-sm"
                                    data-duozen-flatpickr="month"
                                    autocomplete="off"
                                    aria-label="Mês de referência"
                                >
                            </div>
                            <div class="reports-filter-field">
                                <label class="reports-filter-label" for="reports-account">Conta</label>
                                <select id="reports-account" name="account_id" class="form-select form-select-sm" aria-label="Conta">
                                    <option value="" {{ $filterAccountId === null ? 'selected' : '' }}>Todas as contas</option>
                                    @foreach($accountsForFilter as $account)
                                        <option value="{{ $account->id }}" {{ (int) ($filterAccountId ?? 0) === (int) $account->id ? 'selected' : '' }}>
                                            {{ $account->name }}{{ $account->isCreditCard() ? ' (cartão)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary px-3">Aplicar</button>
                            @if(request()->has('period') || request()->has('account_id'))
                                <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary px-3">Limpar</a>
                            @endif
                        </div>
                    </form>
                    <div class="reports-kpi-grid">
                        <article class="reports-kpi-card reports-kpi-card--income">
                            <p class="reports-kpi-label">Receitas</p>
                            <p class="reports-kpi-value text-success">{{ $money($executiveKpis['total_income']) }}</p>
                        </article>
                        <article class="reports-kpi-card reports-kpi-card--expense">
                            <p class="reports-kpi-label">Despesas</p>
                            <p class="reports-kpi-value text-danger">{{ $money($executiveKpis['total_expense']) }}</p>
                        </article>
                        <article class="reports-kpi-card reports-kpi-card--result">
                            <p class="reports-kpi-label">Resultado</p>
                            <p class="reports-kpi-value {{ $executiveKpis['net_result'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($executiveKpis['net_result']) }}</p>
                        </article>
                        <article class="reports-kpi-card">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <p class="reports-kpi-label mb-0">Pressão de gasto</p>
                                <span class="reports-kpi-badge {{ $pressurePct >= 80 ? 'is-danger' : ($pressurePct >= 60 ? 'is-warning' : 'is-ok') }}">
                                    {{ $pct($pressurePct) }}
                                </span>
                            </div>
                            <p class="small text-secondary mb-2">Renda base: {{ $money($executiveKpis['planned_income']) }}</p>
                            <div class="progress reports-mini-progress" role="progressbar" aria-label="Pressão de gasto">
                                <div class="progress-bar {{ $pressurePct >= 80 ? 'bg-danger' : ($pressurePct >= 60 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $pressureBar }}%"></div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="reports-section card border-0 shadow-sm">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div>
                            <h3 class="h6 mb-1">Tendências dos últimos 6 meses</h3>
                            <p class="small text-secondary mb-0">Leitura rápida de direção dos indicadores principais.</p>
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

            <section class="reports-section card border-0 shadow-sm">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div>
                            <h3 class="h6 mb-1">Orçamento por Categoria</h3>
                            <p class="small text-secondary mb-0">Planejado x realizado sem transferências internas e sem pagamento de fatura.</p>
                        </div>
                        <span class="reports-chip">{{ $num($budgetRows->count()) }} categorias com dados</span>
                    </div>

                    <div class="reports-stat-grid reports-stat-grid--three mb-3">
                        <article class="reports-stat-card">
                            <span class="small text-secondary d-block">Planejado</span>
                            <strong>{{ $money($budgetTotal) }}</strong>
                        </article>
                        <article class="reports-stat-card">
                            <span class="small text-secondary d-block">Realizado</span>
                            <strong>{{ $money($budgetSpentTotal) }}</strong>
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
                                    <th class="text-end">Planejado</th>
                                    <th class="text-end">Realizado</th>
                                    <th class="text-end">Variação</th>
                                    <th class="text-end">Execução</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($budgetRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="text-end">{{ $money($row['budget']) }}</td>
                                        <td class="text-end">{{ $money($row['spent']) }}</td>
                                        <td class="text-end">
                                            <span class="fw-semibold {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($row['variance']) }}</span>
                                        </td>
                                        <td class="text-end">
                                            @if($row['execution_pct'] !== null)
                                                <span class="reports-kpi-badge {{ $row['execution_pct'] > 100 ? 'is-danger' : ($row['execution_pct'] >= 80 ? 'is-warning' : 'is-ok') }}">{{ $pct($row['execution_pct']) }}</span>
                                            @else
                                                <span class="text-secondary">Sem meta</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-secondary">Sem dados de orçamento para este período.</td></tr>
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

            <section class="reports-section card border-0 shadow-sm">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div>
                            <h3 class="h6 mb-1">Cartões e Faturas</h3>
                            <p class="small text-secondary mb-0">Utilização de limite, saldos em aberto e vencimentos.</p>
                        </div>
                    </div>
                    <div class="reports-stat-grid reports-stat-grid--three mb-3">
                        <article class="reports-stat-card"><span class="small text-secondary d-block">Limite total</span><strong>{{ $money($totalLimit) }}</strong></article>
                        <article class="reports-stat-card"><span class="small text-secondary d-block">Em aberto</span><strong>{{ $money($totalOutstanding) }}</strong></article>
                        <article class="reports-stat-card"><span class="small text-secondary d-block">Utilização consolidada</span><strong>{{ $pct($overallCardUtilizationPct) }}</strong></article>
                    </div>
                    <div class="table-responsive mb-3 reports-table-wrap">
                        <table class="table table-sm align-middle mb-0 reports-table">
                            <thead>
                                <tr>
                                    <th>Cartão</th>
                                    <th class="text-end">Limite</th>
                                    <th class="text-end">Em aberto</th>
                                    <th class="text-end">Utilização</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cardRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="text-end">{{ $row['limit_total'] !== null ? $money($row['limit_total']) : 'Sem limite' }}</td>
                                        <td class="text-end">{{ $money($row['outstanding']) }}</td>
                                        <td class="text-end">
                                            @if($row['utilization_pct'] !== null)
                                                <span class="reports-kpi-badge {{ $row['utilization_pct'] >= 80 ? 'is-danger' : ($row['utilization_pct'] >= 60 ? 'is-warning' : 'is-ok') }}">{{ $pct($row['utilization_pct']) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-secondary">Nenhum cartão encontrado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive reports-table-wrap">
                        <table class="table table-sm align-middle mb-0 reports-table">
                            <thead>
                                <tr>
                                    <th>Fatura</th>
                                    <th class="text-end">Em aberto</th>
                                    <th class="text-end">Vencimento</th>
                                    <th class="text-end">Dias p/ vencer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($openStatements as $statement)
                                    <tr>
                                        <td>{{ $statement['account_name'] }} - {{ $statement['reference_label'] }}</td>
                                        <td class="text-end">{{ $money($statement['remaining']) }}</td>
                                        <td class="text-end">{{ $statement['due_label'] ?? '-' }}</td>
                                        <td class="text-end">
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
                                    <tr><td colspan="4" class="text-secondary">Sem faturas em aberto.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="reports-section card border-0 shadow-sm">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div>
                            <h3 class="h6 mb-1">Metas (Cofrinhos)</h3>
                            <p class="small text-secondary mb-0">Progresso acumulado e aporte líquido no período.</p>
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
                                    <th class="text-end">Acumulado</th>
                                    <th class="text-end">Meta</th>
                                    <th class="text-end">Progresso</th>
                                    <th class="text-end">Aporte líquido mês</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($projectRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="text-end">{{ $money($row['saved']) }}</td>
                                        <td class="text-end">{{ $row['target'] !== null ? $money($row['target']) : 'Sem meta' }}</td>
                                        <td class="text-end">
                                            @if($row['progress_pct'] !== null)
                                                <span class="reports-kpi-badge {{ $row['progress_pct'] >= 100 ? 'is-ok' : ($row['progress_pct'] >= 60 ? 'is-warning' : '') }}">{{ $pct($row['progress_pct']) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $money($row['monthly_net']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-secondary">Nenhum cofrinho cadastrado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="reports-section card border-0 shadow-sm">
                <div class="card-body reports-section-body">
                    <div class="reports-section-head">
                        <div>
                            <h3 class="h6 mb-1">Recorrências</h3>
                            <p class="small text-secondary mb-0">Previsto x realizado para os modelos ativos no mês.</p>
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
                                    <th class="text-end">Valor</th>
                                    <th class="text-end">Dia sugerido</th>
                                    <th class="text-end">Conta</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingRecurringRows as $row)
                                    <tr>
                                        <td>{{ $row['description'] }}</td>
                                        <td class="text-end">{{ $money($row['amount']) }}</td>
                                        <td class="text-end">{{ $row['day_of_month'] }}</td>
                                        <td class="text-end">{{ $row['account_name'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-secondary">Sem pendências no período selecionado.</td></tr>
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
                tooltip.innerHTML = '<div class="reports-custom-tooltip__label"></div><div class="reports-custom-tooltip__value"></div><div class="reports-custom-tooltip__delta"></div>';
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
