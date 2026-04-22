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
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-0 cofrinhos-hero-title">Cofrinhos</h2>
                <p class="small text-secondary mb-0 mt-1">Metas com aportes (despesa Investimentos) e retiradas (receita dedicada).</p>
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

            <div class="card border-0 dz-tip-card mb-4">
                <div class="card-body p-4 d-flex gap-3">
                    <span class="rounded-3 bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0 p-2 align-self-start" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                    <div class="small mb-0">
                        <strong class="d-block text-body mb-1">Como lançar no cofrinho</strong>
                        No painel, use <strong>+ Despesa</strong> ou <strong>+ Receita</strong>, conta corrente, categoria <strong>Investimentos</strong> (aporte) ou <strong>Retirada de cofrinho</strong> (retirada) e escolha o cofrinho na seção <strong>Cofrinho</strong> do mesmo formulário.
                    </div>
                </div>
            </div>

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
                                    <p class="dz-kpi-label mb-1">Progresso atual</p>
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

            <div class="row g-4">
                @forelse($projects as $p)
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 cofrinhos-project-card h-100" style="--cofrinho-accent: {{ $p->color ? e($p->color) : 'var(--bs-primary)' }}">
                            <div class="cofrinhos-project-card__accent" aria-hidden="true"></div>
                            <div class="card-body p-4 cofrinhos-project-card__body">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                                    <h3 class="h6 mb-0 fw-semibold">{{ $p->name }}</h3>
                                    @if($p->color)
                                        <span class="rounded-circle flex-shrink-0 border shadow-sm" style="width:1.125rem;height:1.125rem;background:{{ $p->color }}" title="Cor do cofrinho"></span>
                                    @endif
                                </div>
                                @php
                                    $saved = (float) $p->savedProgress();
                                    $target = $p->target_amount !== null ? (float) $p->target_amount : null;
                                    $remaining = $target !== null ? max(0.0, $target - $saved) : null;
                                    $pct = ($target !== null && $target > 0.00001) ? min(100.0, ($saved / $target) * 100.0) : null;
                                @endphp
                                <p class="dz-kpi-label mb-0">Guardado</p>
                                <p class="h5 fw-semibold mb-2">R$ {{ number_format($saved, 2, ',', '.') }}</p>
                                @if($target !== null)
                                    <p class="small text-secondary mb-1">Meta: R$ {{ number_format($target, 2, ',', '.') }}</p>
                                    <p class="small mb-2">Falta: <strong>R$ {{ number_format((float) $remaining, 2, ',', '.') }}</strong>@if($pct !== null) · <strong>{{ number_format((float) $pct, 1, ',', '.') }}%</strong>@endif</p>
                                    @if($pct !== null)
                                        <div class="progress rounded-pill bg-body-secondary mb-3" style="height: 10px;">
                                            <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : 'bg-primary' }}" style="width: {{ number_format((float) $pct, 2, '.', '') }}%"></div>
                                        </div>
                                    @else
                                        <div class="mb-3"></div>
                                    @endif
                                @else
                                    <p class="small text-secondary mb-3">Sem meta numérica.</p>
                                @endif
                                <div class="d-flex flex-wrap gap-2 pt-1">
                                    <a
                                        href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'aporte']) }}"
                                        class="btn btn-success btn-sm rounded-pill"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Ir ao painel com despesa em Investimentos e este cofrinho"
                                    >+ Aporte</a>
                                    <a
                                        href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'retirada']) }}"
                                        class="btn btn-outline-danger btn-sm rounded-pill"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Ir ao painel com receita em Retirada de cofrinho e este cofrinho"
                                    >− Retirada</a>
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm rounded-pill"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalCofrinhoJuros{{ $p->id }}"
                                    >
                                        + Juros
                                    </button>
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
                                    <form action="{{ route('cofrinhos.destroy', $p) }}" method="post" class="d-inline" onsubmit="return confirm('Excluir este cofrinho?');">
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
