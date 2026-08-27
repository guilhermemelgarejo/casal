@php
    $openCofrinhoCreate = old('_cofrinho_form') === 'create' || (request()->boolean('novo') && old('_cofrinho_form') !== 'edit');
    $openCofrinhoEdit = old('_cofrinho_form') === 'edit';
    $fpCreateColor = old('color', '#0d9488');
    $prefillPayload = null;
    if (isset($prefillEditProject) && $prefillEditProject && old('_cofrinho_form') !== 'create') {
        $prefillPayload = [
            'id' => $prefillEditProject->id,
            'name' => $prefillEditProject->name,
            'asset_type' => $prefillEditProject->asset_type ?: 'fiat',
            'asset_code' => $prefillEditProject->asset_code ?: '',
            'asset_quantity' => $prefillEditProject->asset_quantity !== null ? (string) $prefillEditProject->asset_quantity : '',
            'asset_avg_price' => $prefillEditProject->asset_avg_price !== null ? (string) $prefillEditProject->asset_avg_price : '',
            'target_amount' => $prefillEditProject->target_amount !== null
                ? number_format((float) $prefillEditProject->target_amount, 2, ',', '.')
                : '',
            'color' => $prefillEditProject->color ?: '#0d9488',
            'saved' => number_format((float) $prefillEditProject->savedProgress(), 2, ',', '.'),
        ];
    }

    $cofrinhoEditSavedForJs = '0,00';
    if (($errors ?? null)?->any() && old('_cofrinho_form') === 'edit' && old('cofrinho_id')) {
        $editProjForJs = $projects->firstWhere('id', (int) old('cofrinho_id'));
        if ($editProjForJs) {
            $cofrinhoEditSavedForJs = number_format((float) $editProjForJs->savedProgress(), 2, ',', '.');
        }
    }

    $jsOpenEditPayload = null;
    if ($openCofrinhoEdit) {
        $jsOpenEditPayload = [
            'id' => (int) old('cofrinho_id', 0),
            'name' => (string) old('name', ''),
            'asset_type' => (string) old('asset_type', 'fiat'),
            'asset_code' => (string) old('asset_code', ''),
            'asset_quantity' => old('asset_quantity') !== null ? (string) old('asset_quantity') : '',
            'asset_avg_price' => old('asset_avg_price') !== null ? (string) old('asset_avg_price') : '',
            'target_amount' => old('target_amount') !== null ? (string) old('target_amount') : '',
            'color' => (string) old('color', '#0d9488'),
            'saved' => $cofrinhoEditSavedForJs,
        ];
    }

    $cofrinhoRows = $projects->map(function ($project) use ($quotes) {
        $isAsset = $project->isCustomAsset();
        $quoteKey = "{$project->asset_type}:{$project->asset_code}";
        $quote = $isAsset ? ($quotes[$quoteKey] ?? null) : null;
        $quotePrice = $quote?->price;

        $saved = $isAsset ? (float) $project->currentEstimatedValue($quotePrice) : (float) $project->savedProgress();
        $invested = $isAsset ? (float) $project->totalInvestedBrl() : (float) $project->savedProgress();
        $target = $project->target_amount !== null ? (float) $project->target_amount : null;
        $remaining = $target !== null ? max(0.0, $target - $saved) : null;
        $pct = ($target !== null && $target > 0.00001) ? min(100.0, ($saved / $target) * 100.0) : null;

        $profit = $isAsset ? $project->profitOrLoss($quotePrice) : 0.0;
        $profitPct = $isAsset ? $project->profitOrLossPct($quotePrice) : null;

        return [
            'project' => $project,
            'is_asset' => $isAsset,
            'quote' => $quote,
            'quote_price' => $quotePrice,
            'saved' => $saved,
            'invested' => $invested,
            'profit' => $profit,
            'profit_pct' => $profitPct,
            'target' => $target,
            'remaining' => $remaining,
            'pct' => $pct,
            'is_complete' => $pct !== null && $pct >= 100,
        ];
    });

    $totalSaved = (float) $cofrinhoRows->sum('saved');
    $totalTarget = (float) $cofrinhoRows->sum(fn ($row) => (float) ($row['target'] ?? 0));
    $projectsWithTarget = $cofrinhoRows->filter(fn ($row) => $row['target'] !== null)->count();
    $completedProjects = $cofrinhoRows->filter(fn ($row) => $row['is_complete'])->count();
    $totalPct = $totalTarget > 0.00001 ? min(100.0, ($totalSaved / $totalTarget) * 100.0) : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <p class="small text-secondary mb-1">Metas e investimentos</p>
                <h2 class="h5 mb-0 cofrinhos-page-title">Cofrinhos</h2>
                <p class="small text-secondary mb-0 mt-1">Acompanhe objetivos, reservas e investimentos em Bitcoin, ações e renda fixa.</p>
            </div>
            <button type="button" class="btn btn-primary rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#modalCofrinhoCreate">
                Novo cofrinho
            </button>
        </div>
    </x-slot>

    <div class="py-4 cofrinhos-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <x-alert type="success" class="mb-4" :message="session('success')" />
            @endif
            @if (session('error'))
                <x-alert type="danger" class="mb-4" :message="session('error')" />
            @endif

            <section class="cofrinhos-hero card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5">
                            <span class="cofrinhos-hero__badge">Visão geral</span>
                            <h3 class="cofrinhos-hero__title h4 mt-3 mb-2">Transformem planos em patrimônio visível.</h3>
                            <p class="text-secondary mb-0">Cada cofrinho gerencia reservas em reais ou aplicações em ativos como Bitcoin, calculando preço médio e cotação em tempo real.</p>
                        </div>
                        <div class="col-lg-7">
                            <div class="cofrinhos-summary-grid">
                                <div class="cofrinhos-summary-card cofrinhos-summary-card--primary">
                                    <span class="cofrinhos-summary-card__label">Total patrimônio</span>
                                    <strong class="cofrinhos-summary-card__value duozen-privacy-blur">R$ {{ number_format($totalSaved, 2, ',', '.') }}</strong>
                                    @if($totalPct !== null)
                                        <span class="cofrinhos-summary-card__hint">{{ number_format($totalPct, 1, ',', '.') }}% das metas definidas</span>
                                    @else
                                        <span class="cofrinhos-summary-card__hint">Crie metas para acompanhar o avanço geral</span>
                                    @endif
                                </div>
                                <div class="cofrinhos-summary-card">
                                    <span class="cofrinhos-summary-card__label">Cofrinhos</span>
                                    <strong class="cofrinhos-summary-card__value">{{ $projects->count() }}</strong>
                                    <span class="cofrinhos-summary-card__hint">{{ $projectsWithTarget }} com meta</span>
                                </div>
                                <div class="cofrinhos-summary-card">
                                    <span class="cofrinhos-summary-card__label">Metas concluídas</span>
                                    <strong class="cofrinhos-summary-card__value">{{ $completedProjects }}</strong>
                                    <span class="cofrinhos-summary-card__hint">objetivos no alvo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Modal Novo Cofrinho --}}
            <div
                class="modal fade"
                id="modalCofrinhoCreate"
                tabindex="-1"
                aria-labelledby="modalCofrinhoCreateLabel"
                aria-hidden="true"
            >
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="modal-header cofrinhos-juros-modal-head border-0">
                            <h2 class="modal-title h5 mb-0" id="modalCofrinhoCreateLabel">Novo cofrinho</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <form method="post" action="{{ route('cofrinhos.store') }}">
                            @csrf
                            <input type="hidden" name="_cofrinho_form" value="create">
                            <div class="modal-body vstack gap-3">
                                <div>
                                    <x-input-label for="fp-create-name" value="Nome do cofrinho / objetivo" />
                                    <x-text-input id="fp-create-name" name="name" class="mt-1 rounded-3" :value="old('_cofrinho_form') === 'create' ? old('name') : ''" placeholder="Ex: Reserva Bitcoin, Viagem, Aposentadoria" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="fp-create-asset-type" value="Tipo de aplicação" />
                                    <select
                                        id="fp-create-asset-type"
                                        name="asset_type"
                                        class="form-select mt-1 rounded-3 js-asset-type-select"
                                        data-target-section="fp-create-asset-fields"
                                    >
                                        <option value="fiat" {{ old('asset_type', 'fiat') === 'fiat' ? 'selected' : '' }}>💵 Moeda tradicional / Reserva livre (R$)</option>
                                        <option value="crypto" {{ old('asset_type') === 'crypto' ? 'selected' : '' }}>₿ Criptomoeda (Bitcoin, etc.)</option>
                                        <option value="stock" {{ old('asset_type') === 'stock' ? 'selected' : '' }}>📈 Ação / ETF</option>
                                        <option value="fii" {{ old('asset_type') === 'fii' ? 'selected' : '' }}>🏢 Fundo Imobiliário (FII)</option>
                                        <option value="fixed_income" {{ old('asset_type') === 'fixed_income' ? 'selected' : '' }}>🏛️ Tesouro Direto / Renda Fixa</option>
                                        <option value="other" {{ old('asset_type') === 'other' ? 'selected' : '' }}>🏷️ Outro Ativo</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('asset_type')" class="mt-2" />
                                </div>

                                <div id="fp-create-asset-fields" class="vstack gap-3 p-3 rounded-3 border border-secondary-subtle bg-body-secondary {{ old('asset_type', 'fiat') === 'fiat' ? 'd-none' : '' }}">
                                    <div>
                                        <x-input-label for="fp-create-asset-code" value="Código / Ticker do ativo" />
                                        <x-text-input id="fp-create-asset-code" name="asset_code" class="mt-1 rounded-3 text-uppercase" :value="old('_cofrinho_form') === 'create' ? old('asset_code') : 'BTC'" placeholder="Ex: BTC, ETH, PETR4, Tesouro Selic 2029" />
                                        <span class="small text-secondary mt-1 d-block">Para Bitcoin, utilize <strong>BTC</strong>.</span>
                                        <x-input-error :messages="$errors->get('asset_code')" class="mt-2" />
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-6">
                                            <x-input-label for="fp-create-quantity" value="Quantidade inicial" />
                                            <x-text-input id="fp-create-quantity" name="asset_quantity" type="number" step="0.00000001" min="0" class="mt-1 rounded-3" :value="old('_cofrinho_form') === 'create' ? old('asset_quantity') : ''" placeholder="0.00000000" />
                                            <x-input-error :messages="$errors->get('asset_quantity')" class="mt-2" />
                                        </div>
                                        <div class="col-6">
                                            <x-input-label for="fp-create-avg-price" value="Preço médio inicial (R$)" />
                                            <x-text-input id="fp-create-avg-price" name="asset_avg_price" type="number" step="0.01" min="0" class="mt-1 rounded-3" :value="old('_cofrinho_form') === 'create' ? old('asset_avg_price') : ''" placeholder="0,00" />
                                            <x-input-error :messages="$errors->get('asset_avg_price')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <x-input-label for="fp-create-target" value="Meta financeira (R$, opcional)" />
                                    <x-text-input id="fp-create-target" name="target_amount" type="text" class="mt-1 rounded-3" :value="old('_cofrinho_form') === 'create' ? old('target_amount') : ''" placeholder="0,00" />
                                    <x-input-error :messages="$errors->get('target_amount')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="fp-create-color" value="Cor de identificação" />
                                    <input
                                        type="color"
                                        id="fp-create-color"
                                        name="color"
                                        value="{{ old('_cofrinho_form') === 'create' ? old('color', '#f59e0b') : '#f59e0b' }}"
                                        class="form-control form-control-color w-100 mt-1 rounded-3 category-form-color-input"
                                    >
                                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                                </div>
                            </div>
                            <div class="modal-footer border-secondary-subtle">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                                <x-primary-button class="rounded-pill px-4">Salvar cofrinho</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Modal Editar Cofrinho --}}
            <div
                class="modal fade"
                id="modalCofrinhoEdit"
                tabindex="-1"
                aria-labelledby="modalCofrinhoEditLabel"
                aria-hidden="true"
                data-cofrinhos-base-url="{{ url('/cofrinhos') }}"
            >
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="modal-header cofrinhos-juros-modal-head border-0">
                            <h2 class="modal-title h5 mb-0" id="modalCofrinhoEditLabel">Editar cofrinho</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <form id="cofrinho-edit-form" method="post" action="">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_cofrinho_form" value="edit">
                            <input type="hidden" name="cofrinho_id" id="fp-edit-cofrinho-id" value="{{ old('_cofrinho_form') === 'edit' ? old('cofrinho_id') : '' }}">
                            <div class="modal-body vstack gap-3">
                                <div>
                                    <x-input-label for="fp-edit-name" value="Nome do cofrinho" />
                                    <x-text-input id="fp-edit-name" name="name" class="mt-1 rounded-3" value="{{ old('_cofrinho_form') === 'edit' ? old('name') : '' }}" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="fp-edit-asset-type" value="Tipo de aplicação" />
                                    <select
                                        id="fp-edit-asset-type"
                                        name="asset_type"
                                        class="form-select mt-1 rounded-3 js-asset-type-select"
                                        data-target-section="fp-edit-asset-fields"
                                    >
                                        <option value="fiat">💵 Moeda tradicional / Reserva livre (R$)</option>
                                        <option value="crypto">₿ Criptomoeda (Bitcoin, etc.)</option>
                                        <option value="stock">📈 Ação / ETF</option>
                                        <option value="fii">🏢 Fundo Imobiliário (FII)</option>
                                        <option value="fixed_income">🏛️ Tesouro Direto / Renda Fixa</option>
                                        <option value="other">🏷️ Outro Ativo</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('asset_type')" class="mt-2" />
                                </div>

                                <div id="fp-edit-asset-fields" class="vstack gap-3 p-3 rounded-3 border border-secondary-subtle bg-body-secondary">
                                    <div>
                                        <x-input-label for="fp-edit-asset-code" value="Código / Ticker do ativo" />
                                        <x-text-input id="fp-edit-asset-code" name="asset_code" class="mt-1 rounded-3 text-uppercase" value="" placeholder="Ex: BTC, ETH, PETR4" />
                                        <x-input-error :messages="$errors->get('asset_code')" class="mt-2" />
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-6">
                                            <x-input-label for="fp-edit-quantity" value="Quantidade acumulada" />
                                            <x-text-input id="fp-edit-quantity" name="asset_quantity" type="number" step="0.00000001" min="0" class="mt-1 rounded-3" value="" placeholder="0.00000000" />
                                            <x-input-error :messages="$errors->get('asset_quantity')" class="mt-2" />
                                        </div>
                                        <div class="col-6">
                                            <x-input-label for="fp-edit-avg-price" value="Preço médio (R$)" />
                                            <x-text-input id="fp-edit-avg-price" name="asset_avg_price" type="number" step="0.01" min="0" class="mt-1 rounded-3" value="" placeholder="0,00" />
                                            <x-input-error :messages="$errors->get('asset_avg_price')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <x-input-label for="fp-edit-target" value="Meta financeira (R$, opcional)" />
                                    <x-text-input id="fp-edit-target" name="target_amount" type="text" class="mt-1 rounded-3" value="{{ old('_cofrinho_form') === 'edit' ? old('target_amount') : '' }}" placeholder="0,00" />
                                    <x-input-error :messages="$errors->get('target_amount')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="fp-edit-color" value="Cor" />
                                    <input
                                        type="color"
                                        id="fp-edit-color"
                                        name="color"
                                        value="{{ old('_cofrinho_form') === 'edit' ? old('color', '#0d9488') : '#0d9488' }}"
                                        class="form-control form-control-color w-100 mt-1 rounded-3 category-form-color-input"
                                    >
                                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                                </div>
                                <div class="rounded-3 border border-secondary-subtle bg-body-secondary p-3">
                                    <p class="dz-stat-label mb-1">Histórico em conta (R$)</p>
                                    <p class="h5 mb-0 fw-semibold" id="fp-edit-saved">R$ 0,00</p>
                                </div>
                            </div>
                            <div class="modal-footer border-secondary-subtle">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                                <x-primary-button class="rounded-pill px-4">Atualizar cofrinho</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Grid de Cofrinhos --}}
            <div class="row g-4 cofrinhos-grid">
                @forelse($cofrinhoRows as $row)
                    @php
                        $p = $row['project'];
                        $isAsset = $row['is_asset'];
                        $quote = $row['quote'];
                        $quotePrice = $row['quote_price'];
                        $saved = $row['saved'];
                        $invested = $row['invested'];
                        $profit = $row['profit'];
                        $profitPct = $row['profit_pct'];
                        $target = $row['target'];
                        $remaining = $row['remaining'];
                        $pct = $row['pct'];
                        $isComplete = $row['is_complete'];

                        $cardAccent = $p->color ?: ($p->isBitcoin() ? '#f59e0b' : '#0d9488');
                    @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 cofrinhos-project-card h-100" style="--cofrinho-accent: {{ e($cardAccent) }}">
                            <div class="cofrinhos-project-card__accent" aria-hidden="true"></div>
                            <div class="cofrinhos-project-card__top">
                                <div class="cofrinhos-project-card__avatar" aria-hidden="true">
                                    @if($p->isBitcoin())
                                        <span class="fs-4 fw-bold">₿</span>
                                    @elseif($p->asset_type === 'crypto')
                                        <span class="fs-5 fw-bold">⟠</span>
                                    @elseif($p->asset_type === 'stock')
                                        <span class="fs-5">📈</span>
                                    @elseif($p->asset_type === 'fii')
                                        <span class="fs-5">🏢</span>
                                    @elseif($p->asset_type === 'fixed_income')
                                        <span class="fs-5">🏛️</span>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 48 48">
                                            <ellipse cx="24" cy="29" rx="16" ry="11" fill="currentColor" opacity="0.18" />
                                            <path d="M12 27c0-7.2 6.2-13 14-13 6.8 0 12.6 4.4 13.8 10.2l3 1.1a2 2 0 011.2 1.8v3.1a2 2 0 01-2 2h-2.4a13.8 13.8 0 01-3.6 4.1v3.2a2 2 0 01-2 2h-3.1a2 2 0 01-1.9-1.4l-.5-1.4a20.3 20.3 0 01-6.9 0l-.5 1.4a2 2 0 01-1.9 1.4H16a2 2 0 01-2-2v-3.3A12.8 12.8 0 0112 27z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
                                            <path d="M20 14c1.4-3.4 5.9-5 9.8-3" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                            <circle cx="31" cy="24" r="1.8" fill="currentColor" />
                                            <path d="M21 23h6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <h3 class="cofrinhos-project-card__title mb-1 text-truncate">{{ $p->name }}</h3>
                                        @if($p->isBitcoin())
                                            <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--crypto">₿ Bitcoin</span>
                                        @elseif($isAsset)
                                            <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--{{ str_replace('_', '-', $p->asset_type) }}">{{ $p->asset_code ?: $p->assetTypeLabel() }}</span>
                                        @elseif($isComplete)
                                            <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--done">Concluído</span>
                                        @elseif($target !== null)
                                            <span class="cofrinhos-project-card__badge">Com meta</span>
                                        @else
                                            <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--muted">Livre</span>
                                        @endif
                                    </div>
                                    @if($isAsset)
                                        <p class="small text-secondary mb-0">Preço Médio: <strong class="text-body duozen-privacy-blur">R$ {{ number_format((float) $p->asset_avg_price, 2, ',', '.') }}</strong></p>
                                    @elseif($target !== null)
                                        <p class="small text-secondary mb-0">Meta de <span class="duozen-privacy-blur">R$ {{ number_format($target, 2, ',', '.') }}</span></p>
                                    @else
                                        <p class="small text-secondary mb-0">Sem valor-alvo definido</p>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body p-4 cofrinhos-project-card__body">
                                @if($isAsset)
                                    {{-- Seção de Ativo / Cripto --}}
                                    <div class="d-flex align-items-baseline justify-content-between gap-2 mb-1">
                                        <p class="dz-stat-label mb-0">Quantidade acumulada</p>
                                        @if($quote)
                                            <div class="cofrinhos-quote-pill" id="quote-pill-{{ $p->id }}">
                                                <span>Cotação: <strong id="quote-price-{{ $p->id }}">{{ $quote->formattedPrice() }}</strong></span>
                                                <button
                                                    type="button"
                                                    class="js-btn-refresh-quote"
                                                    data-asset-type="{{ $p->asset_type }}"
                                                    data-asset-code="{{ $p->asset_code }}"
                                                    data-asset-quantity="{{ (float) $p->asset_quantity }}"
                                                    data-asset-avg-price="{{ (float) $p->asset_avg_price }}"
                                                    data-target-price-id="quote-price-{{ $p->id }}"
                                                    data-cofrinho-id="{{ $p->id }}"
                                                    title="Atualizar cotação agora"
                                                >⟳</button>
                                            </div>
                                        @endif
                                    </div>
                                    <p class="cofrinhos-project-card__amount mb-3 duozen-privacy-blur">
                                        {{ rtrim(rtrim(number_format((float) $p->asset_quantity, 8, ',', '.'), '0'), ',') ?: '0' }} <span class="fs-6 fw-semibold text-secondary">{{ $p->assetUnitLabel() }}</span>
                                    </p>

                                    <div class="cofrinhos-asset-stats-grid mb-3">
                                        <div class="cofrinhos-mini-stat">
                                            <span>Patrimônio atual</span>
                                            <strong class="duozen-privacy-blur text-body" id="estimated-val-{{ $p->id }}">R$ {{ number_format($saved, 2, ',', '.') }}</strong>
                                        </div>
                                        <div class="cofrinhos-mini-stat">
                                            <span>Total investido</span>
                                            <strong class="duozen-privacy-blur">R$ {{ number_format($invested, 2, ',', '.') }}</strong>
                                        </div>
                                    </div>

                                    @if($invested > 0)
                                        <div id="profit-container-{{ $p->id }}" class="d-flex align-items-center justify-content-between rounded-3 border p-2 px-3 mb-3 {{ $profit >= 0 ? 'border-success-subtle bg-success-subtle' : 'border-danger-subtle bg-danger-subtle' }}">
                                            <span class="small fw-semibold text-secondary">Rentabilidade</span>
                                            <span id="profit-badge-{{ $p->id }}" class="small fw-bold {{ $profit >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $profit >= 0 ? '+' : '' }}R$ {{ number_format($profit, 2, ',', '.') }}
                                                @if($profitPct !== null)
                                                    ({{ ($profitPct >= 0 ? '+' : '') . number_format($profitPct, 2, ',', '.') }}%)
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                @else
                                    {{-- Seção Tradicional em R$ --}}
                                    <p class="dz-stat-label mb-1">Guardado agora</p>
                                    <p class="cofrinhos-project-card__amount mb-3 duozen-privacy-blur">R$ {{ number_format($saved, 2, ',', '.') }}</p>
                                    @if($target !== null)
                                        @if($pct !== null)
                                            <div class="cofrinhos-progress mb-3" aria-label="Progresso de {{ number_format((float) $pct, 1, ',', '.') }}%">
                                                <div class="cofrinhos-progress__bar {{ $isComplete ? 'cofrinhos-progress__bar--done' : '' }}" style="width: {{ number_format((float) $pct, 2, '.', '') }}%"></div>
                                            </div>
                                        @endif
                                        <div class="cofrinhos-project-card__metrics mb-3">
                                            <div class="cofrinhos-mini-stat">
                                                <span>Falta</span>
                                                <strong class="duozen-privacy-blur">R$ {{ number_format((float) $remaining, 2, ',', '.') }}</strong>
                                            </div>
                                            <div class="cofrinhos-mini-stat">
                                                <span>Avanço</span>
                                                <strong>{{ number_format((float) $pct, 1, ',', '.') }}%</strong>
                                            </div>
                                        </div>
                                    @else
                                        <div class="cofrinhos-no-target mb-3">
                                            Use como reserva livre ou edite o cofrinho para definir uma meta.
                                        </div>
                                    @endif
                                @endif

                                {{-- Ações Primárias --}}
                                <div class="cofrinhos-project-card__primary-actions">
                                    @if($isAsset)
                                        <button
                                            type="button"
                                            class="btn btn-success btn-sm rounded-pill px-3"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalCofrinhoAssetAporte{{ $p->id }}"
                                        >+ Aporte {{ $p->isBitcoin() ? 'BTC' : $p->assetUnitLabel() }}</button>
                                    @else
                                        <a
                                            href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'aporte']) }}"
                                            class="btn btn-success btn-sm rounded-pill px-3"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="Ir ao painel com despesa em Investimentos e este cofrinho"
                                        >+ Aporte</a>
                                    @endif
                                    <a
                                        href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'retirada']) }}"
                                        class="btn btn-outline-danger btn-sm rounded-pill px-3"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Ir ao painel com receita em Retirada de cofrinho e este cofrinho"
                                    >− Retirada</a>
                                </div>

                                {{-- Barra secundária --}}
                                <div class="cofrinhos-project-card__toolbar pt-3 mt-3">
                                    @if(! $isAsset)
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm rounded-pill"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalCofrinhoJuros{{ $p->id }}"
                                        >
                                            + Juros
                                        </button>
                                    @endif
                                    <a
                                        href="{{ route('cofrinhos.movements', $p) }}"
                                        class="btn btn-outline-dark btn-sm rounded-pill"
                                    >
                                        Movimentações
                                    </a>
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm rounded-pill js-cofrinho-edit-open"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalCofrinhoEdit"
                                        data-cofrinho-id="{{ $p->id }}"
                                        data-cofrinho-name="{{ e($p->name) }}"
                                        data-cofrinho-asset-type="{{ $p->asset_type ?: 'fiat' }}"
                                        data-cofrinho-asset-code="{{ e($p->asset_code ?: '') }}"
                                        data-cofrinho-asset-quantity="{{ $p->asset_quantity !== null ? (string) $p->asset_quantity : '' }}"
                                        data-cofrinho-asset-avg-price="{{ $p->asset_avg_price !== null ? (string) $p->asset_avg_price : '' }}"
                                        data-cofrinho-target="{{ $p->target_amount !== null ? number_format((float) $p->target_amount, 2, ',', '.') : '' }}"
                                        data-cofrinho-color="{{ e($cardAccent) }}"
                                        data-cofrinho-saved="{{ number_format((float) $p->savedProgress(), 2, ',', '.') }}"
                                    >Editar</button>
                                    <form action="{{ route('cofrinhos.destroy', $p) }}" method="post" class="d-inline" data-confirm-title="Excluir cofrinho" data-confirm="Excluir este cofrinho? Movimentações vinculadas podem afetar o histórico." data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill">Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Aporte em Ativo (se for ativo) --}}
                    @if($isAsset)
                        <div
                            class="modal fade"
                            id="modalCofrinhoAssetAporte{{ $p->id }}"
                            tabindex="-1"
                            aria-labelledby="modalCofrinhoAssetAporteLabel{{ $p->id }}"
                            aria-hidden="true"
                        >
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                    <div class="modal-header cofrinhos-juros-modal-head border-0">
                                        <h2 class="modal-title h5 mb-0" id="modalCofrinhoAssetAporteLabel{{ $p->id }}">Aporte — {{ $p->name }}</h2>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                    </div>
                                    <form method="post" action="{{ route('cofrinhos.asset-aporte.store', $p) }}" class="js-asset-aporte-form" data-cofrinho-id="{{ $p->id }}">
                                        @csrf
                                        <div class="modal-body vstack gap-3">
                                            {{-- Posicao Atual --}}
                                            <div class="p-3 rounded-3 border border-secondary-subtle bg-body-secondary">
                                                <div class="row g-2 text-center">
                                                    <div class="col-6">
                                                        <span class="small text-secondary d-block">Saldo atual</span>
                                                        <strong class="fs-6" id="sim-cur-qty-{{ $p->id }}">{{ rtrim(rtrim(number_format((float) $p->asset_quantity, 8, ',', '.'), '0'), ',') ?: '0' }} {{ $p->assetUnitLabel() }}</strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <span class="small text-secondary d-block">Preço Médio atual</span>
                                                        <strong class="fs-6" id="sim-cur-pm-{{ $p->id }}">R$ {{ number_format((float) $p->asset_avg_price, 2, ',', '.') }}</strong>
                                                    </div>
                                                </div>
                                            </div>

                                            <div>
                                                <x-input-label for="asset_amount_{{ $p->id }}" value="Valor investido (R$)" />
                                                <x-text-input
                                                    id="asset_amount_{{ $p->id }}"
                                                    name="amount"
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    class="mt-1 rounded-3 js-aporte-amount"
                                                    placeholder="0,00"
                                                    required
                                                />
                                            </div>

                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <x-input-label for="asset_price_{{ $p->id }}" value="Cotação / Preço (R$)" />
                                                    <x-text-input
                                                        id="asset_price_{{ $p->id }}"
                                                        name="asset_unit_price"
                                                        type="number"
                                                        step="0.01"
                                                        min="0.0001"
                                                        class="mt-1 rounded-3 js-aporte-price"
                                                        value="{{ $quotePrice !== null ? number_format($quotePrice, 2, '.', '') : '' }}"
                                                        placeholder="Cotação no momento"
                                                    />
                                                </div>
                                                <div class="col-6">
                                                    <x-input-label for="asset_quantity_{{ $p->id }}" value="Quantidade ({{ $p->assetUnitLabel() }})" />
                                                    <x-text-input
                                                        id="asset_quantity_{{ $p->id }}"
                                                        name="asset_quantity"
                                                        type="number"
                                                        step="0.00000001"
                                                        min="0.00000001"
                                                        class="mt-1 rounded-3 js-aporte-quantity"
                                                        placeholder="0.00000000"
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            {{-- Simulador dinamico do Novo Preco Medio --}}
                                            <div class="cofrinhos-pm-sim-card">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <span class="small fw-bold text-primary">Simulação do Novo Preço Médio</span>
                                                    <span class="badge rounded-pill text-bg-primary-subtle text-primary border border-primary-subtle">Automático</span>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <span class="small text-secondary d-block">Novo Saldo Total</span>
                                                        <strong class="fs-6" id="sim-new-qty-{{ $p->id }}">—</strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <span class="small text-secondary d-block">Novo Preço Médio</span>
                                                        <strong class="fs-6 text-primary" id="sim-new-pm-{{ $p->id }}">—</strong>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <x-input-label for="asset_date_{{ $p->id }}" value="Data do aporte" />
                                                    <x-text-input
                                                        id="asset_date_{{ $p->id }}"
                                                        name="date"
                                                        type="date"
                                                        class="mt-1 rounded-3"
                                                        required
                                                        value="{{ now()->toDateString() }}"
                                                    />
                                                </div>
                                                <div class="col-6">
                                                    <x-input-label for="asset_account_{{ $p->id }}" value="Debitar de conta (opcional)" />
                                                    <select id="asset_account_{{ $p->id }}" name="account_id" class="form-select mt-1 rounded-3">
                                                        <option value="">Não debitar conta</option>
                                                        @foreach($regularAccounts ?? [] as $acc)
                                                            <option value="{{ $acc->id }}">{{ $acc->name }} (R$ {{ number_format((float) $acc->balance, 2, ',', '.') }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div>
                                                <x-input-label for="asset_note_{{ $p->id }}" value="Observação (opcional)" />
                                                <x-text-input
                                                    id="asset_note_{{ $p->id }}"
                                                    name="note"
                                                    type="text"
                                                    class="mt-1 rounded-3"
                                                    placeholder="Ex: Compra fracionada na corretora"
                                                />
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
                    @else
                        {{-- Modal de Juros (apenas para cofrinhos fiat tradicionais) --}}
                        <div
                            class="modal fade"
                            id="modalCofrinhoJuros{{ $p->id }}"
                            tabindex="-1"
                            aria-labelledby="modalCofrinhoJurosLabel{{ $p->id }}"
                            aria-hidden="true"
                        >
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                    <div class="modal-header cofrinhos-juros-modal-head border-0">
                                        <h2 class="modal-title h5 mb-0" id="modalCofrinhoJurosLabel{{ $p->id }}">Lançar juros — {{ $p->name }}</h2>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                    </div>
                                    <form method="post" action="{{ route('cofrinhos.interest.store', $p) }}">
                                        @csrf
                                        <div class="modal-body vstack gap-3">
                                            <p class="small text-secondary mb-0">
                                                Juros aumentam o progresso do cofrinho, sem gerar lançamento em conta.
                                            </p>
                                            <div>
                                                <x-input-label for="interest_amount_{{ $p->id }}" value="Valor (R$)" />
                                                <x-text-input
                                                    id="interest_amount_{{ $p->id }}"
                                                    name="amount"
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    class="mt-1 rounded-3"
                                                    required
                                                    value="{{ old('amount') }}"
                                                />
                                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                            </div>
                                            <div>
                                                <x-input-label for="interest_date_{{ $p->id }}" value="Data" />
                                                <x-text-input
                                                    id="interest_date_{{ $p->id }}"
                                                    name="date"
                                                    type="date"
                                                    class="mt-1 rounded-3"
                                                    required
                                                    value="{{ old('date', now()->toDateString()) }}"
                                                />
                                                <x-input-error :messages="$errors->get('date')" class="mt-2" />
                                            </div>
                                            <div>
                                                <x-input-label for="interest_note_{{ $p->id }}" value="Observação (opcional)" />
                                                <x-text-input
                                                    id="interest_note_{{ $p->id }}"
                                                    name="note"
                                                    type="text"
                                                    class="mt-1 rounded-3"
                                                    value="{{ old('note') }}"
                                                />
                                                <x-input-error :messages="$errors->get('note')" class="mt-2" />
                                            </div>
                                        </div>
                                        <div class="modal-footer border-secondary-subtle">
                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                                            <x-primary-button class="rounded-pill px-4">Salvar juros</x-primary-button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="col-12">
                        <x-cofrinho-promo variant="hero" :centered="true" class="mb-2" />
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const editModal = document.getElementById('modalCofrinhoEdit');
                const editForm = document.getElementById('cofrinho-edit-form');
                const createModal = document.getElementById('modalCofrinhoCreate');
                const bs = window.bootstrap;

                // Toggle dos campos de ativo no create/edit conforme tipo
                document.querySelectorAll('.js-asset-type-select').forEach(function (select) {
                    const targetId = select.getAttribute('data-target-section');
                    const targetSection = document.getElementById(targetId);
                    if (!targetSection) return;

                    select.addEventListener('change', function () {
                        if (this.value === 'fiat') {
                            targetSection.classList.add('d-none');
                        } else {
                            targetSection.classList.remove('d-none');
                            if (this.value === 'crypto') {
                                const codeInput = targetSection.querySelector('input[name="asset_code"]');
                                if (codeInput && !codeInput.value) codeInput.value = 'BTC';
                            }
                        }
                    });
                });

                function setCofrinhoEditAction(id) {
                    if (!editForm || !editModal) return;
                    const base = editModal.getAttribute('data-cofrinhos-base-url') || '';
                    editForm.action = base.replace(/\/$/, '') + '/' + encodeURIComponent(String(id));
                }

                function fillCofrinhoEditFromPayload(p) {
                    if (!editForm || !p) return;
                    const idEl = document.getElementById('fp-edit-cofrinho-id');
                    const nameEl = document.getElementById('fp-edit-name');
                    const assetTypeEl = document.getElementById('fp-edit-asset-type');
                    const assetCodeEl = document.getElementById('fp-edit-asset-code');
                    const assetQuantityEl = document.getElementById('fp-edit-quantity');
                    const assetAvgPriceEl = document.getElementById('fp-edit-avg-price');
                    const targetEl = document.getElementById('fp-edit-target');
                    const colorEl = document.getElementById('fp-edit-color');
                    const savedEl = document.getElementById('fp-edit-saved');
                    const assetSection = document.getElementById('fp-edit-asset-fields');

                    if (idEl) idEl.value = String(p.id);
                    if (nameEl) nameEl.value = p.name || '';
                    if (assetTypeEl) {
                        assetTypeEl.value = p.asset_type || 'fiat';
                        if (assetSection) {
                            if (p.asset_type && p.asset_type !== 'fiat') {
                                assetSection.classList.remove('d-none');
                            } else {
                                assetSection.classList.add('d-none');
                            }
                        }
                    }
                    if (assetCodeEl) assetCodeEl.value = p.asset_code || (p.asset_type === 'crypto' ? 'BTC' : '');
                    if (assetQuantityEl) assetQuantityEl.value = p.asset_quantity != null ? String(p.asset_quantity) : '';
                    if (assetAvgPriceEl) assetAvgPriceEl.value = p.asset_avg_price != null ? String(p.asset_avg_price) : '';
                    if (targetEl) targetEl.value = p.target_amount != null && p.target_amount !== '' ? String(p.target_amount) : '';
                    if (colorEl) colorEl.value = p.color || '#0d9488';
                    if (savedEl) savedEl.textContent = 'R$ ' + String(p.saved || '0,00');
                    setCofrinhoEditAction(p.id);
                }

                if (editModal && editForm) {
                    editModal.addEventListener('show.bs.modal', function (ev) {
                        const btn = ev.relatedTarget;
                        if (!btn || !btn.classList || !btn.classList.contains('js-cofrinho-edit-open')) return;
                        fillCofrinhoEditFromPayload({
                            id: btn.getAttribute('data-cofrinho-id'),
                            name: btn.getAttribute('data-cofrinho-name') || '',
                            asset_type: btn.getAttribute('data-cofrinho-asset-type') || 'fiat',
                            asset_code: btn.getAttribute('data-cofrinho-asset-code') || '',
                            asset_quantity: btn.getAttribute('data-cofrinho-asset-quantity') || '',
                            asset_avg_price: btn.getAttribute('data-cofrinho-asset-avg-price') || '',
                            target_amount: btn.getAttribute('data-cofrinho-target') || '',
                            color: btn.getAttribute('data-cofrinho-color') || '#0d9488',
                            saved: btn.getAttribute('data-cofrinho-saved') || '0,00',
                        });
                    });
                }

                // Sincronizador de Aportes em Ativos e Simulador de Preço Médio
                document.querySelectorAll('.js-asset-aporte-form').forEach(function (form) {
                    const cofrinhoId = form.getAttribute('data-cofrinho-id');
                    const amountInput = form.querySelector('.js-aporte-amount');
                    const priceInput = form.querySelector('.js-aporte-price');
                    const quantityInput = form.querySelector('.js-aporte-quantity');

                    const curQtyEl = document.getElementById('sim-cur-qty-' + cofrinhoId);
                    const curPmEl = document.getElementById('sim-cur-pm-' + cofrinhoId);
                    const newQtyEl = document.getElementById('sim-new-qty-' + cofrinhoId);
                    const newPmEl = document.getElementById('sim-new-pm-' + cofrinhoId);

                    let q0 = 0;
                    let pm0 = 0;
                    if (curQtyEl) {
                        const txt = curQtyEl.textContent.trim().split(' ')[0].replace(/\./g, '').replace(',', '.');
                        q0 = parseFloat(txt) || 0;
                    }
                    if (curPmEl) {
                        const txt = curPmEl.textContent.replace('R$', '').trim().replace(/\./g, '').replace(',', '.');
                        pm0 = parseFloat(txt) || 0;
                    }

                    function recomputeSim() {
                        const amt = parseFloat(amountInput.value) || 0;
                        const qty = parseFloat(quantityInput.value) || 0;

                        if (amt > 0 && qty > 0) {
                            let newQty = q0 + qty;
                            let newTotal = (q0 * pm0) + amt;
                            let newPm = newQty > 0 ? (newTotal / newQty) : 0;

                            if (newQtyEl) newQtyEl.textContent = newQty.toLocaleString('pt-BR', { maximumFractionDigits: 8 });
                            if (newPmEl) newPmEl.textContent = 'R$ ' + newPm.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        } else {
                            if (newQtyEl) newQtyEl.textContent = '—';
                            if (newPmEl) newPmEl.textContent = '—';
                        }
                    }

                    if (amountInput && priceInput && quantityInput) {
                        amountInput.addEventListener('input', function () {
                            const amt = parseFloat(this.value) || 0;
                            const prc = parseFloat(priceInput.value) || 0;
                            if (amt > 0 && prc > 0) {
                                quantityInput.value = (amt / prc).toFixed(8).replace(/\.?0+$/, '');
                            }
                            recomputeSim();
                        });

                        priceInput.addEventListener('input', function () {
                            const prc = parseFloat(this.value) || 0;
                            const amt = parseFloat(amountInput.value) || 0;
                            if (amt > 0 && prc > 0) {
                                quantityInput.value = (amt / prc).toFixed(8).replace(/\.?0+$/, '');
                            }
                            recomputeSim();
                        });

                        quantityInput.addEventListener('input', function () {
                            const qty = parseFloat(this.value) || 0;
                            const prc = parseFloat(priceInput.value) || 0;
                            if (qty > 0 && prc > 0) {
                                amountInput.value = (qty * prc).toFixed(2);
                            }
                            recomputeSim();
                        });
                    }
                });

                // Atualizacao de Cotacao via AJAX
                document.querySelectorAll('.js-btn-refresh-quote').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const type = this.getAttribute('data-asset-type') || 'crypto';
                        const code = this.getAttribute('data-asset-code') || 'BTC';
                        const targetPriceId = this.getAttribute('data-target-price-id');
                        const cofrinhoId = this.getAttribute('data-cofrinho-id');
                        const qty = parseFloat(this.getAttribute('data-asset-quantity')) || 0;
                        const pm = parseFloat(this.getAttribute('data-asset-avg-price')) || 0;
                        const priceEl = document.getElementById(targetPriceId);

                        this.classList.add('fa-spin');
                        fetch(`{{ route('cofrinhos.quote') }}?type=${encodeURIComponent(type)}&code=${encodeURIComponent(code)}&fresh=1`)
                            .then(res => res.json())
                            .then(res => {
                                if (res.success && res.data) {
                                    const newPrice = res.data.price;
                                    if (priceEl) priceEl.textContent = res.data.formatted_price;

                                    // 1. Atualiza patrimonio estimado
                                    const estValEl = document.getElementById('estimated-val-' + cofrinhoId);
                                    if (estValEl && qty > 0) {
                                        const newEst = qty * newPrice;
                                        estValEl.textContent = 'R$ ' + newEst.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }

                                    // 2. Atualiza rentabilidade
                                    const profitEl = document.getElementById('profit-badge-' + cofrinhoId);
                                    const profitContainer = document.getElementById('profit-container-' + cofrinhoId);
                                    if (profitEl && qty > 0 && pm > 0) {
                                        const profit = (newPrice - pm) * qty;
                                        const profitPct = ((newPrice / pm) - 1) * 100;
                                        const prefix = profit >= 0 ? '+' : '';
                                        profitEl.textContent = `${prefix}R$ ${Math.abs(profit).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} (${prefix}${profitPct.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%)`;
                                        profitEl.className = `small fw-bold ${profit >= 0 ? 'text-success' : 'text-danger'}`;
                                        if (profitContainer) {
                                            profitContainer.className = `d-flex align-items-center justify-content-between rounded-3 border p-2 px-3 mb-3 ${profit >= 0 ? 'border-success-subtle bg-success-subtle' : 'border-danger-subtle bg-danger-subtle'}`;
                                        }
                                    }

                                    // 3. Atualiza preco pre-preenchido no modal de aporte
                                    const modalPriceInput = document.getElementById('asset_price_' + cofrinhoId);
                                    if (modalPriceInput) {
                                        modalPriceInput.value = newPrice.toFixed(2);
                                        modalPriceInput.dispatchEvent(new Event('input', { bubbles: true }));
                                    }
                                }
                            })
                            .catch(err => console.debug('Quote fetch failed:', err))
                            .finally(() => this.classList.remove('fa-spin'));
                    });
                });

                if (bs && bs.Modal) {
                    @if ($openCofrinhoCreate)
                        const m = createModal ? bs.Modal.getOrCreateInstance(createModal) : null;
                        if (m) m.show();
                    @endif
                    @if ($openCofrinhoEdit && $jsOpenEditPayload)
                        fillCofrinhoEditFromPayload(@json($jsOpenEditPayload));
                        const em = editModal ? bs.Modal.getOrCreateInstance(editModal) : null;
                        if (em) em.show();
                    @endif
                    @if ($prefillPayload && ! $openCofrinhoEdit && ! $openCofrinhoCreate)
                        fillCofrinhoEditFromPayload(@json($prefillPayload));
                        const em2 = editModal ? bs.Modal.getOrCreateInstance(editModal) : null;
                        if (em2) em2.show();
                    @endif
                }
            })();
        </script>
    @endpush
</x-app-layout>
