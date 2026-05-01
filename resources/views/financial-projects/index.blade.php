@php
    $openCofrinhoCreate = old('_cofrinho_form') === 'create' || (request()->boolean('novo') && old('_cofrinho_form') !== 'edit');
    $openCofrinhoEdit = old('_cofrinho_form') === 'edit';
    $fpCreateColor = old('color', '#0d9488');
    $prefillPayload = null;
    if (isset($prefillEditProject) && $prefillEditProject && old('_cofrinho_form') !== 'create') {
        $prefillPayload = [
            'id' => $prefillEditProject->id,
            'name' => $prefillEditProject->name,
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
            'target_amount' => old('target_amount') !== null ? (string) old('target_amount') : '',
            'color' => (string) old('color', '#0d9488'),
            'saved' => $cofrinhoEditSavedForJs,
        ];
    }

    $cofrinhoRows = $projects->map(function ($project) {
        $saved = (float) $project->savedProgress();
        $target = $project->target_amount !== null ? (float) $project->target_amount : null;
        $remaining = $target !== null ? max(0.0, $target - $saved) : null;
        $pct = ($target !== null && $target > 0.00001) ? min(100.0, ($saved / $target) * 100.0) : null;

        return [
            'project' => $project,
            'saved' => $saved,
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
                <p class="small text-secondary mb-1">Metas compartilhadas</p>
                <h2 class="h5 mb-0 cofrinhos-page-title">Cofrinhos</h2>
                <p class="small text-secondary mb-0 mt-1">Acompanhe aportes, retiradas e juros por objetivo.</p>
            </div>
            <button type="button" class="btn btn-primary rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#modalCofrinhoCreate">
                Novo cofrinho
            </button>
        </div>
    </x-slot>

    <div class="py-4 cofrinhos-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3 rounded-4" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3 rounded-4" role="alert">
                    <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <span class="pt-1">{{ session('error') }}</span>
                </div>
            @endif

            <section class="cofrinhos-hero card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5">
                            <span class="cofrinhos-hero__badge">Visão geral</span>
                            <h3 class="cofrinhos-hero__title h4 mt-3 mb-2">Transformem planos em metas visíveis.</h3>
                            <p class="text-secondary mb-0">Cada cofrinho soma aportes em Investimentos, desconta retiradas e pode receber juros sem movimentar uma conta.</p>
                        </div>
                        <div class="col-lg-7">
                            <div class="cofrinhos-summary-grid">
                                <div class="cofrinhos-summary-card cofrinhos-summary-card--primary">
                                    <span class="cofrinhos-summary-card__label">Total guardado</span>
                                    <strong class="cofrinhos-summary-card__value">R$ {{ number_format($totalSaved, 2, ',', '.') }}</strong>
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
                    <div class="cofrinhos-hero__tip mt-4">
                        <span class="cofrinhos-hero__tip-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                        <span>No painel, use <strong>+ Despesa</strong> com categoria <strong>Investimentos</strong> para aporte ou <strong>+ Receita</strong> com <strong>Retirada de cofrinho</strong> para retirada.</span>
                    </div>
                </div>
            </section>

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
                                <p class="small text-secondary mb-0">Defina nome, meta opcional e cor para identificar nos lançamentos.</p>
                                <div>
                                    <x-input-label for="fp-create-name" value="Nome" />
                                    <x-text-input id="fp-create-name" name="name" class="mt-1 rounded-3" :value="old('_cofrinho_form') === 'create' ? old('name') : ''" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="fp-create-target" value="Meta (R$, opcional)" />
                                    <x-text-input id="fp-create-target" name="target_amount" type="text" class="mt-1 rounded-3" :value="old('_cofrinho_form') === 'create' ? old('target_amount') : ''" placeholder="0,00" />
                                    <x-input-error :messages="$errors->get('target_amount')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="fp-create-color" value="Cor" />
                                    <input
                                        type="color"
                                        id="fp-create-color"
                                        name="color"
                                        value="{{ old('_cofrinho_form') === 'create' ? old('color', '#0d9488') : $fpCreateColor }}"
                                        class="form-control form-control-color w-100 mt-1 rounded-3 category-form-color-input"
                                    >
                                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                                </div>
                            </div>
                            <div class="modal-footer border-secondary-subtle">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                                <x-primary-button class="rounded-pill px-4">Salvar</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

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
                                    <x-input-label for="fp-edit-name" value="Nome" />
                                    <x-text-input id="fp-edit-name" name="name" class="mt-1 rounded-3" value="{{ old('_cofrinho_form') === 'edit' ? old('name') : '' }}" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="fp-edit-target" value="Meta (R$, opcional)" />
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
                                    <p class="dz-stat-label mb-1">Progresso atual</p>
                                    <p class="h5 mb-0 fw-semibold" id="fp-edit-saved">R$ 0,00</p>
                                </div>
                            </div>
                            <div class="modal-footer border-secondary-subtle">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                                <x-primary-button class="rounded-pill px-4">Atualizar</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row g-4 cofrinhos-grid">
                @forelse($cofrinhoRows as $row)
                    @php
                        $p = $row['project'];
                        $saved = $row['saved'];
                        $target = $row['target'];
                        $remaining = $row['remaining'];
                        $pct = $row['pct'];
                        $isComplete = $row['is_complete'];
                    @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 cofrinhos-project-card h-100" style="--cofrinho-accent: {{ $p->color ? e($p->color) : 'var(--bs-primary)' }}">
                            <div class="cofrinhos-project-card__accent" aria-hidden="true"></div>
                            <div class="cofrinhos-project-card__top">
                                <div class="cofrinhos-project-card__avatar" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 48 48">
                                        <ellipse cx="24" cy="29" rx="16" ry="11" fill="currentColor" opacity="0.18" />
                                        <path d="M12 27c0-7.2 6.2-13 14-13 6.8 0 12.6 4.4 13.8 10.2l3 1.1a2 2 0 011.2 1.8v3.1a2 2 0 01-2 2h-2.4a13.8 13.8 0 01-3.6 4.1v3.2a2 2 0 01-2 2h-3.1a2 2 0 01-1.9-1.4l-.5-1.4a20.3 20.3 0 01-6.9 0l-.5 1.4a2 2 0 01-1.9 1.4H16a2 2 0 01-2-2v-3.3A12.8 12.8 0 0112 27z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
                                        <path d="M20 14c1.4-3.4 5.9-5 9.8-3" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                        <circle cx="31" cy="24" r="1.8" fill="currentColor" />
                                        <path d="M21 23h6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <h3 class="cofrinhos-project-card__title mb-1">{{ $p->name }}</h3>
                                        @if($isComplete)
                                            <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--done">Concluído</span>
                                        @elseif($target !== null)
                                            <span class="cofrinhos-project-card__badge">Com meta</span>
                                        @else
                                            <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--muted">Livre</span>
                                        @endif
                                    </div>
                                    @if($target !== null)
                                        <p class="small text-secondary mb-0">Meta de R$ {{ number_format($target, 2, ',', '.') }}</p>
                                    @else
                                        <p class="small text-secondary mb-0">Sem valor-alvo definido</p>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body p-4 cofrinhos-project-card__body">
                                <p class="dz-stat-label mb-1">Guardado agora</p>
                                <p class="cofrinhos-project-card__amount mb-3">R$ {{ number_format($saved, 2, ',', '.') }}</p>
                                @if($target !== null)
                                    @if($pct !== null)
                                        <div class="cofrinhos-progress mb-3" aria-label="Progresso de {{ number_format((float) $pct, 1, ',', '.') }}%">
                                            <div class="cofrinhos-progress__bar {{ $isComplete ? 'cofrinhos-progress__bar--done' : '' }}" style="width: {{ number_format((float) $pct, 2, '.', '') }}%"></div>
                                        </div>
                                    @endif
                                    <div class="cofrinhos-project-card__metrics mb-3">
                                        <div class="cofrinhos-mini-stat">
                                            <span>Falta</span>
                                            <strong>R$ {{ number_format((float) $remaining, 2, ',', '.') }}</strong>
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
                                <div class="cofrinhos-project-card__primary-actions">
                                    <a
                                        href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'aporte']) }}"
                                        class="btn btn-success btn-sm rounded-pill px-3"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Ir ao painel com despesa em Investimentos e este cofrinho"
                                    >+ Aporte</a>
                                    <a
                                        href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'retirada']) }}"
                                        class="btn btn-outline-danger btn-sm rounded-pill px-3"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Ir ao painel com receita em Retirada de cofrinho e este cofrinho"
                                    >− Retirada</a>
                                </div>
                                <div class="cofrinhos-project-card__toolbar pt-3 mt-3">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm rounded-pill"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalCofrinhoJuros{{ $p->id }}"
                                    >
                                        + Juros
                                    </button>
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
                                        data-cofrinho-target="{{ $p->target_amount !== null ? number_format((float) $p->target_amount, 2, ',', '.') : '' }}"
                                        data-cofrinho-color="{{ e($p->color ?: '#0d9488') }}"
                                        data-cofrinho-saved="{{ number_format((float) $saved, 2, ',', '.') }}"
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

                function setCofrinhoEditAction(id) {
                    if (!editForm || !editModal) return;
                    const base = editModal.getAttribute('data-cofrinhos-base-url') || '';
                    editForm.action = base.replace(/\/$/, '') + '/' + encodeURIComponent(String(id));
                }

                function fillCofrinhoEditFromPayload(p) {
                    if (!editForm || !p) return;
                    const idEl = document.getElementById('fp-edit-cofrinho-id');
                    const nameEl = document.getElementById('fp-edit-name');
                    const targetEl = document.getElementById('fp-edit-target');
                    const colorEl = document.getElementById('fp-edit-color');
                    const savedEl = document.getElementById('fp-edit-saved');
                    if (idEl) idEl.value = String(p.id);
                    if (nameEl) nameEl.value = p.name || '';
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
                            target_amount: btn.getAttribute('data-cofrinho-target') || '',
                            color: btn.getAttribute('data-cofrinho-color') || '#0d9488',
                            saved: btn.getAttribute('data-cofrinho-saved') || '0,00',
                        });
                    });
                }

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
