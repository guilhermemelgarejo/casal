@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\RecurringTransaction> $items */
    $dummyRtId = 888888888;
    $rtUpdateUrlTemplate = route('recurring-transactions.update', ['recurringTransaction' => $dummyRtId]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-md-between gap-3">
            <div class="min-w-0">
                <h2 class="h5 mb-0 tx-page-title">Lançamentos recorrentes</h2>
                    <p class="small text-secondary mb-0 mt-1">Modelos para despesas e receitas fixas. Você pode criar o lançamento do mês no <strong class="fw-medium text-body">Painel</strong> — nada é gravado sozinho.</p>
            </div>
            <button
                type="button"
                class="btn btn-primary rounded-pill px-4 py-2 flex-shrink-0 js-rt-open-new"
                data-bs-toggle="modal"
                data-bs-target="#modalRecurringForm"
                id="btnRtNew"
            >
                Novo modelo
            </button>
        </div>
    </x-slot>

    @php
        $rtTotal = $items->count();
        $rtActive = $items->where('is_active', true)->count();
        $rtPendingCount = $pendingReminders->count();
    @endphp

    <div class="py-4 recurring-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <span class="pt-1">{{ session('error') }}</span>
                </div>
            @endif

            <div class="card border-0 shadow-sm overflow-hidden tx-index-card mb-4">
                <div class="tx-index-head px-4 py-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div class="min-w-0">
                            <h3 class="h5 mb-1 fw-semibold">Seus modelos</h3>
                            <p class="small text-secondary mb-0">O <strong class="fw-medium text-body">dia do mês</strong> sugere a data ao abrir o Painel; em meses curtos usa-se o último dia útil.</p>
                        </div>
                        @if($rtTotal > 0)
                            <div class="d-flex flex-wrap gap-2 justify-content-end flex-shrink-0" role="group" aria-label="Resumo dos modelos">
                                <span class="rt-stat-pill">
                                    <span class="rt-stat-pill__value">{{ $rtTotal }}</span>
                                    <span class="rt-stat-pill__label">{{ $rtTotal === 1 ? 'modelo' : 'modelos' }}</span>
                                </span>
                                <span class="rt-stat-pill rt-stat-pill--success">
                                    <span class="rt-stat-pill__value">{{ $rtActive }}</span>
                                    <span class="rt-stat-pill__label">ativos</span>
                                </span>
                                @if($rtPendingCount > 0)
                                    <span class="rt-stat-pill rt-stat-pill--warning">
                                        <span class="rt-stat-pill__value">{{ $rtPendingCount }}</span>
                                        <span class="rt-stat-pill__label">pendentes</span>
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($items->isEmpty())
                <div class="rt-empty text-center px-4 py-5">
                    <div class="rt-empty__icon mx-auto mb-3" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    </div>
                    <p class="fw-semibold text-body mb-1">Nenhum modelo ainda</p>
                    <p class="small text-secondary mb-4 mx-auto" style="max-width: 22rem;">Crie o primeiro para aluguel, assinaturas ou salários — depois use o lembrete para lançar no mês.</p>
                    <button type="button" class="btn btn-primary rounded-pill px-4 js-rt-open-new" data-bs-toggle="modal" data-bs-target="#modalRecurringForm">
                        Criar primeiro modelo
                    </button>
                </div>
            @else
                <div class="vstack gap-3">
                    @foreach($items as $item)
                        @php
                            $accent = $item->account?->color ?? ($item->type === 'income' ? '#198754' : '#0d6efd');
                            $pendingThisMonth = $item->is_active && ! $item->hasGeneratedForCalendarMonth(now()->year, now()->month);
                            $isCard = $item->funding === \App\Models\RecurringTransaction::FUNDING_CREDIT_CARD;
                        @endphp
                        <article class="card border-0 rt-item-card shadow-sm overflow-hidden" style="--rt-accent: {{ $accent }}">
                            <div class="rt-item-card__accent" aria-hidden="true"></div>
                            <div class="card-body p-0">
                                <div class="rt-item-card__top px-3 px-sm-4 pt-3 pb-3">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                                        <div class="d-flex align-items-start gap-3 min-w-0 flex-grow-1">
                                            <div class="rt-item-card__avatar flex-shrink-0 text-white" style="background-color: {{ $accent }}">
                                                @if($isCard)
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                                @else
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                    <h3 class="rt-item-card__title mb-0 text-truncate">{{ $item->description }}</h3>
                                                    <span class="rt-item-card__type {{ $item->type === 'income' ? 'rt-item-card__type--income' : 'rt-item-card__type--expense' }}">
                                                        {{ $item->type === 'income' ? 'Receita' : 'Despesa' }}
                                                    </span>
                                                    @if($item->is_active)
                                                        <span class="rt-item-card__badge rt-item-card__badge--ok">Ativo</span>
                                                    @else
                                                        <span class="rt-item-card__badge">Inativo</span>
                                                    @endif
                                                    @if($pendingThisMonth)
                                                        <span class="rt-item-card__badge rt-item-card__badge--pending">Pendente no mês</span>
                                                    @elseif($item->is_active)
                                                        <span class="rt-item-card__badge rt-item-card__badge--done">Registado no mês</span>
                                                    @endif
                                                </div>
                                                <p class="rt-item-card__meta small mb-0">
                                                    <span class="rt-item-card__amount">R$ {{ number_format((float) $item->amount, 2, ',', '.') }}</span>
                                                    <span class="text-secondary">· Dia {{ $item->day_of_month }}</span>
                                                    <span class="text-secondary">· {{ $item->account?->name ?? 'Conta' }}</span>
                                                    @if($item->funding === \App\Models\RecurringTransaction::FUNDING_ACCOUNT && $item->payment_method)
                                                        <span class="text-secondary">· {{ $item->payment_method }}</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        <div class="rt-item-card__toolbar d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                                            @if(!$item->hasGeneratedForCalendarMonth(now()->year, now()->month))
                                                <a href="{{ route('dashboard', ['prefill_recurring' => $item->id, 'period' => now()->format('Y-m')]) }}" class="btn btn-sm rt-item-card__btn-primary rounded-pill px-3">Criar lançamento</a>
                                            @endif
                                            <button
                                                type="button"
                                                class="btn btn-sm rt-item-card__btn-edit rounded-pill px-3 btn-rt-edit"
                                                data-rt-edit-id="{{ $item->id }}"
                                            >Editar</button>
                                            <form method="POST" action="{{ route('recurring-transactions.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Remover este modelo? Lançamentos já gerados não serão apagados.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm rt-item-card__btn-delete rounded-pill px-3">Excluir</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="rt-item-card__body px-3 px-sm-4 pb-4">
                                    <p class="rt-item-card__cats-label small text-secondary text-uppercase fw-semibold mb-2">Categorias</p>
                                    <div class="rt-item-card__chips">
                                        @forelse($item->categorySplits as $sp)
                                            @php $c = $sp->category; @endphp
                                            <span
                                                class="rt-cat-chip"
                                                @if($c?->color) style="--rt-cat-chip-color: {{ $c->color }}" @endif
                                            >{{ $c?->name ?? '—' }}</span>
                                        @empty
                                            <span class="rt-cat-chip rt-cat-chip--muted">Sem categorias</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="modalRecurringForm" tabindex="-1" aria-labelledby="modalRecurringFormLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow rt-modal-content">
                <div class="modal-header border-0 pb-0 rt-modal-head">
                    <div>
                        <h2 class="h5 mb-0 fw-semibold" id="modalRecurringFormLabel">Modelo recorrente</h2>
                        <p class="small text-secondary mb-0" id="rt-modal-subtitle">Preencha os dados e as categorias (soma = valor total).</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="{{ route('recurring-transactions.store') }}" id="formRecurring" class="vstack gap-3">
                        @csrf
                        <input type="hidden" name="_form" value="recurring-transactions">
                        <div id="rt-method-wrap"></div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="rt-description">Descrição</label>
                                <input type="text" name="description" id="rt-description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" required maxlength="255">
                                @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="rt-amount">Valor total</label>
                                <input type="text" name="amount" id="rt-amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required inputmode="decimal">
                                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="rt-type">Tipo</label>
                                <select name="type" id="rt-type" class="form-select @error('type') is-invalid @enderror" required>
                                    <option value="expense" @selected(old('type', 'expense') === 'expense')>Despesa</option>
                                    <option value="income" @selected(old('type') === 'income')>Receita</option>
                                </select>
                                @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="rt-funding">Origem</label>
                                <select name="funding" id="rt-funding" class="form-select @error('funding') is-invalid @enderror" required>
                                    <option value="{{ \App\Models\RecurringTransaction::FUNDING_ACCOUNT }}" @selected(old('funding', \App\Models\RecurringTransaction::FUNDING_ACCOUNT) === \App\Models\RecurringTransaction::FUNDING_ACCOUNT)>Conta corrente</option>
                                    <option value="{{ \App\Models\RecurringTransaction::FUNDING_CREDIT_CARD }}" @selected(old('funding') === \App\Models\RecurringTransaction::FUNDING_CREDIT_CARD)>Cartão de crédito</option>
                                </select>
                                @error('funding')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="rt-day">Dia do mês</label>
                                <input type="number" name="day_of_month" id="rt-day" class="form-control @error('day_of_month') is-invalid @enderror" min="1" max="31" value="{{ old('day_of_month', 5) }}" required>
                                @error('day_of_month')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text">Em meses mais curtos, o sistema usa o último dia possível.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="rt-account">Conta / cartão</label>
                                <select name="account_id" id="rt-account" class="form-select @error('account_id') is-invalid @enderror" required>
                                    <option value="">Selecione…</option>
                                    @foreach($regularAccounts as $a)
                                        <option value="{{ $a->id }}" data-kind="regular" @selected((string) old('account_id') === (string) $a->id)>{{ $a->name }} (conta)</option>
                                    @endforeach
                                    @foreach($cardAccounts as $a)
                                        <option value="{{ $a->id }}" data-kind="credit_card" @selected((string) old('account_id') === (string) $a->id)>{{ $a->name }} (cartão)</option>
                                    @endforeach
                                </select>
                                @error('account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6" id="rt-payment-wrap">
                                <label class="form-label" for="rt-payment-method">Forma de pagamento</label>
                                <select name="payment_method" id="rt-payment-method" class="form-select @error('payment_method') is-invalid @enderror">
                                    <option value="">Selecione…</option>
                                    @foreach($paymentMethods as $pm)
                                        <option value="{{ $pm }}" @selected(old('payment_method') === $pm)>{{ $pm }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="rt-active" value="1" @checked(old('is_active', true))>
                            <label class="form-check-label" for="rt-active">Modelo ativo</label>
                        </div>

                        <div class="border rounded-3 p-3 bg-body-tertiary rt-form-cats">
                            <p class="small fw-semibold text-secondary mb-2 text-uppercase rt-form-cats__title">Categorias e valores</p>
                            <div class="vstack gap-2" id="rt-cat-rows">
                                @for($i = 0; $i < 5; $i++)
                                    <div class="row g-2 align-items-end rt-cat-row">
                                        <div class="col-md-7">
                                            <label class="form-label small mb-1">Categoria</label>
                                            <select name="category_allocations[{{ $i }}][category_id]" class="form-select form-select-sm rt-cat-select">
                                                <option value="">—</option>
                                                @foreach($categories as $cat)
                                                    <option value="{{ $cat->id }}" data-type="{{ $cat->type }}">{{ $cat->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small mb-1">Valor</label>
                                            <input type="text" name="category_allocations[{{ $i }}][amount]" class="form-control form-control-sm rt-cat-amount" inputmode="decimal" placeholder="0,00">
                                        </div>
                                    </div>
                                @endfor
                            </div>
                            @error('category_allocations')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </div>

                        <div class="modal-footer border-0 px-0 pb-0 pt-2">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4" id="rt-submit-btn">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.__RT_EDIT_BY_ID__ = @json($recurringEditPayloadsById);
        </script>
        <script>
            (function () {
                const modalEl = document.getElementById('modalRecurringForm');
                if (!modalEl) return;

                const form = document.getElementById('formRecurring');
                const methodWrap = document.getElementById('rt-method-wrap');
                const fundingEl = document.getElementById('rt-funding');
                const paymentWrap = document.getElementById('rt-payment-wrap');
                const paymentSelect = document.getElementById('rt-payment-method');
                const accountSelect = document.getElementById('rt-account');
                const typeSelect = document.getElementById('rt-type');
                const storeUrl = @json(route('recurring-transactions.store'));
                const updateUrlTemplate = @json($rtUpdateUrlTemplate);
                const dummyId = {{ (int) $dummyRtId }};
                const rawEditMap = window.__RT_EDIT_BY_ID__;
                const editById =
                    rawEditMap && typeof rawEditMap === 'object' && !Array.isArray(rawEditMap) ? rawEditMap : {};
                const amountInput = document.getElementById('rt-amount');

                function setFundingUi() {
                    const funding = fundingEl.value;
                    const isCard = funding === @json(\App\Models\RecurringTransaction::FUNDING_CREDIT_CARD);
                    paymentWrap.classList.toggle('d-none', isCard);
                    paymentSelect.disabled = isCard;
                    if (isCard) {
                        paymentSelect.value = '';
                    }
                    filterAccounts();
                    filterCategoryOptions();
                }

                function filterAccounts() {
                    const funding = fundingEl.value;
                    const want = funding === @json(\App\Models\RecurringTransaction::FUNDING_CREDIT_CARD) ? 'credit_card' : 'regular';
                    const options = accountSelect.querySelectorAll('option[data-kind]');
                    let firstVisible = null;
                    options.forEach((opt) => {
                        const ok = opt.getAttribute('data-kind') === want;
                        opt.hidden = !ok;
                        opt.disabled = !ok;
                        if (ok && !firstVisible) firstVisible = opt;
                    });
                    const cur = accountSelect.options[accountSelect.selectedIndex];
                    if (!cur || cur.disabled || cur.hidden) {
                        accountSelect.value = firstVisible ? firstVisible.value : '';
                    }
                }

                function filterCategoryOptions() {
                    const t = typeSelect.value;
                    document.querySelectorAll('.rt-cat-select').forEach((sel) => {
                        const val = sel.value;
                        Array.from(sel.options).forEach((opt) => {
                            if (!opt.value) return;
                            const ot = opt.getAttribute('data-type');
                            opt.hidden = ot !== t;
                            opt.disabled = ot !== t;
                        });
                        const selected = sel.options[sel.selectedIndex];
                        if (selected && (selected.hidden || selected.disabled)) {
                            sel.value = '';
                        }
                    });
                }

                function resetCategoryRows() {
                    document.querySelectorAll('.rt-cat-row').forEach((row) => {
                        row.querySelector('.rt-cat-select').value = '';
                        row.querySelector('.rt-cat-amount').value = '';
                    });
                }

                function fillCategoryRows(splits) {
                    resetCategoryRows();
                    const rows = document.querySelectorAll('.rt-cat-row');
                    (splits || []).slice(0, rows.length).forEach((sp, idx) => {
                        const row = rows[idx];
                        if (!row) return;
                        row.querySelector('.rt-cat-select').value = String(sp.category_id || '');
                        row.querySelector('.rt-cat-amount').value = sp.amount || '';
                    });
                }

                function parseMoneyToCents(raw) {
                    const input = String(raw || '').trim();
                    if (input === '') return null;

                    const lastComma = input.lastIndexOf(',');
                    const lastDot = input.lastIndexOf('.');
                    let normalized = input.replace(/\s+/g, '');

                    if (lastComma !== -1 && lastDot !== -1) {
                        const commaIsDecimal = lastComma > lastDot;
                        if (commaIsDecimal) {
                            normalized = normalized.replace(/\./g, '').replace(',', '.');
                        } else {
                            normalized = normalized.replace(/,/g, '');
                        }
                    } else if (lastComma !== -1) {
                        normalized = normalized.replace(/\./g, '').replace(',', '.');
                    } else {
                        normalized = normalized.replace(/,/g, '');
                    }

                    const n = Number.parseFloat(normalized);
                    if (!Number.isFinite(n) || n <= 0) return null;
                    return Math.round(n * 100);
                }

                function formatCentsToMoneyBr(cents) {
                    const v = (Math.round(cents) / 100).toFixed(2);
                    return v.replace('.', ',');
                }

                function distributeAmountToCategories(totalCents) {
                    if (totalCents == null || totalCents < 1) return;
                    const rows = Array.from(document.querySelectorAll('.rt-cat-row'));
                    const mapped = rows
                        .map((row) => {
                            const cat = row.querySelector('.rt-cat-select');
                            const amt = row.querySelector('.rt-cat-amount');
                            const amtCents = amt ? parseMoneyToCents(amt.value) : null;
                            return { row, cat, amt, amtCents };
                        })
                        .filter((x) => x.amt);

                    if (mapped.length < 1) return;

                    const selected = mapped.filter((x) => x.cat && String(x.cat.value || '').trim() !== '');

                    if (selected.length < 1) {
                        const target = mapped[0]?.amt;
                        if (target) target.value = formatCentsToMoneyBr(totalCents);
                        return;
                    }

                    if (selected.length === 1) {
                        selected[0].amt.value = formatCentsToMoneyBr(totalCents);
                        return;
                    }

                    const filled = selected.filter((x) => x.amtCents != null && x.amtCents > 0);
                    const sumFilled = filled.reduce((acc, x) => acc + (x.amtCents || 0), 0);
                    if (sumFilled < 1) {
                        const base = Math.floor(totalCents / selected.length);
                        const rem = totalCents - base * selected.length;
                        selected.forEach((x, idx) => {
                            const c = base + (idx === selected.length - 1 ? rem : 0);
                            x.amt.value = formatCentsToMoneyBr(c);
                        });
                        return;
                    }

                    let allocated = 0;
                    for (let i = 0; i < selected.length; i += 1) {
                        const isLast = i === selected.length - 1;
                        const rowCents = selected[i].amtCents || 0;
                        const newCents = isLast
                            ? totalCents - allocated
                            : Math.floor((totalCents * rowCents) / sumFilled);
                        allocated += newCents;
                        if (selected[i].amt) selected[i].amt.value = formatCentsToMoneyBr(newCents);
                    }
                }

                function openNew() {
                    form.action = storeUrl;
                    methodWrap.innerHTML = '';
                    document.getElementById('modalRecurringFormLabel').textContent = 'Novo modelo recorrente';
                    document.getElementById('rt-submit-btn').textContent = 'Criar';
                    form.reset();
                    document.getElementById('rt-type').value = 'expense';
                    document.getElementById('rt-funding').value = @json(\App\Models\RecurringTransaction::FUNDING_ACCOUNT);
                    document.getElementById('rt-day').value = '5';
                    document.getElementById('rt-active').checked = true;
                    resetCategoryRows();
                    setFundingUi();
                }

                function openEdit(payload) {
                    const url = updateUrlTemplate.replace(String(dummyId), String(payload.id));
                    form.action = url;
                    methodWrap.innerHTML = '<input type="hidden" name="_method" value="PUT">';
                    document.getElementById('modalRecurringFormLabel').textContent = 'Editar modelo';
                    document.getElementById('rt-submit-btn').textContent = 'Atualizar';
                    document.getElementById('rt-description').value = payload.description || '';
                    document.getElementById('rt-amount').value = payload.amount || '';
                    document.getElementById('rt-type').value = payload.type || 'expense';
                    document.getElementById('rt-funding').value = payload.funding || @json(\App\Models\RecurringTransaction::FUNDING_ACCOUNT);
                    document.getElementById('rt-day').value = String(payload.day_of_month || 1);
                    document.getElementById('rt-active').checked = Number(payload.is_active) === 1;
                    setFundingUi();
                    accountSelect.value = String(payload.account_id || '');
                    if (paymentSelect) {
                        paymentSelect.value = payload.payment_method || '';
                    }
                    fillCategoryRows(payload.splits || []);
                    filterCategoryOptions();
                }

                fundingEl.addEventListener('change', () => {
                    setFundingUi();
                });
                typeSelect.addEventListener('change', () => {
                    filterCategoryOptions();
                });

                if (amountInput) {
                    const onAmountChange = () => {
                        const cents = parseMoneyToCents(amountInput.value);
                        if (cents == null) return;
                        distributeAmountToCategories(cents);
                    };
                    amountInput.addEventListener('input', onAmountChange);
                    amountInput.addEventListener('change', onAmountChange);
                    amountInput.addEventListener('blur', onAmountChange);
                }

                document.addEventListener('click', (ev) => {
                    const btn = ev.target.closest('.btn-rt-edit');
                    if (!btn) {
                        return;
                    }
                    ev.preventDefault();
                    const rawId = btn.getAttribute('data-rt-edit-id');
                    const id = rawId != null && rawId !== '' ? String(rawId) : '';
                    const payload = id !== '' ? editById[id] : null;
                    if (payload) {
                        openEdit(payload);
                    } else {
                        openNew();
                    }
                    if (typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                });

                modalEl.addEventListener('show.bs.modal', (ev) => {
                    const btn = ev.relatedTarget;
                    if (btn && btn.classList && btn.classList.contains('js-rt-open-new')) {
                        openNew();
                    }
                });

                @if ($errors->any() && old('_form') === 'recurring-transactions')
                    setFundingUi();
                    const m = new bootstrap.Modal(modalEl);
                    m.show();
                @else
                    setFundingUi();
                @endif
            })();
        </script>
    @endpush
</x-app-layout>
