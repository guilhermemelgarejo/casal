@php
    $money = fn ($val) => 'R$ ' . number_format((float) $val, 2, ',', '.');
    $hasActiveFilters = $filterAccountId || $filterCategoryId || $filterUserId || $filterType || $searchQuery;
@endphp
<x-app-layout :installment-groups-modal-payload="$installmentGroupsModalPayload ?? []">
    <x-slot name="header">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h1 class="dz-page-title">Lançamentos</h1>
                <span class="badge rounded-pill" style="background: var(--dz-primary-subtle); color: var(--dz-primary); font-size: 0.72rem; font-weight: 700;">
                    {{ count($transactions) }} registros
                </span>
            </div>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Histórico completo e filtros por conta, categoria e tipo
            </div>
        </div>

        <!-- Navegador de Período Inline -->
        <div class="dz-period-nav">
            <a href="{{ route('transactions.index', array_merge(request()->query(), ['period' => $periodPrev])) }}" class="dz-period-nav__btn" title="Mês anterior">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="dz-period-nav__label">{{ ucfirst($periodLabel) }}</span>
            <a href="{{ route('transactions.index', array_merge(request()->query(), ['period' => $periodNext])) }}" class="dz-period-nav__btn" title="Próximo mês">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </x-slot>


    <div class="container-xxl py-4 px-3 px-lg-4">
        @if (session('success'))
            <x-alert type="success" class="mb-4" :message="session('success')" />
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-4" :message="session('error')" />
        @endif
        @if ($errors->any() && old('_form') === 'account-transfer')
            <x-alert type="danger" class="mb-4" title="Não foi possível concluir a transferência">
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <!-- BARRA SUPERIOR: NAVEGADOR DE PERÍODO & RESUMO RÁPIDO -->
        <!-- RESUMO DO MÊS SELECIONADO -->
        <div class="dz-card p-3 mb-4" style="background: var(--dz-bg-card); border-radius: var(--dz-radius-lg); border: 1px solid var(--dz-border);">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold" style="color: var(--dz-text-title); font-size: 0.88rem;">Resumo de {{ ucfirst($periodLabel) }}:</span>
                    @if($period !== now()->format('Y-m'))
                        <a href="{{ route('transactions.index', array_merge(request()->query(), ['period' => now()->format('Y-m')])) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size: 0.72rem;">
                            Ir para Mês Atual
                        </a>
                    @endif
                </div>

                <!-- Mini KPIs do Mês -->
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <span style="font-size: 0.75rem; color: var(--dz-text-secondary);">Receitas:</span>
                        <span class="fw-bold text-success dz-privacy-blur" style="font-size: 0.9rem;">+ {{ $money($totalIncome) }}</span>
                    </div>
                    <div style="width: 1px; height: 16px; background: var(--dz-border);"></div>
                    <div class="d-flex align-items-center gap-2">
                        <span style="font-size: 0.75rem; color: var(--dz-text-secondary);">Despesas:</span>
                        <span class="fw-bold text-danger dz-privacy-blur" style="font-size: 0.9rem;">- {{ $money($totalExpense) }}</span>
                    </div>
                    <div style="width: 1px; height: 16px; background: var(--dz-border);"></div>
                    <div class="d-flex align-items-center gap-2">
                        <span style="font-size: 0.75rem; color: var(--dz-text-secondary);">Resultado:</span>
                        <span class="fw-bold {{ $netResult >= 0 ? 'text-success' : 'text-danger' }} dz-privacy-blur" style="font-size: 0.9rem;">{{ $money($netResult) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTROS AVANÇADOS -->
        <div class="dz-card p-3 mb-4" style="background: var(--dz-bg-card); border-radius: var(--dz-radius-lg); border: 1px solid var(--dz-border);">
            <form method="GET" action="{{ route('transactions.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="period" value="{{ sprintf('%04d-%02d', $year, $month) }}">

                <!-- Tipo -->
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-bold mb-1" style="font-size: 0.72rem; color: var(--dz-text-secondary);">Tipo</label>
                    <select name="type" class="form-select form-select-sm" style="background: var(--dz-bg-canvas); border-color: var(--dz-border); color: var(--dz-text-body); border-radius: var(--dz-radius-md);">
                        <option value="all" @selected(!$filterType || $filterType === 'all')>Todos os tipos</option>
                        <option value="expense" @selected($filterType === 'expense')>🔴 Apenas Despesas</option>
                        <option value="income" @selected($filterType === 'income')>🟢 Apenas Receitas</option>
                    </select>
                </div>

                <!-- Conta / Cartão -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label small fw-bold mb-1" style="font-size: 0.72rem; color: var(--dz-text-secondary);">Conta ou Cartão</label>
                    <select name="account_id" class="form-select form-select-sm" style="background: var(--dz-bg-canvas); border-color: var(--dz-border); color: var(--dz-text-body); border-radius: var(--dz-radius-md);">
                        <option value="">Todas as contas</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" @selected((int)$filterAccountId === (int)$acc->id)>
                                {{ $acc->isCreditCard() ? '💳 ' : '🏦 ' }}{{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Categoria -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label small fw-bold mb-1" style="font-size: 0.72rem; color: var(--dz-text-secondary);">Categoria</label>
                    <select name="category_id" class="form-select form-select-sm" style="background: var(--dz-bg-canvas); border-color: var(--dz-border); color: var(--dz-text-body); border-radius: var(--dz-radius-md);">
                        <option value="">Todas as categorias</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((int)$filterCategoryId === (int)$cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Busca por Descrição / Valor -->
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-bold mb-1" style="font-size: 0.72rem; color: var(--dz-text-secondary);">Buscar</label>
                    <input type="text" name="search" value="{{ $searchQuery }}" class="form-control form-control-sm" placeholder="Ex: mercado, 50..." style="background: var(--dz-bg-canvas); border-color: var(--dz-border); color: var(--dz-text-body); border-radius: var(--dz-radius-md);">
                </div>

                <!-- Botões de Ação -->
                <div class="col-12 col-lg-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold" style="border-radius: var(--dz-radius-md); font-size: 0.78rem;">
                        🔍 Filtrar
                    </button>
                    @if($hasActiveFilters)
                        <a href="{{ route('transactions.index', ['period' => sprintf('%04d-%02d', $year, $month)]) }}" class="btn btn-sm btn-outline-secondary" style="border-radius: var(--dz-radius-md); font-size: 0.78rem;" title="Limpar filtros">
                            ✕
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- LISTA DE LANÇAMENTOS -->
        <div class="dz-card p-3 p-lg-4" style="background: var(--dz-bg-card); border-radius: var(--dz-radius-lg); border: 1px solid var(--dz-border);">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom" style="border-color: var(--dz-border-subtle) !important;">
                <div>
                    <h3 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Lançamentos Filtrados</h3>
                    <span style="font-size: 0.78rem; color: var(--dz-text-secondary);">
                        {{ count($transactions) }} movimentação(ões) encontrada(s) • {{ ucfirst($periodLabel) }}
                    </span>
                </div>
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="font-size: 0.75rem;">
                    ← Voltar ao Painel
                </a>
            </div>

            <div class="list-group list-group-flush" role="list">
                @include('transactions.partials.transaction-list-rows', [
                    'emptyTitle' => 'Nenhum lançamento encontrado',
                    'emptyHint' => 'Tente ajustar os filtros acima ou registre um novo lançamento usando os botões do topo.',
                ])
            </div>
        </div>

    </div>
</x-app-layout>

