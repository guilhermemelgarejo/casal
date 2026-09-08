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
    $isAsset = $cofrinho->isCustomAsset();
    $cardAccent = $cofrinho->color ?: ($cofrinho->isBitcoin() ? '#f59e0b' : '#0d9488');

    // Preparação dos dados para o Gráfico 1 (Evolução Global)
    $balSeries = $chartData['balanceSeries'] ?? [];
    $balCount = count($balSeries);
    $balMax = max(1.0, (float) collect($balSeries)->max('balance'), (float) ($target ?? 0.0));
    $balMin = 0.0;

    $svgW = 620;
    $svgH = 210;
    $padX = 36;
    $padY = 24;
    $innerW = $svgW - ($padX * 2);
    $innerH = $svgH - ($padY * 2);
    $balStepX = $balCount > 1 ? ($innerW / ($balCount - 1)) : $innerW;

    $toBalY = function (float $val) use ($balMin, $balMax, $padY, $innerH) {
        $range = max(1.0, $balMax - $balMin);
        return $padY + (($balMax - $val) / $range) * $innerH;
    };

    $balPoints = [];
    foreach ($balSeries as $i => $item) {
        $x = round($padX + ($balStepX * $i), 2);
        $y = round($toBalY((float) $item['balance']), 2);
        $balPoints[] = array_merge($item, ['x' => $x, 'y' => $y]);
    }

    $balPolyline = implode(' ', array_map(fn ($p) => "{$p['x']},{$p['y']}", $balPoints));
    $balBottomY = round($padY + $innerH, 2);
    $balArea = count($balPoints) > 0
        ? "{$balPoints[0]['x']},{$balBottomY} {$balPolyline} {$balPoints[$balCount - 1]['x']},{$balBottomY}"
        : '';

    $targetY = ($target !== null && $target > 0) ? round($toBalY((float) $target), 2) : null;

    // Preparação dos dados para o Gráfico 2 (Evolução dos Juros)
    $hasInterest = !empty($chartData['hasInterest']);
    $intSeries = $chartData['interestSeries'] ?? [];
    $intCount = count($intSeries);
    $intMax = 1.0;
    if ($hasInterest) {
        $intMax = max(1.0, (float) collect($intSeries)->max('cumulative'), (float) collect($intSeries)->max('monthly'));
    }
    $intStepX = $intCount > 1 ? ($innerW / ($intCount - 1)) : $innerW;
    $toIntY = function (float $val) use ($intMax, $padY, $innerH) {
        return $padY + (($intMax - $val) / max(1.0, $intMax)) * $innerH;
    };

    $intPoints = [];
    $barW = max(12, min(36, $intCount > 0 ? ($innerW / $intCount) * 0.45 : 20));
    foreach ($intSeries as $i => $item) {
        $x = round($padX + ($intStepX * $i), 2);
        $yCum = round($toIntY((float) $item['cumulative']), 2);
        $yMonth = round($toIntY((float) $item['monthly']), 2);
        $barH = max(0.0, $balBottomY - $yMonth);
        $intPoints[] = array_merge($item, [
            'x' => $x,
            'y_cum' => $yCum,
            'y_month' => $yMonth,
            'bar_h' => $barH,
        ]);
    }
    $intPolyline = implode(' ', array_map(fn ($p) => "{$p['x']},{$p['y_cum']}", $intPoints));
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="{{ route('cofrinhos.index') }}" class="text-decoration-none text-secondary">Cofrinhos</a></li>
                        <li class="breadcrumb-item active text-body" aria-current="page">{{ $cofrinho->name }}</li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="h4 mb-0 fw-bold" style="color: var(--dz-text-title);">{{ $cofrinho->name }}</h1>
                    @if(! $cofrinho->is_active)
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle">Inativo</span>
                    @elseif($cofrinho->isBitcoin())
                        <span class="badge rounded-pill text-bg-warning">₿ Bitcoin</span>
                    @elseif($isAsset)
                        <span class="badge rounded-pill text-bg-info">{{ $cofrinho->asset_code ?: $cofrinho->assetTypeLabel() }}</span>
                    @else
                        <span class="badge rounded-pill text-bg-primary">R$ Moeda</span>
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('cofrinhos.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                    ← Voltar
                </a>
                @if($isAsset)
                    <button type="button" class="btn btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalAssetAporte">
                        + Aporte no Ativo
                    </button>
                @endif
                <button type="button" class="btn btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalCofrinhoInterest">
                    💰 Lançar Juros
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalCofrinhoEdit">
                    ✏️ Editar
                </button>
            </div>
        </div>
    </x-slot>

    <div class="container-xxl py-4 px-3 px-lg-4 cofrinhos-page">
        @if (session('success'))
            <x-alert type="success" class="mb-4" :message="session('success')" />
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-4" :message="session('error')" />
        @endif

        <!-- TOP INDICADORES DA POSIÇÃO -->
        <section class="dz-kpi-grid mb-4">
            <!-- Patrimônio Atual -->
            <div class="dz-card dz-kpi-card" style="border-top: 3px solid {{ $cardAccent }};">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Patrimônio Atual</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                        🐷
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-primary dz-privacy-blur">
                        R$ {{ number_format($currentBalance, 2, ',', '.') }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        @if($isAsset)
                            <span>{{ rtrim(rtrim(number_format((float) $cofrinho->asset_quantity, 8, ',', '.'), '0'), ',') }} {{ $cofrinho->assetUnitLabel() }} acumulados</span>
                        @else
                            <span>Saldo total disponível</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Total Aportado -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Total Aportado</span>
                    <div class="dz-kpi-card__icon-box" style="background: rgba(14, 165, 233, 0.15); color: #0284c7;">
                        📥
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value dz-privacy-blur" style="color: var(--dz-text-title);">
                        R$ {{ number_format($totalInvested, 2, ',', '.') }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        @if($isAsset)
                            <span>Preço Médio: R$ {{ number_format((float) $cofrinho->asset_avg_price, 2, ',', '.') }}</span>
                        @else
                            <span>Capital aportado (principal)</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Rendimentos / Juros -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Rendimentos / Juros</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                        📈
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-success dz-privacy-blur">
                        +R$ {{ number_format($totalInterest, 2, ',', '.') }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span class="text-success fw-semibold">{{ number_format($profitPct, 2, ',', '.') }}%</span>
                        <span>de rentabilidade</span>
                    </div>
                </div>
            </div>

            <!-- Meta Financeira -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Meta Financeira</span>
                    <div class="dz-kpi-card__icon-box" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">
                        🎯
                    </div>
                </div>
                <div>
                    @if($target !== null && $target > 0)
                        <div class="dz-kpi-card__value dz-privacy-blur" style="color: var(--dz-text-title);">
                            R$ {{ number_format($target, 2, ',', '.') }}
                        </div>
                        <div class="dz-progress-bar">
                            <div class="dz-progress-bar__fill dz-progress-bar__fill--success" style="width: {{ $targetPct }}%;"></div>
                        </div>
                        <div class="dz-kpi-card__footer" style="margin-top: 0.5rem;">
                            <span>{{ number_format($targetPct, 1, ',', '.') }}% atingido</span>
                            @if($targetRemaining > 0)
                                <span class="dz-privacy-blur">Faltam R$ {{ number_format($targetRemaining, 2, ',', '.') }}</span>
                            @else
                                <span class="text-success fw-semibold">Meta alcançada! 🏆</span>
                            @endif
                        </div>
                    @else
                        <div class="dz-kpi-card__value text-secondary" style="font-size: 1.15rem;">
                            Livre
                        </div>
                        <div class="dz-kpi-card__footer">
                            <span>Sem meta definida</span>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <!-- SEÇÃO DE GRÁFICOS DE EVOLUÇÃO -->
        <section class="row g-4 mb-4">
            <!-- GRÁFICO 1: EVOLUÇÃO GLOBAL DO COFRINHO -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm cofrinhos-chart-card">
                    <div class="cofrinhos-chart-head">
                        <div class="cofrinhos-chart-title-wrap">
                            <div class="cofrinhos-chart-icon" style="background: color-mix(in srgb, {{ $cardAccent }} 14%, transparent); color: {{ $cardAccent }};">
                                📊
                            </div>
                            <div>
                                <h2 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Evolução Global do Cofrinho</h2>
                                <p class="small text-secondary mb-0">Crescimento do saldo acumulado ao longo do tempo</p>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="small text-secondary d-block">Saldo atual</span>
                            <strong class="text-primary dz-privacy-blur" style="font-size: 0.95rem;">R$ {{ number_format($currentBalance, 2, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="cofrinhos-chart-body">
                        <svg class="cofrinhos-chart-svg" viewBox="0 0 {{ $svgW }} {{ $svgH }}" role="img" aria-label="Gráfico de evolução global do cofrinho">
                            <defs>
                                <linearGradient id="balGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="{{ $cardAccent }}" stop-opacity="0.32" />
                                    <stop offset="100%" stop-color="{{ $cardAccent }}" stop-opacity="0.01" />
                                </linearGradient>
                            </defs>

                            <!-- Linhas de grade sutis -->
                            <line x1="{{ $padX }}" y1="{{ $padY }}" x2="{{ $svgW - $padX }}" y2="{{ $padY }}" class="cofrinhos-chart-grid-line" />
                            <line x1="{{ $padX }}" y1="{{ round($padY + ($innerH * 0.5), 2) }}" x2="{{ $svgW - $padX }}" y2="{{ round($padY + ($innerH * 0.5), 2) }}" class="cofrinhos-chart-grid-line" />
                            <line x1="{{ $padX }}" y1="{{ $balBottomY }}" x2="{{ $svgW - $padX }}" y2="{{ $balBottomY }}" class="cofrinhos-chart-grid-line" />

                            <!-- Linha da Meta (se houver e couber no gráfico) -->
                            @if($targetY !== null && $targetY >= $padY && $targetY <= $balBottomY)
                                <line x1="{{ $padX }}" y1="{{ $targetY }}" x2="{{ $svgW - $padX }}" y2="{{ $targetY }}" class="cofrinhos-chart-target-line" />
                                <text x="{{ $svgW - $padX - 4 }}" y="{{ $targetY - 5 }}" text-anchor="end" fill="#10b981" font-size="10.5" font-weight="700">Meta: R$ {{ number_format($target, 2, ',', '.') }}</text>
                            @endif

                            <!-- Área Gradiente -->
                            @if(!empty($balArea))
                                <polygon points="{{ $balArea }}" fill="url(#balGrad)" />
                            @endif

                            <!-- Linha Principal -->
                            @if(!empty($balPolyline))
                                <polyline class="cofrinhos-chart-line" points="{{ $balPolyline }}" stroke="{{ $cardAccent }}" />
                            @endif

                            <!-- Pontos Interativos com Tooltip formatado e Hitbox expandida -->
                            @foreach($balPoints as $pt)
                                @php
                                    $balTooltip = "<div class='text-start p-1'>"
                                        . "<div class='fw-bold mb-1' style='font-size: 0.85rem; color: #f8fafc; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 3px;'>" . e($pt['label']) . "</div>"
                                        . "<div class='d-flex justify-content-between gap-3 mb-1'><span>Saldo:</span><strong style='color: #38bdf8;'>R$ " . number_format((float) $pt['balance'], 2, ',', '.') . "</strong></div>"
                                        . ($target !== null && $target > 0 ? "<div class='d-flex justify-content-between gap-3 mb-1' style='font-size: 0.72rem; color: #94a3b8;'><span>Meta:</span><span style='color: #10b981;'>R$ " . number_format((float) $target, 2, ',', '.') . " (" . number_format(min(100.0, ((float) $pt['balance'] / $target) * 100), 1, ',', '.') . "%)</span></div>" : '')
                                        . "<div class='d-flex justify-content-between gap-3' style='font-size: 0.72rem; color: #94a3b8; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 3px;'><span>Fluxo mês:</span><span style='color: " . ($pt['net'] >= 0 ? '#10b981' : '#f87171') . "; font-weight: 600;'>" . ($pt['net'] >= 0 ? '+' : '') . "R$ " . number_format((float) $pt['net'], 2, ',', '.') . "</span></div>"
                                        . "</div>";
                                @endphp
                                <circle
                                    cx="{{ $pt['x'] }}"
                                    cy="{{ $pt['y'] }}"
                                    r="16"
                                    fill="transparent"
                                    class="cofrinhos-chart-hitbox"
                                    data-bs-toggle="tooltip"
                                    data-bs-html="true"
                                    data-bs-placement="top"
                                    data-bs-custom-class="dz-chart-tooltip"
                                    data-bs-title="{{ $balTooltip }}"
                                    tabindex="0"
                                    aria-label="{{ $pt['label'] }}: Saldo R$ {{ number_format((float) $pt['balance'], 2, ',', '.') }}"
                                ></circle>
                                <circle
                                    cx="{{ $pt['x'] }}"
                                    cy="{{ $pt['y'] }}"
                                    r="4.5"
                                    fill="{{ $cardAccent }}"
                                    stroke="#ffffff"
                                    stroke-width="2"
                                    class="cofrinhos-chart-dot"
                                    style="pointer-events: none;"
                                ></circle>
                            @endforeach
                        </svg>

                        <!-- Eixo X dos Meses -->
                        <div class="cofrinhos-chart-labels">
                            @foreach($balPoints as $pt)
                                <span style="flex: 1; text-align: center;">{{ $pt['label'] }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- GRÁFICO 2: EVOLUÇÃO DOS JUROS -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm cofrinhos-chart-card">
                    <div class="cofrinhos-chart-head">
                        <div class="cofrinhos-chart-title-wrap">
                            <div class="cofrinhos-chart-icon" style="background: rgba(16, 185, 129, 0.15); color: #059669;">
                                💰
                            </div>
                            <div>
                                <h2 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Evolução dos Juros e Rendimentos</h2>
                                <p class="small text-secondary mb-0">Rendimentos creditados mês a mês e acumulado</p>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="small text-secondary d-block">Total de juros</span>
                            <strong class="text-success dz-privacy-blur" style="font-size: 0.95rem;">+R$ {{ number_format($totalInterest, 2, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="cofrinhos-chart-body">
                        @if($hasInterest && $totalInterest > 0.0001)
                            <svg class="cofrinhos-chart-svg" viewBox="0 0 {{ $svgW }} {{ $svgH }}" role="img" aria-label="Gráfico de evolução dos juros">
                                <defs>
                                    <linearGradient id="intBarGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.9" />
                                        <stop offset="100%" stop-color="#059669" stop-opacity="0.6" />
                                    </linearGradient>
                                </defs>

                                <!-- Linhas de grade sutis -->
                                <line x1="{{ $padX }}" y1="{{ $padY }}" x2="{{ $svgW - $padX }}" y2="{{ $padY }}" class="cofrinhos-chart-grid-line" />
                                <line x1="{{ $padX }}" y1="{{ round($padY + ($innerH * 0.5), 2) }}" x2="{{ $svgW - $padX }}" y2="{{ round($padY + ($innerH * 0.5), 2) }}" class="cofrinhos-chart-grid-line" />
                                <line x1="{{ $padX }}" y1="{{ $balBottomY }}" x2="{{ $svgW - $padX }}" y2="{{ $balBottomY }}" class="cofrinhos-chart-grid-line" />

                                <!-- Barras Mensais com Tooltip formatado -->
                                @foreach($intPoints as $pt)
                                    @if($pt['bar_h'] > 0.5)
                                        @php
                                            $intBarTooltip = "<div class='text-start p-1'>"
                                                . "<div class='fw-bold mb-1' style='font-size: 0.85rem; color: #f8fafc; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 3px;'>" . e($pt['label']) . "</div>"
                                                . "<div class='d-flex justify-content-between gap-3 mb-1'><span>Juros no mês:</span><strong style='color: #10b981;'>" . ($pt['monthly'] > 0 ? '+R$ ' . number_format((float) $pt['monthly'], 2, ',', '.') : 'R$ 0,00') . "</strong></div>"
                                                . "<div class='d-flex justify-content-between gap-3' style='font-size: 0.72rem; color: #94a3b8; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 3px;'><span>Total acumulado:</span><span style='color: #38bdf8; font-weight: 600;'>+R$ " . number_format((float) $pt['cumulative'], 2, ',', '.') . "</span></div>"
                                                . "</div>";
                                        @endphp
                                        <rect
                                            x="{{ $pt['x'] - ($barW / 2) }}"
                                            y="{{ $pt['y_month'] }}"
                                            width="{{ $barW }}"
                                            height="{{ $pt['bar_h'] }}"
                                            rx="3"
                                            ry="3"
                                            fill="url(#intBarGrad)"
                                            class="cofrinhos-chart-bar"
                                            data-bs-toggle="tooltip"
                                            data-bs-html="true"
                                            data-bs-placement="top"
                                            data-bs-custom-class="dz-chart-tooltip"
                                            data-bs-title="{{ $intBarTooltip }}"
                                            tabindex="0"
                                            aria-label="{{ $pt['label'] }}: Juros no mês R$ {{ number_format((float) $pt['monthly'], 2, ',', '.') }}"
                                        ></rect>
                                    @endif
                                @endforeach

                                <!-- Linha de Juros Acumulados -->
                                @if(!empty($intPolyline))
                                    <polyline class="cofrinhos-chart-line" points="{{ $intPolyline }}" stroke="#059669" stroke-width="2.5" />
                                @endif

                                <!-- Pontos de Juros Acumulados com Tooltip e Hitbox expandida -->
                                @foreach($intPoints as $pt)
                                    @php
                                        $intCumTooltip = "<div class='text-start p-1'>"
                                            . "<div class='fw-bold mb-1' style='font-size: 0.85rem; color: #f8fafc; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 3px;'>" . e($pt['label']) . "</div>"
                                            . "<div class='d-flex justify-content-between gap-3 mb-1'><span>Total acumulado:</span><strong style='color: #38bdf8;'>+R$ " . number_format((float) $pt['cumulative'], 2, ',', '.') . "</strong></div>"
                                            . "<div class='d-flex justify-content-between gap-3' style='font-size: 0.72rem; color: #94a3b8; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 3px;'><span>Juros no mês:</span><span style='color: #10b981; font-weight: 600;'>" . ($pt['monthly'] > 0 ? '+R$ ' . number_format((float) $pt['monthly'], 2, ',', '.') : 'R$ 0,00') . "</span></div>"
                                            . "</div>";
                                    @endphp
                                    <circle
                                        cx="{{ $pt['x'] }}"
                                        cy="{{ $pt['y_cum'] }}"
                                        r="16"
                                        fill="transparent"
                                        class="cofrinhos-chart-hitbox"
                                        data-bs-toggle="tooltip"
                                        data-bs-html="true"
                                        data-bs-placement="top"
                                        data-bs-custom-class="dz-chart-tooltip"
                                        data-bs-title="{{ $intCumTooltip }}"
                                        tabindex="0"
                                        aria-label="{{ $pt['label'] }}: Acumulado R$ {{ number_format((float) $pt['cumulative'], 2, ',', '.') }}"
                                    ></circle>
                                    <circle
                                        cx="{{ $pt['x'] }}"
                                        cy="{{ $pt['y_cum'] }}"
                                        r="4"
                                        fill="#059669"
                                        stroke="#ffffff"
                                        stroke-width="2"
                                        class="cofrinhos-chart-dot"
                                        style="pointer-events: none;"
                                    ></circle>
                                @endforeach
                            </svg>

                            <!-- Eixo X dos Meses -->
                            <div class="cofrinhos-chart-labels">
                                @foreach($intPoints as $pt)
                                    <span style="flex: 1; text-align: center;">{{ $pt['label'] }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="d-flex flex-column align-items-center justify-content-center text-center p-4 my-auto" style="min-height: 180px;">
                                <div class="fs-2 mb-2 opacity-50">✨</div>
                                <h3 class="h6 fw-bold mb-1" style="color: var(--dz-text-title);">Nenhum rendimento registrado ainda</h3>
                                <p class="small text-secondary mb-3" style="max-width: 320px;">Lance os juros da poupança, CDI ou dividendos para acompanhar a curva de rentabilidade deste cofrinho.</p>
                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalCofrinhoInterest">
                                    + Lançar primeiro rendimento
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <!-- SEÇÃO: HISTÓRICO DE MOVIMENTAÇÕES -->
        <section class="card border-0 shadow-sm cofrinhos-movements-card">
            <div class="card-header border-0 bg-transparent p-4 pb-0">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        <h2 class="h6 mb-1 fw-bold" style="color: var(--dz-text-title);">Histórico de Movimentações</h2>
                        <p class="small text-secondary mb-0">{{ $movements->total() }} registro(s) em {{ $periodLabelDisplay }}</p>
                    </div>

                    <!-- Filtro por Período -->
                    <form action="{{ route('cofrinhos.show', $cofrinho) }}" method="GET" class="cofrinhos-filter-shell d-flex align-items-center gap-2">
                        <div class="flex-grow-1">
                            <input
                                id="cofrinho-show-period"
                                type="text"
                                name="period"
                                value="{{ $period ?? '' }}"
                                class="form-control form-control-sm rounded-pill"
                                placeholder="Filtrar por mês..."
                                data-duozen-flatpickr="month"
                                autocomplete="off"
                                style="min-width: 140px; max-width: 180px;"
                            >
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Filtrar</button>
                        @if($period)
                            <a href="{{ route('cofrinhos.show', $cofrinho) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Limpar</a>
                        @endif
                    </form>
                </div>
            </div>

            <div class="card-body p-4">
                <!-- Resumo do Período Filtrado -->
                @if($period)
                    <div class="row g-3 mb-3 p-3 rounded-3 bg-body-tertiary">
                        <div class="col-sm-4">
                            <span class="small text-secondary d-block">Aportes no mês</span>
                            <strong class="text-success dz-privacy-blur">R$ {{ number_format($totalAportes, 2, ',', '.') }}</strong>
                        </div>
                        <div class="col-sm-4">
                            <span class="small text-secondary d-block">Retiradas no mês</span>
                            <strong class="text-danger dz-privacy-blur">R$ {{ number_format($totalRetiradas, 2, ',', '.') }}</strong>
                        </div>
                        <div class="col-sm-4">
                            <span class="small text-secondary d-block">Saldo do mês</span>
                            <strong class="{{ $saldoPeriodo < 0 ? 'text-danger' : 'text-success' }} dz-privacy-blur">
                                {{ $saldoPeriodo < 0 ? '-' : '+' }}R$ {{ number_format(abs($saldoPeriodo), 2, ',', '.') }}
                            </strong>
                        </div>
                    </div>
                @endif

                <div class="table-responsive cofrinhos-table-wrap">
                    <table class="table table-sm align-middle mb-0 cofrinhos-movements-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                @if($isAsset)
                                    <th class="text-end">Qtd ({{ $cofrinho->assetUnitLabel() }})</th>
                                    <th class="text-end">Cotação no Aporte</th>
                                    <th class="text-end">PM Resultante</th>
                                @endif
                                <th>Conta</th>
                                <th>Registrado por</th>
                                <th class="text-end">Valor (R$)</th>
                                <th class="text-end pe-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                                @php
                                    $isOut = ($movement['kind'] ?? '') === 'retirada';
                                    $amount = (float) ($movement['amount'] ?? 0);
                                    $assetQty = $movement['asset_quantity'] ?? null;
                                    $unitPrice = $movement['asset_unit_price'] ?? null;
                                    $resultingPm = $movement['asset_resulting_avg_price'] ?? null;
                                    $isInterest = ($movement['source'] ?? '') === \App\Models\FinancialProjectEntry::TYPE_INTEREST;
                                @endphp
                                <tr>
                                    <td class="text-nowrap">{{ optional($movement['date'])->format('d/m/Y') }}</td>
                                    <td>
                                        @if(($movement['kind'] ?? '') === 'aporte')
                                            <span class="badge rounded-pill text-bg-success-subtle text-success-emphasis border border-success-subtle">Aporte</span>
                                        @elseif(($movement['kind'] ?? '') === 'retirada')
                                            <span class="badge rounded-pill text-bg-danger-subtle text-danger-emphasis border border-danger-subtle">Retirada</span>
                                        @else
                                            <span class="badge rounded-pill text-bg-info-subtle text-info-emphasis border border-info-subtle">Juros / Rend.</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-medium">{{ $movement['description'] }}</span>
                                        @if(!empty($movement['note']))
                                            <span class="small text-secondary d-block">{{ $movement['note'] }}</span>
                                        @endif
                                    </td>
                                    @if($isAsset)
                                        <td class="text-end text-nowrap">
                                            @if($assetQty !== null)
                                                <span class="fw-semibold {{ $isOut ? 'text-danger' : 'text-success' }}">
                                                    {{ $isOut ? '-' : '+' }}{{ rtrim(rtrim(number_format((float) $assetQty, 8, ',', '.'), '0'), ',') }}
                                                </span>
                                            @else
                                                <span class="text-secondary">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            @if($unitPrice !== null)
                                                <span>R$ {{ number_format((float) $unitPrice, 2, ',', '.') }}</span>
                                            @else
                                                <span class="text-secondary">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            @if($resultingPm !== null)
                                                <span class="fw-semibold text-primary">R$ {{ number_format((float) $resultingPm, 2, ',', '.') }}</span>
                                            @else
                                                <span class="text-secondary">—</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td>{{ $movement['account_name'] ?? '-' }}</td>
                                    <td>{{ $movement['user_name'] ?? '-' }}</td>
                                    <td class="text-end text-nowrap">
                                        <span class="fw-semibold {{ $isOut ? 'text-danger' : 'text-success' }}">
                                            {{ $isOut ? '-' : '+' }}R$ {{ number_format(abs($amount), 2, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap pe-3">
                                        @if($isInterest && !empty($movement['id']))
                                            <form
                                                action="{{ route('cofrinhos.interest.destroy', $movement['id']) }}"
                                                method="POST"
                                                class="d-inline"
                                                data-confirm-title="Excluir rendimento"
                                                data-confirm="Excluir este lançamento de juros/rendimento? O saldo do cofrinho será recalculado."
                                                data-confirm-accept="Sim, excluir"
                                                data-confirm-cancel="Cancelar"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="btn btn-link text-danger btn-sm p-0"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="Excluir este rendimento"
                                                    aria-label="Excluir rendimento"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-secondary small">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="cofrinhos-empty-row">
                                    <td colspan="{{ $isAsset ? 10 : 7 }}">
                                        <div class="cofrinhos-empty-state text-center py-5">
                                            <div class="cofrinhos-empty-state__icon mx-auto mb-3" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8c-3.866 0-7 1.343-7 3s3.134 3 7 3 7-1.343 7-3-3.134-3-7-3zM5 11v4c0 1.657 3.134 3 7 3s7-1.343 7-3v-4" /></svg>
                                            </div>
                                            <h3 class="h6 mb-1">Nenhuma movimentação encontrada</h3>
                                            <p class="small text-secondary mb-0">Aportes, retiradas e lançamentos para este cofrinho aparecerão aqui.</p>
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

    {{-- MODAL LANÇAR JUROS --}}
    <div class="modal fade" id="modalCofrinhoInterest" tabindex="-1" aria-labelledby="modalCofrinhoInterestLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header cofrinhos-juros-modal-head border-0">
                    <h2 class="modal-title h5 mb-0" id="modalCofrinhoInterestLabel">Lançar juros / rendimento</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post" action="{{ route('cofrinhos.interest.store', $cofrinho) }}">
                    @csrf
                    <div class="modal-body vstack gap-3">
                        <p class="small text-secondary mb-0">
                            Registre o rendimento creditado no cofrinho <strong>{{ $cofrinho->name }}</strong>. Esse valor aumenta o saldo sem debitar de nenhuma conta corrente.
                        </p>
                        <div>
                            <x-input-label for="interest_amount" value="Valor do rendimento (R$)" />
                            <x-text-input
                                id="interest_amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="mt-1 rounded-3"
                                required
                                placeholder="0,00"
                            />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="interest_date" value="Data do crédito" />
                            <x-text-input
                                id="interest_date"
                                name="date"
                                type="date"
                                class="mt-1 rounded-3"
                                required
                                value="{{ now()->toDateString() }}"
                            />
                            <x-input-error :messages="$errors->get('date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="interest_note" value="Observação (opcional)" />
                            <x-text-input
                                id="interest_note"
                                name="note"
                                type="text"
                                class="mt-1 rounded-3"
                                placeholder="Ex: Rendimento CDI, Poupança, Dividendos"
                            />
                            <x-input-error :messages="$errors->get('note')" class="mt-2" />
                        </div>
                    </div>
                    <div class="modal-footer border-secondary-subtle">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <x-primary-button class="rounded-pill px-4">Salvar rendimento</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL EDITAR COFRINHO --}}
    <div class="modal fade" id="modalCofrinhoEdit" tabindex="-1" aria-labelledby="modalCofrinhoEditLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header cofrinhos-juros-modal-head border-0">
                    <h2 class="modal-title h5 mb-0" id="modalCofrinhoEditLabel">Editar cofrinho</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post" action="{{ route('cofrinhos.update', $cofrinho) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_cofrinho_form" value="edit">
                    <input type="hidden" name="cofrinho_id" value="{{ $cofrinho->id }}">
                    <div class="modal-body vstack gap-3">
                        <div>
                            <x-input-label for="fp-edit-name" value="Nome do cofrinho" />
                            <x-text-input id="fp-edit-name" name="name" class="mt-1 rounded-3" value="{{ $cofrinho->name }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="fp-edit-target" value="Meta financeira (R$, opcional)" />
                            <x-text-input id="fp-edit-target" name="target_amount" type="text" class="mt-1 rounded-3" value="{{ $cofrinho->target_amount !== null ? number_format((float) $cofrinho->target_amount, 2, ',', '.') : '' }}" placeholder="0,00" />
                            <x-input-error :messages="$errors->get('target_amount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fp-edit-color" value="Cor de identificação" />
                            <input
                                type="color"
                                id="fp-edit-color"
                                name="color"
                                value="{{ $cofrinho->color ?: '#0d9488' }}"
                                class="form-control form-control-color w-100 mt-1 rounded-3"
                            >
                            <x-input-error :messages="$errors->get('color')" class="mt-2" />
                        </div>

                        <div class="form-check form-switch pt-1">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="fp-edit-is-active" value="1" @checked($cofrinho->is_active)>
                            <label class="form-check-label fw-semibold" for="fp-edit-is-active">Cofrinho ativo</label>
                            <div class="form-text mt-0">Cofrinhos desativados não aparecem em novos lançamentos e aportes.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary-subtle">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <x-primary-button class="rounded-pill px-4">Salvar alterações</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL APORTE EM ATIVO (SE FOR ATIVO CUSTOMIZADO) --}}
    @if($isAsset)
        <div class="modal fade" id="modalAssetAporte" tabindex="-1" aria-labelledby="modalAssetAporteLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header cofrinhos-juros-modal-head border-0">
                        <h2 class="modal-title h5 mb-0" id="modalAssetAporteLabel">
                            Aporte em {{ $cofrinho->name }} ({{ $cofrinho->asset_code ?: $cofrinho->assetTypeLabel() }})
                        </h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form method="post" action="{{ route('cofrinhos.asset-aporte.store', $cofrinho) }}">
                        @csrf
                        <div class="modal-body vstack gap-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <x-input-label for="modal-aporte-amount" value="Valor total (R$)" />
                                    <x-text-input id="modal-aporte-amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 rounded-3" placeholder="0,00" required />
                                </div>
                                <div class="col-6">
                                    <x-input-label for="modal-aporte-price" value="Preço unitário pago (R$)" />
                                    <x-text-input id="modal-aporte-price" name="price" type="number" step="0.01" min="0.01" class="mt-1 rounded-3" value="{{ $quotePrice !== null ? number_format((float) $quotePrice, 2, '.', '') : '' }}" placeholder="0,00" />
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <x-input-label for="modal-aporte-quantity" value="Quantidade ({{ $cofrinho->assetUnitLabel() }})" />
                                    <x-text-input id="modal-aporte-quantity" name="quantity" type="number" step="0.00000001" min="0.00000001" class="mt-1 rounded-3" placeholder="0.00000000" />
                                </div>
                                <div class="col-6">
                                    <x-input-label for="modal-aporte-date" value="Data do aporte" />
                                    <x-text-input id="modal-aporte-date" name="date" type="date" class="mt-1 rounded-3" value="{{ now()->toDateString() }}" required />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="modal-aporte-account" value="Debitar de conta bancária (opcional)" />
                                <select id="modal-aporte-account" name="account_id" class="form-select mt-1 rounded-3">
                                    <option value="">Nenhuma conta (aporte externo / custódia)</option>
                                    @foreach($regularAccounts as $acc)
                                        <option value="{{ $acc->id }}">
                                            {{ $acc->name }} (Saldo: R$ {{ number_format((float) $acc->balance, 2, ',', '.') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="modal-aporte-note" value="Observação (opcional)" />
                                <x-text-input id="modal-aporte-note" name="note" type="text" class="mt-1 rounded-3" placeholder="Ex: Compra corretora Binance / B3" />
                            </div>
                        </div>
                        <div class="modal-footer border-secondary-subtle">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                            <x-primary-button class="rounded-pill px-4">Salvar aporte</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const bs = typeof bootstrap !== 'undefined' ? bootstrap : window.bootstrap;
                if (bs?.Tooltip) {
                    document.querySelectorAll('.cofrinhos-chart-svg [data-bs-toggle="tooltip"]').forEach(function (el) {
                        bs.Tooltip.getOrCreateInstance(el, {
                            container: 'body',
                            html: true,
                            trigger: 'hover focus'
                        });
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>
