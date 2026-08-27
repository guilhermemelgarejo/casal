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
            'is_active' => (bool) $prefillEditProject->is_active,
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
            'is_active' => old('is_active', '1') === '1' || old('is_active') === 1 || old('is_active') === true,
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
            'is_active' => (bool) $project->is_active,
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
    $activeRows = $cofrinhoRows->where('is_active', true);
    $inactiveRows = $cofrinhoRows->where('is_active', false);
    $activeCount = $activeRows->count();
    $inactiveCount = $inactiveRows->count();
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
                                    <strong class="cofrinhos-summary-card__value">{{ $activeCount }}</strong>
                                    <span class="cofrinhos-summary-card__hint">
                                        @if($inactiveCount > 0)
                                            {{ $projectsWithTarget }} com meta · {{ $inactiveCount }} {{ $inactiveCount === 1 ? 'inativo' : 'inativos' }}
                                        @else
                                            {{ $projectsWithTarget }} com meta
                                        @endif
                                    </span>
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

                                <div class="form-check form-switch pt-1">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="fp-create-is-active" value="1" @checked(old('_cofrinho_form') === 'create' ? old('is_active', true) : true)>
                                    <label class="form-check-label fw-semibold" for="fp-create-is-active">Cofrinho ativo</label>
                                    <div class="form-text mt-0">Cofrinhos desativados não aparecem em novos lançamentos e aportes.</div>
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

                                <div class="form-check form-switch pt-1">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="fp-edit-is-active" value="1" @checked(old('_cofrinho_form') === 'edit' ? old('is_active', true) : true)>
                                    <label class="form-check-label fw-semibold" for="fp-edit-is-active">Cofrinho ativo</label>
                                    <div class="form-text mt-0">Cofrinhos desativados não aparecem em novos lançamentos e aportes.</div>
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

            {{-- Grid de Cofrinhos Ativos --}}
            <div class="row g-4 cofrinhos-grid">
                @forelse($activeRows as $row)
                    @include('financial-projects.partials.project-card', ['row' => $row])
                @empty
                    @if($inactiveRows->isEmpty())
                        <div class="col-12">
                            <x-cofrinho-promo variant="hero" :centered="true" class="mb-2" />
                        </div>
                    @else
                        <div class="col-12">
                            <div class="card border-0 shadow-sm p-4 p-md-5 text-center bg-body-secondary rounded-4">
                                <div class="mb-3 fs-1 opacity-50">🔒</div>
                                <h4 class="h5 fw-bold mb-2">Nenhum cofrinho ativo no momento</h4>
                                <p class="text-secondary mb-3 small">Todos os seus cofrinhos estão desativados. Você pode reativá-los abaixo ou criar um novo cofrinho.</p>
                                <div>
                                    <button type="button" class="btn btn-primary rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#modalCofrinhoCreate">
                                        Novo cofrinho
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforelse
            </div>

            {{-- Bloco Fechado de Cofrinhos Desativados --}}
            @if($inactiveRows->isNotEmpty())
                <div class="cofrinhos-inactive-section mt-5 pt-4 border-top border-secondary-subtle">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <button
                            class="btn btn-link text-decoration-none p-0 d-flex align-items-center gap-2 text-secondary fw-semibold cofrinhos-collapse-toggle collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#cofrinhos-desativados-collapse"
                            aria-expanded="false"
                            aria-controls="cofrinhos-desativados-collapse"
                        >
                            <svg class="cofrinhos-collapse-toggle__arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                            <span class="fs-6">Cofrinhos desativados</span>
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">{{ $inactiveRows->count() }}</span>
                        </button>
                    </div>

                    <div class="collapse" id="cofrinhos-desativados-collapse">
                        <div class="row g-4 cofrinhos-grid pt-2">
                            @foreach($inactiveRows as $row)
                                @include('financial-projects.partials.project-card', ['row' => $row])
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
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
                    const isActiveEl = document.getElementById('fp-edit-is-active');
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
                    if (isActiveEl) {
                        isActiveEl.checked = p.is_active === undefined ? true : (p.is_active === true || p.is_active === 1 || p.is_active === '1');
                    }
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
                            is_active: btn.getAttribute('data-cofrinho-is-active') !== '0',
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
