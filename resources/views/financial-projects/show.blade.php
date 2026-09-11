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

    // Preparação dos dados para o Gráfico 1 (Evolução)
    $balSeries = $chartData['balanceSeries'] ?? [];
    $balCount = count($balSeries);

    $svgW = 620;
    $svgH = 210;
    $padX = 36;
    $padY = 24;
    $innerW = $svgW - ($padX * 2);
    $innerH = $svgH - ($padY * 2);
    $balStepX = $balCount > 1 ? ($innerW / ($balCount - 1)) : $innerW;
    $balBottomY = round($padY + $innerH, 2);

    if ($isAsset) {
        $balMax = max(1.0, (float) collect($balSeries)->max('balance'), (float) collect($balSeries)->max('invested'), (float) ($target ?? 0.0));
    } else {
        $balMax = max(1.0, (float) collect($balSeries)->max('balance'), (float) ($target ?? 0.0));
    }
    $balMin = 0.0;

    $toBalY = function (float $val) use ($balMin, $balMax, $padY, $innerH) {
        $range = max(1.0, $balMax - $balMin);
        return $padY + (($balMax - $val) / $range) * $innerH;
    };

    $balPoints = [];
    foreach ($balSeries as $i => $item) {
        $x = round($padX + ($balStepX * $i), 2);
        $y = round($toBalY((float) $item['balance']), 2);
        $yInvested = round($toBalY((float) ($item['invested'] ?? $item['balance'])), 2);
        $balPoints[] = array_merge($item, [
            'x' => $x,
            'y' => $y,
            'y_invested' => $yInvested,
        ]);
    }

    $balPolyline = implode(' ', array_map(fn ($p) => "{$p['x']},{$p['y']}", $balPoints));
    $investedPolyline = implode(' ', array_map(fn ($p) => "{$p['x']},{$p['y_invested']}", $balPoints));
    $balArea = count($balPoints) > 0
        ? "{$balPoints[0]['x']},{$balBottomY} {$balPolyline} {$balPoints[$balCount - 1]['x']},{$balBottomY}"
        : '';

    $targetY = ($target !== null && $target > 0) ? round($toBalY((float) $target), 2) : null;

    // Preparação dos dados para o Gráfico 2
    if ($isAsset) {
        // Gráfico 2: Acumulação do Ativo (Quantidade)
        $totalAssetQty = (float) ($cofrinho->asset_quantity ?? 0);
        $hasAssetData = $totalAssetQty > 0.00000001 || (float) collect($balSeries)->max('qty_cumulative') > 0.00000001;
        $qtyMax = max(0.00000001, (float) collect($balSeries)->max('qty_cumulative'));
        $qtyCeil = $qtyMax * 1.15;
        $toQtyY = function (float $val) use ($qtyCeil, $padY, $innerH) {
            return $padY + (($qtyCeil - $val) / max(0.00000001, $qtyCeil)) * $innerH;
        };

        $qtyMonthMax = max(0.00000001, (float) collect($balSeries)->max('qty_monthly'));
        $barW = max(12, min(36, $balCount > 0 ? ($innerW / $balCount) * 0.45 : 20));

        $assetPoints = [];
        foreach ($balSeries as $i => $item) {
            $x = round($padX + ($balStepX * $i), 2);
            $yCum = round($toQtyY((float) ($item['qty_cumulative'] ?? 0)), 2);
            $qMonth = (float) ($item['qty_monthly'] ?? 0);
            $barH = ($qtyMonthMax > 0.00000001 && $qMonth > 0)
                ? max(3.0, round(($qMonth / $qtyMonthMax) * ($innerH * 0.55), 2))
                : 0.0;
            $yBar = round($balBottomY - $barH, 2);
            $assetPoints[] = array_merge($item, [
                'x' => $x,
                'y_cum' => $yCum,
                'y_bar' => $yBar,
                'bar_h' => $barH,
            ]);
        }
        $assetPolyline = implode(' ', array_map(fn ($p) => "{$p['x']},{$p['y_cum']}", $assetPoints));
    } else {
        // Gráfico 2: Evolução dos Juros (Fiat)
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
    }
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
                    <button type="button" class="btn btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalAssetVenda">
                        − Venda / Resgate
                    </button>
                @endif
                @if(! $isAsset)
                    <button type="button" class="btn btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalCofrinhoInterest">
                        💰 Lançar Juros
                    </button>
                @endif
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
        @if ($errors->any() && old('_cofrinho_form') === 'edit')
            <x-alert type="danger" class="mb-4" message="Não foi possível atualizar o cofrinho. Verifique os campos com erro." />
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
                    <div class="dz-kpi-card__value text-primary dz-privacy-blur" id="show-current-balance">
                        R$ {{ number_format($currentBalance, 2, ',', '.') }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        @if($isAsset)
                            <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-1">
                                <span class="fw-medium">{{ rtrim(rtrim(number_format((float) $cofrinho->asset_quantity, 8, ',', '.'), '0'), ',') }} {{ $cofrinho->assetUnitLabel() }}</span>
                                @if($quotePrice)
                                    <div class="d-inline-flex align-items-center gap-1" title="Cotação ao vivo {{ $currentQuote?->source ? '(Fonte: ' . $currentQuote->source . ')' : '' }}">
                                        <span class="text-secondary">Cotação:</span>
                                        <strong class="text-body" id="show-card-quote-price">R$ {{ number_format($quotePrice, 2, ',', '.') }}</strong>
                                        @if($currentQuote?->pctChange24h !== null)
                                            <span class="badge rounded-pill {{ $currentQuote->pctChange24h >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}" style="font-size: 0.65rem; padding: 0.15rem 0.35rem; font-weight: 600;" id="show-card-quote-pct">
                                                {{ $currentQuote->formattedPctChange() }}
                                            </span>
                                        @endif
                                        <button
                                            type="button"
                                            class="btn btn-link p-0 text-decoration-none js-btn-refresh-quote-show"
                                            data-asset-type="{{ $cofrinho->asset_type }}"
                                            data-asset-code="{{ $cofrinho->asset_code }}"
                                            data-asset-quantity="{{ (float) $cofrinho->asset_quantity }}"
                                            data-total-invested="{{ (float) $totalInvested }}"
                                            data-asset-avg-price="{{ (float) $cofrinho->asset_avg_price }}"
                                            title="Atualizar cotação agora"
                                            style="font-size: 0.85rem; color: var(--dz-primary); line-height: 1;"
                                        >⟳</button>
                                    </div>
                                @endif
                            </div>
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

            <!-- Rendimentos / Juros OU Lucro / Valorização -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">{{ $isAsset ? 'Lucro / Valorização' : 'Rendimentos / Juros' }}</span>
                    @if($isAsset)
                        <div class="dz-kpi-card__icon-box {{ $profit >= 0 ? 'dz-kpi-card__icon-box--success' : 'dz-kpi-card__icon-box--danger' }}" id="show-profit-icon" style="{{ $profit < 0 ? 'background: rgba(239, 68, 68, 0.15); color: #dc2626;' : '' }}">
                            {{ $profit >= 0 ? '🚀' : '📉' }}
                        </div>
                    @else
                        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                            📈
                        </div>
                    @endif
                </div>
                <div>
                    @if($isAsset)
                        @php
                            $profitFormatted = ($profit >= 0 ? '+' : '-') . 'R$ ' . number_format(abs($profit), 2, ',', '.');
                            $profitPctFormatted = ($profitPct !== null ? (($profitPct >= 0 ? '+' : '') . number_format($profitPct, 2, ',', '.') . '%') : '—');
                        @endphp
                        <div class="dz-kpi-card__value {{ $profit >= 0 ? 'text-success' : 'text-danger' }} dz-privacy-blur" id="show-profit-value">
                            {{ $profitFormatted }}
                        </div>
                        <div class="dz-kpi-card__footer">
                            <span class="{{ $profit >= 0 ? 'text-success' : 'text-danger' }} fw-semibold" id="show-profit-pct">{{ $profitPctFormatted }}</span>
                            <span>de rentabilidade</span>
                        </div>
                    @else
                        <div class="dz-kpi-card__value text-success dz-privacy-blur">
                            +R$ {{ number_format($totalInterest, 2, ',', '.') }}
                        </div>
                        <div class="dz-kpi-card__footer">
                            <span class="text-success fw-semibold">{{ number_format($profitPct, 2, ',', '.') }}%</span>
                            <span>de rentabilidade</span>
                        </div>
                    @endif
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
            <!-- GRÁFICO 1: EVOLUÇÃO (PATRIMONIAL / GLOBAL) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm cofrinhos-chart-card">
                    <div class="cofrinhos-chart-head">
                        <div class="cofrinhos-chart-title-wrap">
                            <div class="cofrinhos-chart-icon" style="background: color-mix(in srgb, {{ $cardAccent }} 14%, transparent); color: {{ $cardAccent }};">
                                📊
                            </div>
                            <div>
                                <h2 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">{{ $isAsset ? 'Evolução Patrimonial' : 'Evolução Global do Cofrinho' }}</h2>
                                <p class="small text-secondary mb-0">{{ $isAsset ? 'Patrimônio atual vs Capital aportado ao longo do tempo' : 'Crescimento do saldo acumulado ao longo do tempo' }}</p>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="small text-secondary d-block">{{ $isAsset ? 'Patrimônio atual' : 'Saldo atual' }}</span>
                            <strong class="text-primary dz-privacy-blur" style="font-size: 0.95rem;">R$ {{ number_format($currentBalance, 2, ',', '.') }}</strong>
                            @if($isAsset)
                                <span class="small text-secondary d-block" style="font-size: 0.72rem;">Aportado: R$ {{ number_format($totalInvested, 2, ',', '.') }}</span>
                            @endif
                        </div>
                    </div>

                    @if($isAsset)
                        <!-- Legenda do Gráfico Patrimonial -->
                        <div class="d-flex align-items-center gap-3 px-4 pt-2 pb-1 small text-secondary flex-wrap">
                            <div class="d-flex align-items-center gap-1">
                                <span style="display:inline-block; width:12px; height:3px; background:{{ $cardAccent }}; border-radius:2px;"></span>
                                <span style="font-size:0.75rem;">Patrimônio (Mercado)</span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span style="display:inline-block; width:12px; height:2px; background:#64748b; border-radius:2px; border-top: 2px dashed #94a3b8;"></span>
                                <span style="font-size:0.75rem;">Total Aportado</span>
                            </div>
                            @if($target !== null && $target > 0 && $targetY !== null)
                                <div class="d-flex align-items-center gap-1">
                                    <span style="display:inline-block; width:12px; height:2px; background:#10b981; border-radius:2px; border-top: 1px dashed #10b981;"></span>
                                    <span style="font-size:0.75rem;">Meta</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="cofrinhos-chart-body">
                        <svg class="cofrinhos-chart-svg" viewBox="0 0 {{ $svgW }} {{ $svgH }}" role="img" aria-label="{{ $isAsset ? 'Gráfico de evolução patrimonial' : 'Gráfico de evolução global do cofrinho' }}">
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

                            <!-- Linha do Capital Aportado (para ativos) -->
                            @if($isAsset && !empty($investedPolyline))
                                <polyline class="cofrinhos-chart-line" points="{{ $investedPolyline }}" stroke="#94a3b8" stroke-width="2" stroke-dasharray="4 3" opacity="0.85" />
                            @endif

                            <!-- Área Gradiente -->
                            @if(!empty($balArea))
                                <polygon points="{{ $balArea }}" fill="url(#balGrad)" />
                            @endif

                            <!-- Linha Principal (Patrimônio / Saldo) -->
                            @if(!empty($balPolyline))
                                <polyline class="cofrinhos-chart-line" points="{{ $balPolyline }}" stroke="{{ $cardAccent }}" stroke-width="2.5" />
                            @endif

                            <!-- Pontos Interativos com Tooltip formatado e Hitbox expandida -->
                            @foreach($balPoints as $pt)
                                @php
                                    if ($isAsset) {
                                        $ptProfit = (float) ($pt['profit'] ?? ($pt['balance'] - ($pt['invested'] ?? 0)));
                                        $ptProfitPct = (float) ($pt['profit_pct'] ?? 0);
                                        $ptAporteMes = (float) ($pt['aportes'] ?? 0);

                                        $balTooltip = "<div class='text-start p-1'>"
                                            . "<div class='fw-bold mb-1' style='font-size: 0.85rem; color: #f8fafc; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 3px;'>" . e($pt['label']) . "</div>"
                                            . "<div class='d-flex justify-content-between gap-3 mb-1'><span>Patrimônio:</span><strong style='color: #38bdf8;'>R$ " . number_format((float) $pt['balance'], 2, ',', '.') . "</strong></div>"
                                            . "<div class='d-flex justify-content-between gap-3 mb-1' style='font-size: 0.72rem; color: #94a3b8;'><span>Total Aportado:</span><span>R$ " . number_format((float) ($pt['invested'] ?? 0), 2, ',', '.') . "</span></div>"
                                            . "<div class='d-flex justify-content-between gap-3 mb-1' style='font-size: 0.72rem;'><span>Resultado:</span><span style='color: " . ($ptProfit >= 0 ? '#10b981' : '#f87171') . "; font-weight: 600;'>" . ($ptProfit >= 0 ? '+' : '') . "R$ " . number_format($ptProfit, 2, ',', '.') . " (" . ($ptProfitPct >= 0 ? '+' : '') . number_format($ptProfitPct, 1, ',', '.') . "%)</span></div>"
                                            . (!empty($pt['quote_price']) ? "<div class='d-flex justify-content-between gap-3 mb-1' style='font-size: 0.70rem; color: #94a3b8;'><span>Cotação ref.:</span><span>R$ " . number_format((float) $pt['quote_price'], 2, ',', '.') . "</span></div>" : '')
                                            . ($ptAporteMes > 0 ? "<div class='d-flex justify-content-between gap-3' style='font-size: 0.72rem; color: #94a3b8; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 3px;'><span>Aportado no mês:</span><span style='color: #10b981;'>+R$ " . number_format($ptAporteMes, 2, ',', '.') . "</span></div>" : '')
                                            . "</div>";

                                    } else {
                                        $balTooltip = "<div class='text-start p-1'>"
                                            . "<div class='fw-bold mb-1' style='font-size: 0.85rem; color: #f8fafc; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 3px;'>" . e($pt['label']) . "</div>"
                                            . "<div class='d-flex justify-content-between gap-3 mb-1'><span>Saldo:</span><strong style='color: #38bdf8;'>R$ " . number_format((float) $pt['balance'], 2, ',', '.') . "</strong></div>"
                                            . ($target !== null && $target > 0 ? "<div class='d-flex justify-content-between gap-3 mb-1' style='font-size: 0.72rem; color: #94a3b8;'><span>Meta:</span><span style='color: #10b981;'>R$ " . number_format((float) $target, 2, ',', '.') . " (" . number_format(min(100.0, ((float) $pt['balance'] / $target) * 100), 1, ',', '.') . "%)</span></div>" : '')
                                            . "<div class='d-flex justify-content-between gap-3' style='font-size: 0.72rem; color: #94a3b8; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 3px;'><span>Fluxo mês:</span><span style='color: " . ($pt['net'] >= 0 ? '#10b981' : '#f87171') . "; font-weight: 600;'>" . ($pt['net'] >= 0 ? '+' : '') . "R$ " . number_format((float) $pt['net'], 2, ',', '.') . "</span></div>"
                                            . "</div>";
                                    }
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

            <!-- GRÁFICO 2: ACUMULAÇÃO DE ATIVO (SE ASSET) OU EVOLUÇÃO DOS JUROS (SE FIAT) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm cofrinhos-chart-card">
                    @if($isAsset)
                        <!-- CABEÇALHO ATIVO -->
                        <div class="cofrinhos-chart-head">
                            <div class="cofrinhos-chart-title-wrap">
                                <div class="cofrinhos-chart-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">
                                    {{ $cofrinho->isBitcoin() ? '₿' : '🪙' }}
                                </div>
                                <div>
                                    <h2 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Acumulação de {{ $cofrinho->assetUnitLabel() }}</h2>
                                    <p class="small text-secondary mb-0">Compras no mês e quantidade acumulada</p>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="small text-secondary d-block">Total acumulado</span>
                                <strong class="text-warning dz-privacy-blur" style="font-size: 0.95rem;">
                                    {{ rtrim(rtrim(number_format((float) $cofrinho->asset_quantity, 8, ',', '.'), '0'), ',') }} {{ $cofrinho->assetUnitLabel() }}
                                </strong>
                            </div>
                        </div>



                        <!-- Legenda do Gráfico de Acumulação -->
                        <div class="d-flex align-items-center gap-3 px-4 pt-2 pb-1 small text-secondary flex-wrap">
                            <div class="d-flex align-items-center gap-1">
                                <span style="display:inline-block; width:10px; height:10px; background:rgba(245, 158, 11, 0.75); border-radius:2px;"></span>
                                <span style="font-size:0.75rem;">Comprado no mês</span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span style="display:inline-block; width:12px; height:3px; background:{{ $cardAccent }}; border-radius:2px;"></span>
                                <span style="font-size:0.75rem;">Total Acumulado ({{ $cofrinho->assetUnitLabel() }})</span>
                            </div>
                        </div>

                        <div class="cofrinhos-chart-body">
                            @if($hasAssetData)
                                <svg class="cofrinhos-chart-svg" viewBox="0 0 {{ $svgW }} {{ $svgH }}" role="img" aria-label="Gráfico de acumulação do ativo">
                                    <defs>
                                        <linearGradient id="assetBarGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.9" />
                                            <stop offset="100%" stop-color="#d97706" stop-opacity="0.5" />
                                        </linearGradient>
                                    </defs>

                                    <!-- Linhas de grade sutis -->
                                    <line x1="{{ $padX }}" y1="{{ $padY }}" x2="{{ $svgW - $padX }}" y2="{{ $padY }}" class="cofrinhos-chart-grid-line" />
                                    <line x1="{{ $padX }}" y1="{{ round($padY + ($innerH * 0.5), 2) }}" x2="{{ $svgW - $padX }}" y2="{{ round($padY + ($innerH * 0.5), 2) }}" class="cofrinhos-chart-grid-line" />
                                    <line x1="{{ $padX }}" y1="{{ $balBottomY }}" x2="{{ $svgW - $padX }}" y2="{{ $balBottomY }}" class="cofrinhos-chart-grid-line" />

                                    <!-- Barras Mensais de Compras do Ativo -->
                                    @foreach($assetPoints as $pt)
                                        @if($pt['bar_h'] > 0.5)
                                            @php
                                                $qMesFormatted = rtrim(rtrim(number_format((float) ($pt['qty_monthly'] ?? 0), 8, ',', '.'), '0'), ',');
                                                $barTooltip = "<div class='text-start p-1'>"
                                                    . "<div class='fw-bold mb-1' style='font-size: 0.85rem; color: #f8fafc; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 3px;'>" . e($pt['label']) . "</div>"
                                                    . "<div class='d-flex justify-content-between gap-3 mb-1'><span>Comprado no mês:</span><strong style='color: #f59e0b;'>+" . $qMesFormatted . " " . e($cofrinho->assetUnitLabel()) . "</strong></div>"
                                                    . (!empty($pt['aportes']) ? "<div class='d-flex justify-content-between gap-3 mb-1' style='font-size: 0.72rem; color: #94a3b8;'><span>Valor investido:</span><span>R$ " . number_format((float) $pt['aportes'], 2, ',', '.') . "</span></div>" : '')
                                                    . "</div>";
                                            @endphp
                                            <rect
                                                x="{{ $pt['x'] - ($barW / 2) }}"
                                                y="{{ $pt['y_bar'] }}"
                                                width="{{ $barW }}"
                                                height="{{ $pt['bar_h'] }}"
                                                rx="3"
                                                ry="3"
                                                fill="url(#assetBarGrad)"
                                                class="cofrinhos-chart-bar"
                                                data-bs-toggle="tooltip"
                                                data-bs-html="true"
                                                data-bs-placement="top"
                                                data-bs-custom-class="dz-chart-tooltip"
                                                data-bs-title="{{ $barTooltip }}"
                                                tabindex="0"
                                                aria-label="{{ $pt['label'] }}: Comprado {{ $qMesFormatted }} {{ $cofrinho->assetUnitLabel() }}"
                                            ></rect>
                                        @endif
                                    @endforeach

                                    <!-- Linha de Quantidade Acumulada -->
                                    @if(!empty($assetPolyline))
                                        <polyline class="cofrinhos-chart-line" points="{{ $assetPolyline }}" stroke="{{ $cardAccent }}" stroke-width="2.5" />
                                    @endif

                                    <!-- Pontos de Quantidade Acumulada com Tooltip e Hitbox -->
                                    @foreach($assetPoints as $pt)
                                        @php
                                            $qCumFormatted = rtrim(rtrim(number_format((float) ($pt['qty_cumulative'] ?? 0), 8, ',', '.'), '0'), ',');
                                            $cumTooltip = "<div class='text-start p-1'>"
                                                . "<div class='fw-bold mb-1' style='font-size: 0.85rem; color: #f8fafc; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 3px;'>" . e($pt['label']) . "</div>"
                                                . "<div class='d-flex justify-content-between gap-3 mb-1'><span>Total acumulado:</span><strong style='color: #f59e0b;'>" . $qCumFormatted . " " . e($cofrinho->assetUnitLabel()) . "</strong></div>"
                                                . "<div class='d-flex justify-content-between gap-3 mb-1' style='font-size: 0.72rem; color: #94a3b8;'><span>Patrimônio est.:</span><span style='color: #38bdf8;'>R$ " . number_format((float) $pt['balance'], 2, ',', '.') . "</span></div>"
                                                . (!empty($pt['invested']) ? "<div class='d-flex justify-content-between gap-3' style='font-size: 0.72rem; color: #94a3b8; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 3px;'><span>Total aportado:</span><span>R$ " . number_format((float) $pt['invested'], 2, ',', '.') . "</span></div>" : '')
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
                                            data-bs-title="{{ $cumTooltip }}"
                                            tabindex="0"
                                            aria-label="{{ $pt['label'] }}: Acumulado {{ $qCumFormatted }} {{ $cofrinho->assetUnitLabel() }}"
                                        ></circle>
                                        <circle
                                            cx="{{ $pt['x'] }}"
                                            cy="{{ $pt['y_cum'] }}"
                                            r="4"
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
                                    @foreach($assetPoints as $pt)
                                        <span style="flex: 1; text-align: center;">{{ $pt['label'] }}</span>
                                    @endforeach
                                </div>
                            @else
                                <div class="d-flex flex-column align-items-center justify-content-center text-center p-4 my-auto" style="min-height: 180px;">
                                    <div class="fs-2 mb-2 opacity-50">🪙</div>
                                    <h3 class="h6 fw-bold mb-1" style="color: var(--dz-text-title);">Nenhum aporte registrado ainda</h3>
                                    <p class="small text-secondary mb-3" style="max-width: 320px;">Faça seu primeiro aporte para acompanhar a curva de acumulação deste ativo ao longo do tempo.</p>
                                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalAssetAporte">
                                        + Aporte no Ativo
                                    </button>
                                </div>
                            @endif
                        </div>
                    @else
                        <!-- CABEÇALHO FIAT (JUROS) -->
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
                    @endif
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
                                    $isSaldoInicial = ($movement['kind'] ?? '') === 'saldo_inicial';
                                @endphp
                                <tr>
                                    <td class="text-nowrap">{{ optional($movement['date'])->format('d/m/Y') }}</td>
                                    <td>
                                        @if(($movement['kind'] ?? '') === 'saldo_inicial')
                                            <span class="badge rounded-pill text-bg-primary-subtle text-primary-emphasis border border-primary-subtle">Saldo Inicial</span>
                                        @elseif(($movement['kind'] ?? '') === 'aporte')
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
                                        @if(($isInterest || $isSaldoInicial) && !empty($movement['id']))
                                            <form
                                                action="{{ route('cofrinhos.interest.destroy', $movement['id']) }}"
                                                method="POST"
                                                class="d-inline"
                                                data-confirm-title="{{ $isSaldoInicial ? 'Excluir saldo inicial' : 'Excluir rendimento' }}"
                                                data-confirm="{{ $isSaldoInicial ? 'Excluir este lançamento de saldo inicial? O saldo do cofrinho será recalculado.' : 'Excluir este lançamento de juros/rendimento? O saldo do cofrinho será recalculado.' }}"
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
                                                    title="{{ $isSaldoInicial ? 'Excluir saldo inicial' : 'Excluir este rendimento' }}"
                                                    aria-label="{{ $isSaldoInicial ? 'Excluir saldo inicial' : 'Excluir rendimento' }}"
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
                    <input type="hidden" name="_redirect_to" value="show">
                    <div class="modal-body vstack gap-3">
                        <div>
                            <x-input-label for="fp-edit-name" value="Nome do cofrinho" />
                            <x-text-input id="fp-edit-name" name="name" class="mt-1 rounded-3" value="{{ old('_cofrinho_form') === 'edit' ? old('name', $cofrinho->name) : $cofrinho->name }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="fp-edit-target" value="Meta financeira (R$, opcional)" />
                            <x-text-input id="fp-edit-target" name="target_amount" type="text" class="mt-1 rounded-3" value="{{ old('_cofrinho_form') === 'edit' ? old('target_amount') : ($cofrinho->target_amount !== null ? number_format((float) $cofrinho->target_amount, 2, ',', '.') : '') }}" placeholder="0,00" />
                            <x-input-error :messages="$errors->get('target_amount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fp-edit-color" value="Cor de identificação" />
                            <input
                                type="color"
                                id="fp-edit-color"
                                name="color"
                                value="{{ old('_cofrinho_form') === 'edit' ? old('color', $cofrinho->color ?: '#0d9488') : ($cofrinho->color ?: '#0d9488') }}"
                                class="form-control form-control-color w-100 mt-1 rounded-3"
                            >
                            <x-input-error :messages="$errors->get('color')" class="mt-2" />
                        </div>

                        <div class="form-check form-switch pt-1">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="fp-edit-is-active" value="1" @checked(old('_cofrinho_form') === 'edit' ? old('is_active', $cofrinho->is_active) : $cofrinho->is_active)>
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
                                    <x-text-input id="modal-aporte-amount" name="amount" type="text" inputmode="decimal" class="mt-1 rounded-3 js-show-aporte-amount" placeholder="0,00" required />
                                </div>
                                <div class="col-6">
                                    <x-input-label for="modal-aporte-price" value="Cotação / Preço unitário (R$)" />
                                    <x-text-input id="modal-aporte-price" name="asset_unit_price" type="text" inputmode="decimal" class="mt-1 rounded-3 js-show-aporte-price" value="{{ $quotePrice !== null ? number_format((float) $quotePrice, 2, '.', '') : '' }}" placeholder="Qualquer valor de cotação" />
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <x-input-label for="modal-aporte-quantity" value="Quantidade ({{ $cofrinho->assetUnitLabel() }})" />
                                    <x-text-input id="modal-aporte-quantity" name="asset_quantity" type="text" inputmode="decimal" class="mt-1 rounded-3 js-show-aporte-quantity" placeholder="0.00000000" />
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

    {{-- MODAL VENDA / RESGATE EM ATIVO (SE FOR ATIVO CUSTOMIZADO) --}}
    @if($isAsset)
        <div class="modal fade" id="modalAssetVenda" tabindex="-1" aria-labelledby="modalAssetVendaLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-danger text-white border-0">
                        <h2 class="modal-title h5 mb-0 text-white" id="modalAssetVendaLabel">
                            Venda / Resgate — {{ $cofrinho->name }} ({{ $cofrinho->asset_code ?: $cofrinho->assetTypeLabel() }})
                        </h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form method="post" action="{{ route('cofrinhos.asset-sale.store', $cofrinho) }}">
                        @csrf
                        <div class="modal-body vstack gap-3">
                            {{-- Posição Atual --}}
                            <div class="p-3 rounded-3 border border-secondary-subtle bg-body-secondary">
                                <div class="row g-2 text-center">
                                    <div class="col-6">
                                        <span class="small text-secondary d-block">Saldo disponível</span>
                                        <strong class="fs-6">{{ rtrim(rtrim(number_format((float) $cofrinho->asset_quantity, 8, ',', '.'), '0'), ',') ?: '0' }} {{ $cofrinho->assetUnitLabel() }}</strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="small text-secondary d-block">Preço Médio atual</span>
                                        <strong class="fs-6">R$ {{ number_format((float) $cofrinho->asset_avg_price, 2, ',', '.') }}</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <x-input-label for="modal-venda-amount" value="Valor total recebido (R$)" />
                                    <x-text-input id="modal-venda-amount" name="amount" type="text" inputmode="decimal" class="mt-1 rounded-3 js-show-venda-amount" placeholder="0,00" required />
                                </div>
                                <div class="col-6">
                                    <x-input-label for="modal-venda-price" value="Cotação de venda (R$)" />
                                    <x-text-input id="modal-venda-price" name="asset_unit_price" type="text" inputmode="decimal" class="mt-1 rounded-3 js-show-venda-price" value="{{ $quotePrice !== null ? number_format((float) $quotePrice, 2, '.', '') : '' }}" placeholder="Qualquer valor de cotação" />
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <x-input-label for="modal-venda-quantity" value="Quantidade a vender ({{ $cofrinho->assetUnitLabel() }})" />
                                    <x-text-input id="modal-venda-quantity" name="asset_quantity" type="text" inputmode="decimal" class="mt-1 rounded-3 js-show-venda-quantity" placeholder="0.00000000" required />
                                </div>
                                <div class="col-6">
                                    <x-input-label for="modal-venda-date" value="Data da venda" />
                                    <x-text-input id="modal-venda-date" name="date" type="date" class="mt-1 rounded-3" value="{{ now()->toDateString() }}" required />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="modal-venda-account" value="Creditar em conta bancária (opcional)" />
                                <select id="modal-venda-account" name="account_id" class="form-select mt-1 rounded-3">
                                    <option value="">Nenhuma conta (custódia externa / reinvestido)</option>
                                    @foreach($regularAccounts as $acc)
                                        <option value="{{ $acc->id }}">
                                            {{ $acc->name }} (Saldo: R$ {{ number_format((float) $acc->balance, 2, ',', '.') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="modal-venda-note" value="Observação (opcional)" />
                                <x-text-input id="modal-venda-note" name="note" type="text" class="mt-1 rounded-3" placeholder="Ex: Venda parcial / realização de lucro" />
                            </div>
                        </div>
                        <div class="modal-footer border-secondary-subtle">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger rounded-pill px-4">Salvar venda</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        <style>
            @keyframes fa-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .fa-spin {
                display: inline-block;
                animation: fa-spin 1s infinite linear;
            }
        </style>
        <script>

            (function () {
                function initShowPage() {
                    const bs = typeof bootstrap !== 'undefined' ? bootstrap : window.bootstrap;
                    if (bs?.Tooltip) {
                        try {
                            document.querySelectorAll('.cofrinhos-chart-svg [data-bs-toggle="tooltip"]').forEach(function (el) {
                                bs.Tooltip.getOrCreateInstance(el, {
                                    container: 'body',
                                    html: true,
                                    trigger: 'hover focus'
                                });
                            });
                        } catch (e) {
                            console.debug('Tooltip init error:', e);
                        }
                    }

                    // Sincronizador de campos no modal de aporte de ativos
                    const showAmt = document.getElementById('modal-aporte-amount');
                    const showPrc = document.getElementById('modal-aporte-price');
                    const showQty = document.getElementById('modal-aporte-quantity');

                    function parseInputNumber(val) {
                        if (val === null || val === undefined) return 0;
                        let s = String(val).trim().replace(/[^\d.,]/g, '');
                        if (!s) return 0;
                        if (s.includes(',') && s.includes('.')) {
                            if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
                                s = s.replace(/\./g, '').replace(',', '.');
                            } else {
                                s = s.replace(/,/g, '');
                            }
                        } else if (s.includes(',')) {
                            s = s.replace(',', '.');
                        } else if (s.includes('.')) {
                            const parts = s.split('.');
                            if (parts.length > 2) {
                                s = s.replace(/\./g, '');
                            } else if (parts[0] !== '0' && parts[1].length === 3) {
                                s = s.replace('.', '');
                            }
                        }
                        const n = parseFloat(s);
                        return isNaN(n) ? 0 : n;
                    }

                    function updateQuotePrice() {
                        if (!showAmt || !showQty || !showPrc) return;
                        const amt = parseInputNumber(showAmt.value);
                        const qty = parseInputNumber(showQty.value);
                        if (amt > 0 && qty > 0) {
                            const newPrc = amt / qty;
                            showPrc.value = newPrc >= 1 ? newPrc.toFixed(2) : newPrc.toFixed(4);
                        }
                    }

                    if (showAmt && showPrc && showQty) {
                        // Ao alterar valor investido: se quantidade já informada, recalcula cotação; senão calcula quantidade pela cotação
                        const onShowAmountChange = function () {
                            const qty = parseInputNumber(showQty.value);
                            if (qty > 0) {
                                updateQuotePrice();
                            } else {
                                const amt = parseInputNumber(showAmt.value);
                                const prc = parseInputNumber(showPrc.value);
                                if (amt > 0 && prc > 0) {
                                    showQty.value = (amt / prc).toFixed(8).replace(/\.?0+$/, '');
                                }
                            }
                        };
                        showAmt.addEventListener('input', onShowAmountChange);
                        showAmt.addEventListener('change', onShowAmountChange);

                        // Ao alterar quantidade comprada: NUNCA altera valor investido, SEMPRE recalcula cotação!
                        const onShowQuantityChange = function () {
                            updateQuotePrice();
                        };
                        showQty.addEventListener('input', onShowQuantityChange);
                        showQty.addEventListener('change', onShowQuantityChange);

                        // Ao alterar cotação diretamente: calcula quantidade a partir do valor investido
                        const onShowPriceChange = function () {
                            const prc = parseInputNumber(showPrc.value);
                            const amt = parseInputNumber(showAmt.value);
                            if (amt > 0 && prc > 0) {
                                showQty.value = (amt / prc).toFixed(8).replace(/\.?0+$/, '');
                            }
                        };
                        showPrc.addEventListener('input', onShowPriceChange);
                        showPrc.addEventListener('change', onShowPriceChange);
                    }

                    // Sincronizador de campos no modal de venda de ativos
                    const showVendaAmt = document.getElementById('modal-venda-amount');
                    const showVendaPrc = document.getElementById('modal-venda-price');
                    const showVendaQty = document.getElementById('modal-venda-quantity');

                    function updateVendaQuotePrice() {
                        if (!showVendaAmt || !showVendaQty || !showVendaPrc) return;
                        const amt = parseInputNumber(showVendaAmt.value);
                        const qty = parseInputNumber(showVendaQty.value);
                        if (amt > 0 && qty > 0) {
                            const newPrc = amt / qty;
                            showVendaPrc.value = newPrc >= 1 ? newPrc.toFixed(2) : newPrc.toFixed(4);
                        }
                    }

                    if (showVendaAmt && showVendaPrc && showVendaQty) {
                        const onShowVendaAmountChange = function () {
                            const qty = parseInputNumber(showVendaQty.value);
                            if (qty > 0) {
                                updateVendaQuotePrice();
                            } else {
                                const amt = parseInputNumber(showVendaAmt.value);
                                const prc = parseInputNumber(showVendaPrc.value);
                                if (amt > 0 && prc > 0) {
                                    showVendaQty.value = (amt / prc).toFixed(8).replace(/\.?0+$/, '');
                                }
                            }
                        };
                        showVendaAmt.addEventListener('input', onShowVendaAmountChange);
                        showVendaAmt.addEventListener('change', onShowVendaAmountChange);

                        const onShowVendaQuantityChange = function () {
                            updateVendaQuotePrice();
                        };
                        showVendaQty.addEventListener('input', onShowVendaQuantityChange);
                        showVendaQty.addEventListener('change', onShowVendaQuantityChange);

                        const onShowVendaPriceChange = function () {
                            const prc = parseInputNumber(showVendaPrc.value);
                            const amt = parseInputNumber(showVendaAmt.value);
                            if (amt > 0 && prc > 0) {
                                showVendaQty.value = (amt / prc).toFixed(8).replace(/\.?0+$/, '');
                            }
                        };
                        showVendaPrc.addEventListener('input', onShowVendaPriceChange);
                        showVendaPrc.addEventListener('change', onShowVendaPriceChange);
                    }

                    // Atualização de Cotação via AJAX dinâmica (sem reload, igual à tela de cofrinhos)
                    const refreshBtn = document.querySelector('.js-btn-refresh-quote-show');
                    if (refreshBtn) {
                        refreshBtn.addEventListener('click', function (e) {
                            if (e) {
                                e.preventDefault();
                                e.stopPropagation();
                            }
                            const type = this.getAttribute('data-asset-type') || 'crypto';
                            const code = this.getAttribute('data-asset-code') || '';
                            const qty = parseFloat(this.getAttribute('data-asset-quantity')) || 0;
                            const invested = parseFloat(this.getAttribute('data-total-invested')) || 0;
                            if (!code) return;

                            this.classList.add('fa-spin');
                            this.style.pointerEvents = 'none';

                            fetch(`{{ route('cofrinhos.quote') }}?type=${encodeURIComponent(type)}&code=${encodeURIComponent(code)}&fresh=1`)
                                .then(res => res.json())
                                .then(res => {
                                    if (res.success && res.data) {
                                        const newPrice = parseFloat(res.data.price) || 0;
                                        if (newPrice <= 0) return;

                                        // 1. Atualiza valor da cotação no Card 1
                                        const quotePriceEl = document.getElementById('show-card-quote-price');
                                        if (quotePriceEl && res.data.formatted_price) {
                                            quotePriceEl.textContent = res.data.formatted_price;
                                        }

                                        // 2. Atualiza badge de variação 24h
                                        const quotePctEl = document.getElementById('show-card-quote-pct');
                                        if (quotePctEl) {
                                            if (res.data.pct_change_24h !== null && res.data.pct_change_24h !== undefined) {
                                                const isPositivePct = res.data.pct_change_24h >= 0;
                                                quotePctEl.textContent = (isPositivePct ? '+' : '') + Number(res.data.pct_change_24h).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
                                                quotePctEl.className = `badge rounded-pill ${isPositivePct ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}`;
                                                quotePctEl.classList.remove('d-none');
                                            } else {
                                                quotePctEl.classList.add('d-none');
                                            }
                                        }

                                        // 3. Atualiza Patrimônio Atual no Card 1
                                        const balanceEl = document.getElementById('show-current-balance');
                                        const newBalance = qty > 0 ? (qty * newPrice) : 0;
                                        if (balanceEl && qty > 0) {
                                            balanceEl.textContent = 'R$ ' + newBalance.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                        }

                                        // 4. Atualiza Lucro / Valorização no Card 3
                                        const profitValEl = document.getElementById('show-profit-value');
                                        const profitPctEl = document.getElementById('show-profit-pct');
                                        const profitIconEl = document.getElementById('show-profit-icon');

                                        if (profitValEl && profitPctEl && qty > 0) {
                                            const profit = newBalance - invested;
                                            const profitPct = invested > 0.0001 ? ((newBalance / invested) - 1) * 100 : 0;
                                            const isProfit = profit >= 0;
                                            const prefix = isProfit ? '+' : '-';

                                            profitValEl.textContent = `${prefix}R$ ${Math.abs(profit).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                                            profitValEl.className = `dz-kpi-card__value ${isProfit ? 'text-success' : 'text-danger'} dz-privacy-blur`;

                                            profitPctEl.textContent = `${isProfit ? '+' : ''}${profitPct.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
                                            profitPctEl.className = `${isProfit ? 'text-success' : 'text-danger'} fw-semibold`;

                                            if (profitIconEl) {
                                                profitIconEl.textContent = isProfit ? '🚀' : '📉';
                                                profitIconEl.className = `dz-kpi-card__icon-box ${isProfit ? 'dz-kpi-card__icon-box--success' : 'dz-kpi-card__icon-box--danger'}`;
                                                profitIconEl.style.background = isProfit ? '' : 'rgba(239, 68, 68, 0.15)';
                                                profitIconEl.style.color = isProfit ? '' : '#dc2626';
                                            }
                                        }

                                        // 5. Atualiza campo de cotação no modal de aporte e no modal de venda
                                        const modalPriceInput = document.getElementById('modal-aporte-price');
                                        if (modalPriceInput) {
                                            modalPriceInput.value = newPrice.toFixed(2);
                                        }
                                        const modalVendaPriceInput = document.getElementById('modal-venda-price');
                                        if (modalVendaPriceInput) {
                                            modalVendaPriceInput.value = newPrice.toFixed(2);
                                        }
                                    }
                                })
                                .catch(err => console.debug('Quote refresh failed:', err))
                                .finally(() => {
                                    this.classList.remove('fa-spin');
                                    this.style.pointerEvents = '';
                                });
                        });
                    }

                    @if ($errors->any() && old('_cofrinho_form') === 'edit')
                        const editModalEl = document.getElementById('modalCofrinhoEdit');
                        if (editModalEl && window.bootstrap?.Modal) {
                            window.bootstrap.Modal.getOrCreateInstance(editModalEl).show();
                        }
                    @endif
                }


                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initShowPage);
                } else {
                    initShowPage();
                }
            })();
        </script>
    @endpush
</x-app-layout>
