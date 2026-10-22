{{--
    Lembretes: modelos recorrentes + faturas de cartão em aberto (painel e /recorrentes).
    Obrigatório: $reminders (collection de RecurringTransaction), $month (int), $year (int).
    Opcional: $invoiceReminders (collection de arrays — ver CreditCardInvoiceReminders::openStatementsForCouple);
    $title, $description (HTML); $manageUrl (padrão = /recorrentes; vazio oculta), $manageLabel;
    $invoiceManageUrl (padrão = faturas-cartão sem filtro), $invoiceManageLabel
--}}
@php
    $invoiceReminders = $invoiceReminders ?? collect();
    $debtReminders = $debtReminders ?? collect();
    $monthlyReminders = $reminders->filter(fn ($rec) => ! ($rec instanceof \App\Models\RecurringTransaction && $rec->is_multiple));
    $multipleReminders = $reminders->filter(fn ($rec) => $rec instanceof \App\Models\RecurringTransaction && $rec->is_multiple);

    $hasMonthly = $monthlyReminders->isNotEmpty();
    $hasMultiple = $multipleReminders->isNotEmpty();
    $hasRecurring = $hasMonthly || $hasMultiple;
    $hasInvoices = $invoiceReminders->isNotEmpty();
    $hasDebts = $debtReminders->isNotEmpty();
    $showPanel = $hasRecurring || $hasInvoices || $hasDebts;

    $activeColumnsCount = ($hasMonthly ? 1 : 0) + ($hasMultiple ? 1 : 0) + ($hasInvoices ? 1 : 0) + ($hasDebts ? 1 : 0);
    $bothReminderKinds = $activeColumnsCount > 1;

    // Métricas para diferenciação de Receitas e Despesas
    $monthlyIncomes = $monthlyReminders->filter(fn ($r) => $r instanceof \App\Models\RecurringTransaction && $r->type === 'income');
    $monthlyExpenses = $monthlyReminders->filter(fn ($r) => ! ($r instanceof \App\Models\RecurringTransaction && $r->type === 'income'));
    $multipleIncomes = $multipleReminders->filter(fn ($r) => $r instanceof \App\Models\RecurringTransaction && $r->type === 'income');
    $multipleExpenses = $multipleReminders->filter(fn ($r) => ! ($r instanceof \App\Models\RecurringTransaction && $r->type === 'income'));

    $allIncomes = $monthlyIncomes->concat($multipleIncomes);
    $incomesCount = $allIncomes->count();
    $incomesTotal = (float) $allIncomes->sum('amount');

    $expensesCount = $monthlyExpenses->count() + $multipleExpenses->count() + count($invoiceReminders) + count($debtReminders);
    $expensesTotal = (float) $monthlyExpenses->sum('amount')
        + (float) $multipleExpenses->sum('amount')
        + (float) $invoiceReminders->sum('remaining')
        + (float) $debtReminders->sum('amount');
    $totalRemindersCount = $incomesCount + $expensesCount;
    $hasMultipleKinds = $expensesCount > 0 && $incomesCount > 0;

    if (! isset($title)) {
        if ($bothReminderKinds) {
            $title = 'Lembretes & Vencimentos';
        } elseif ($hasDebts) {
            $title = 'Boletos & Contas a vencer';
        } elseif ($hasInvoices) {
            $title = 'Faturas em aberto';
        } elseif ($hasMultiple && ! $hasMonthly) {
            $title = 'Atalhos de lançamentos';
        } else {
            $title = 'Lembretes deste mês';
        }
    }
    if (! isset($description)) {
        if ($bothReminderKinds) {
            $description = 'Há modelos recorrentes, faturas de cartão e/ou parcelas a vencer com pendências.';
        } elseif ($hasDebts) {
            $description = 'Parcelas de dívidas ou boletos com vencimento neste período.';
        } elseif ($hasInvoices) {
            $description = 'Cartões com fatura não quitada ou com pagamento parcial. Itens <strong class="fw-medium text-body">vencidos</strong> aparecem primeiro.';
        } elseif ($hasMultiple && ! $hasMonthly) {
            $description = 'Modelos recorrentes múltiplos prontos para lançamento rápido com data no dia atual.';
        } else {
            $description = 'Ainda sem lançamento vinculado ao modelo no mês civil atual. Use o <strong class="fw-medium text-body">Painel</strong> para pré-preencher e confirmar.';
        }
    }

    $manageUrl = $manageUrl ?? route('recurring-transactions.index');
    $manageLabel = $manageLabel ?? 'Gerenciar modelos';
    $invoiceManageUrl = $invoiceManageUrl ?? route('credit-card-statements.index');
    $invoiceManageLabel = $invoiceManageLabel ?? 'Ver faturas';
    $debtManageUrl = route('debts.index', ['tab' => 'agenda']);
    $debtManageLabel = 'Ver contas';

    $nowForReminderOverdue = \Carbon\Carbon::now();
    $reminderCivilYear = (int) $nowForReminderOverdue->year;
    $reminderCivilMonth = (int) $nowForReminderOverdue->month;
    $reminderPanelHasOverdue = $invoiceReminders->contains(fn (array $inv) => ! empty($inv['is_overdue'] ?? false))
        || $debtReminders->contains(fn ($d) => $d->isOverdue($nowForReminderOverdue))
        || $reminders->contains(
            fn ($rec) => $rec instanceof \App\Models\RecurringTransaction
                && $rec->isReminderOverdueForCalendarMonth($nowForReminderOverdue)
        );
@endphp
@if($showPanel)
    <div class="rt-reminder-strip {{ ! empty($embedded ?? false) ? 'rt-reminder-strip--embedded mb-0' : 'mb-3' }}">
        @if(empty($embedded ?? false))
            <div class="container-xxl px-3 px-lg-4">
        @endif
            <div class="rt-reminder-card border-0 shadow-sm @if($reminderPanelHasOverdue) rt-reminder-card--overdue @endif {{ $bothReminderKinds ? 'rt-reminder-columns--split' : '' }}" role="status" style="border-radius: var(--dz-radius-lg); background: var(--dz-bg-card); border: 1px solid var(--dz-border); padding: 0.85rem 1.15rem;">
                
                <!-- Cabeçalho Compacto em Linha Única -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 border-bottom" style="border-color: var(--dz-border-subtle) !important;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="d-flex align-items-center gap-1">
                            <span style="font-size: 1.1rem; line-height: 1;">🔔</span>
                            <h3 class="rt-reminder-card__title mb-0" style="font-size: 0.92rem; font-weight: 800; color: var(--dz-text-title);">{{ $title }}</h3>
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            @if($reminderPanelHasOverdue)
                                <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(244, 63, 94, 0.15); color: var(--dz-danger);">
                                    🔴 Vencido(s)
                                </span>
                            @endif
                            @if($hasMultipleKinds)
                                <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(244, 63, 94, 0.12); color: #E11D48;" title="Total a pagar: R$ {{ number_format($expensesTotal, 2, ',', '.') }}">
                                    🔴 {{ $expensesCount }} a pagar: R$ {{ number_format($expensesTotal, 2, ',', '.') }}
                                </span>
                                <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #059669;" title="Total a receber: R$ {{ number_format($incomesTotal, 2, ',', '.') }}">
                                    🟢 {{ $incomesCount }} a receber: R$ {{ number_format($incomesTotal, 2, ',', '.') }}
                                </span>
                            @else
                                <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: {{ $reminderPanelHasOverdue ? 'rgba(244, 63, 94, 0.15)' : 'var(--dz-primary-subtle)' }}; color: {{ $reminderPanelHasOverdue ? 'var(--dz-danger)' : 'var(--dz-primary)' }};">
                                    {{ $totalRemindersCount }} pendência(s)
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($hasRecurring && $manageUrl)
                            <a href="{{ $manageUrl }}" class="rt-reminder-btn--header" style="font-size: 0.75rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                                {{ $manageLabel }} ↗
                            </a>
                        @endif
                        @if($hasRecurring && $hasDebts)
                            <span style="color: var(--dz-border); font-size: 0.75rem;">•</span>
                        @endif
                        @if($hasDebts && $debtManageUrl)
                            <a href="{{ $debtManageUrl }}" class="rt-reminder-btn--header" style="font-size: 0.75rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                                {{ $debtManageLabel }} ↗
                            </a>
                        @endif
                        @if(($hasRecurring || $hasDebts) && $hasInvoices)
                            <span style="color: var(--dz-border); font-size: 0.75rem;">•</span>
                        @endif
                        @if($hasInvoices && $invoiceManageUrl)
                            <a href="{{ $invoiceManageUrl }}" class="rt-reminder-btn--header" style="font-size: 0.75rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                                {{ $invoiceManageLabel }} ↗
                            </a>
                        @endif
                    </div>
                </div>

                @php
                    $categoryCount = ($hasMonthly ? 1 : 0) + ($hasDebts ? 1 : 0) + ($hasInvoices ? 1 : 0) + ($hasMultiple ? 1 : 0);
                @endphp

                @if($categoryCount > 1 || $hasMultipleKinds)
                    <!-- Barra de Filtros por Categoria e Tipo (Receitas / Despesas) -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 dz-reminder-filter-bar">
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            <button type="button" class="dz-reminder-filter-btn active" data-filter="all">
                                Todos <span class="badge rounded-pill bg-light text-dark ms-1" style="font-size: 0.65rem;">{{ $totalRemindersCount }}</span>
                            </button>
                            @if($hasMultipleKinds)
                                <button type="button" class="dz-reminder-filter-btn" data-filter="expense" title="Filtrar apenas despesas a pagar">
                                    <span>🔴</span> Despesas <span class="badge rounded-pill bg-light text-dark ms-1" style="font-size: 0.65rem;">{{ $expensesCount }}</span>
                                </button>
                                <button type="button" class="dz-reminder-filter-btn" data-filter="income" title="Filtrar apenas receitas a receber">
                                    <span>🟢</span> Receitas <span class="badge rounded-pill bg-light text-dark ms-1" style="font-size: 0.65rem;">{{ $incomesCount }}</span>
                                </button>
                                @if($categoryCount > 1)
                                    <span class="text-secondary opacity-25 d-none d-sm-inline mx-1" style="font-size: 0.85rem;">|</span>
                                @endif
                            @endif
                            @if($categoryCount > 1)
                                @if($hasMonthly)
                                    <button type="button" class="dz-reminder-filter-btn" data-filter="recurring">
                                        <span>🔁</span> Recorrentes <span class="badge rounded-pill bg-light text-dark ms-1" style="font-size: 0.65rem;">{{ count($monthlyReminders) }}</span>
                                    </button>
                                @endif
                                @if($hasDebts)
                                    <button type="button" class="dz-reminder-filter-btn" data-filter="debt">
                                        <span>📄</span> Boletos & Contas <span class="badge rounded-pill bg-light text-dark ms-1" style="font-size: 0.65rem;">{{ count($debtReminders) }}</span>
                                    </button>
                                @endif
                                @if($hasInvoices)
                                    <button type="button" class="dz-reminder-filter-btn" data-filter="invoice">
                                        <span>💳</span> Faturas de cartão <span class="badge rounded-pill bg-light text-dark ms-1" style="font-size: 0.65rem;">{{ count($invoiceReminders) }}</span>
                                    </button>
                                @endif
                                @if($hasMultiple)
                                    <button type="button" class="dz-reminder-filter-btn" data-filter="shortcut">
                                        <span>⚡</span> Atalhos <span class="badge rounded-pill bg-light text-dark ms-1" style="font-size: 0.65rem;">{{ count($multipleReminders) }}</span>
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Grade Unificada Contínua de Lembretes (Ocupa todo o espaço disponível) -->
                <div class="dz-reminder-track pt-2" id="dz-reminder-unified-track">
                    <!-- 1. Recorrentes Mensais -->
                    @if($hasMonthly)
                        @foreach($monthlyReminders as $rec)
                            @php
                                $predDay = $rec->effectiveDayInMonth($reminderCivilYear, $reminderCivilMonth);
                                $isIncome = $rec->type === 'income';
                            @endphp
                            <div class="dz-reminder-chip dz-reminder-chip--recurring {{ $isIncome ? 'dz-reminder-chip--income' : 'dz-reminder-chip--expense' }}" data-type="recurring" data-kind="{{ $isIncome ? 'income' : 'expense' }}">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-1">
                                        @if($isIncome)
                                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; background: rgba(16, 185, 129, 0.15); font-size: 0.75rem;">🟢</span>
                                            <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #059669;">
                                                <span class="d-none">Recorrentes</span>Receita • Mensal
                                            </span>
                                        @else
                                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; background: rgba(244, 63, 94, 0.15); font-size: 0.75rem;">🔴</span>
                                            <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(244, 63, 94, 0.15); color: #E11D48;">
                                                <span class="d-none">Recorrentes</span>Despesa • Mensal
                                            </span>
                                        @endif
                                    </div>
                                    <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: 600; background: var(--dz-border-subtle); color: var(--dz-text-secondary);">
                                        <span class="d-none">Dia previsto: {{ sprintf('%02d/%02d/%04d', $predDay, $reminderCivilMonth, $reminderCivilYear) }}</span>
                                        Dia {{ sprintf('%02d', $predDay) }}
                                    </span>
                                </div>
                                <div class="dz-reminder-chip__body">
                                    <div class="fw-bold text-truncate" style="font-size: 0.88rem; color: var(--dz-text-title);" title="{{ $rec->description }}">{{ $rec->description }}</div>
                                    <div class="fw-bolder dz-privacy-blur {{ $isIncome ? 'text-success' : 'text-danger' }}" style="font-size: 1.05rem; margin-top: 0.15rem;">
                                        {{ $isIncome ? '+' : '-' }} R$ {{ number_format((float) $rec->amount, 2, ',', '.') }}
                                    </div>
                                </div>
                                @if($isIncome)
                                    <a href="{{ route('dashboard', ['prefill_recurring' => $rec->id, 'period' => sprintf('%04d-%02d', $year, $month)]) }}" class="btn btn-sm btn-success rounded-pill w-100 py-1 text-white fw-bold" style="font-size: 0.72rem; text-align: center;" title="Lançar esta receita no painel">
                                        + Lançar Receita
                                    </a>
                                @else
                                    <a href="{{ route('dashboard', ['prefill_recurring' => $rec->id, 'period' => sprintf('%04d-%02d', $year, $month)]) }}" class="btn btn-sm rounded-pill w-100 py-1 fw-bold" style="font-size: 0.72rem; text-align: center; background: rgba(244, 63, 94, 0.12); color: #E11D48; border: 1px solid rgba(244, 63, 94, 0.3);" title="Lançar esta despesa no painel">
                                        + Lançar Despesa
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    @endif

                    <!-- 2. Parcelas de Dívidas & Boletos a Vencer -->
                    @if($hasDebts)
                        @foreach($debtReminders as $dInst)
                            @php
                                $isOverdue = $dInst->isOverdue($nowForReminderOverdue);
                                $due = $dInst->due_date ? \Carbon\Carbon::parse($dInst->due_date) : null;
                            @endphp
                            <div class="dz-reminder-chip dz-reminder-chip--debt dz-reminder-chip--expense {{ $isOverdue ? 'dz-reminder-chip--overdue' : '' }}" data-type="debt" data-kind="expense">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-1">
                                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; background: rgba(244, 63, 94, 0.15); font-size: 0.75rem;">🔴</span>
                                        <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(244, 63, 94, 0.15); color: #E11D48;">
                                            Despesa • Boleto / Conta
                                        </span>
                                    </div>
                                    @if($isOverdue)
                                        <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: 700; background: rgba(244, 63, 94, 0.15); color: var(--dz-danger);">🔴 Vencido</span>
                                    @elseif($due && $due->isToday())
                                        <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #D97706;">🟡 Hoje</span>
                                    @elseif($due)
                                        <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: 600; background: var(--dz-border-subtle); color: var(--dz-text-secondary);">
                                            Dia {{ $due->format('d') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="dz-reminder-chip__body">
                                    <div class="fw-bold text-truncate" style="font-size: 0.88rem; color: var(--dz-text-title);" title="{{ $dInst->debt->name }} (Boleto/Conta)">{{ $dInst->debt->name }}</div>
                                    <div class="fw-bolder dz-privacy-blur text-danger" style="font-size: 1.05rem; margin-top: 0.15rem;">
                                        - R$ {{ number_format((float) $dInst->amount, 2, ',', '.') }}
                                    </div>
                                </div>
                                <a href="{{ route('debts.index', ['tab' => 'agenda', 'month' => $due?->month ?? $month, 'year' => $due?->year ?? $year]) }}" class="btn btn-sm rounded-pill w-100 py-1 fw-bold" style="font-size: 0.72rem; text-align: center; background: rgba(245, 158, 11, 0.15); color: #D97706; border: 1px solid rgba(245, 158, 11, 0.35);" title="Pagar na agenda">
                                    Pagar Conta ↗
                                </a>
                            </div>
                        @endforeach
                    @endif

                    <!-- 3. Faturas de Cartão -->
                    @if($hasInvoices)
                        @foreach($invoiceReminders as $inv)
                            @php
                                $isOverdue = !empty($inv['is_overdue']);
                            @endphp
                            <div class="dz-reminder-chip dz-reminder-chip--card dz-reminder-chip--expense {{ $isOverdue ? 'dz-reminder-chip--overdue' : '' }}" data-type="invoice" data-kind="expense">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-1">
                                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; background: rgba(124, 58, 237, 0.15); font-size: 0.75rem;">💳</span>
                                        <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(244, 63, 94, 0.15); color: #E11D48;">
                                            <span class="d-none">Faturas de cartão</span>Despesa • Fatura
                                        </span>
                                    </div>
                                    @if($isOverdue)
                                        <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: 700; background: rgba(244, 63, 94, 0.15); color: var(--dz-danger);">🔴 Vencida</span>
                                    @else
                                        <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: 600; background: var(--dz-border-subtle); color: var(--dz-text-secondary);">
                                            {{ $inv['due_label'] ? explode(':', $inv['due_label'])[0] : $inv['ref_label'] }}
                                        </span>
                                    @endif
                                </div>
                                <div class="dz-reminder-chip__body">
                                    <div class="fw-bold text-truncate" style="font-size: 0.88rem; color: var(--dz-text-title);" title="{{ $inv['account_name'] }}">{{ $inv['account_name'] }}</div>
                                    <div class="fw-bolder dz-privacy-blur text-danger" style="font-size: 1.05rem; margin-top: 0.15rem;">
                                        - R$ {{ number_format((float) $inv['remaining'], 2, ',', '.') }}
                                    </div>
                                </div>
                                <a href="{{ $inv['statements_url'] }}" class="btn btn-sm btn-outline-primary rounded-pill w-100 py-1 fw-bold" style="font-size: 0.72rem; text-align: center;" title="Ver fatura do cartão">
                                    Ver Fatura ↗
                                </a>
                            </div>
                        @endforeach
                    @endif

                    <!-- 4. Recorrentes Múltiplos / Atalhos -->
                    @if($hasMultiple)
                        @foreach($multipleReminders as $rec)
                            @php
                                $isIncome = $rec->type === 'income';
                            @endphp
                            <div class="dz-reminder-chip dz-reminder-chip--shortcut {{ $isIncome ? 'dz-reminder-chip--income' : 'dz-reminder-chip--expense' }}" data-type="shortcut" data-kind="{{ $isIncome ? 'income' : 'expense' }}">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-1">
                                        @if($isIncome)
                                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; background: rgba(16, 185, 129, 0.15); font-size: 0.75rem;">🟢</span>
                                            <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #059669;">
                                                <span class="d-none">Múltiplo</span>Receita • Atalho
                                            </span>
                                        @else
                                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; background: rgba(244, 63, 94, 0.15); font-size: 0.75rem;">🔴</span>
                                            <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(244, 63, 94, 0.15); color: #E11D48;">
                                                <span class="d-none">Múltiplo</span>Despesa • Atalho
                                            </span>
                                        @endif
                                    </div>
                                    <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: 600; background: rgba(245, 158, 11, 0.12); color: #D97706;">
                                        Rápido
                                    </span>
                                </div>
                                <div class="dz-reminder-chip__body">
                                    <div class="fw-bold text-truncate" style="font-size: 0.88rem; color: var(--dz-text-title);" title="{{ $rec->description }}">{{ $rec->description }}</div>
                                    <div class="fw-bolder dz-privacy-blur {{ $isIncome ? 'text-success' : 'text-danger' }}" style="font-size: 1.05rem; margin-top: 0.15rem;">
                                        {{ $isIncome ? '+' : '-' }} R$ {{ number_format((float) $rec->amount, 2, ',', '.') }}
                                    </div>
                                </div>
                                @if($isIncome)
                                    <a href="{{ route('dashboard', ['prefill_recurring' => $rec->id, 'period' => sprintf('%04d-%02d', $year, $month)]) }}" class="btn btn-sm btn-success rounded-pill w-100 py-1 text-white fw-bold" style="font-size: 0.72rem; text-align: center;" title="Lançar este atalho no painel">
                                        + Lançar Receita
                                    </a>
                                @else
                                    <a href="{{ route('dashboard', ['prefill_recurring' => $rec->id, 'period' => sprintf('%04d-%02d', $year, $month)]) }}" class="btn btn-sm rounded-pill w-100 py-1 fw-bold" style="font-size: 0.72rem; text-align: center; background: rgba(244, 63, 94, 0.12); color: #E11D48; border: 1px solid rgba(244, 63, 94, 0.3);" title="Lançar este atalho no painel">
                                        + Lançar Despesa
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>

                @if($categoryCount > 1 || $hasMultipleKinds)
                    <script>
                        (function() {
                            function setupReminderFilters() {
                                const filterBtns = document.querySelectorAll('.dz-reminder-filter-btn');
                                const track = document.getElementById('dz-reminder-unified-track');
                                if (!filterBtns.length || !track) return;

                                filterBtns.forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        filterBtns.forEach(b => b.classList.remove('active'));
                                        this.classList.add('active');
                                        const filter = this.getAttribute('data-filter');
                                        const chips = track.querySelectorAll('.dz-reminder-chip');
                                        chips.forEach(chip => {
                                            const chipType = chip.getAttribute('data-type');
                                            const chipKind = chip.getAttribute('data-kind');
                                            if (filter === 'all' || chipType === filter || chipKind === filter) {
                                                chip.style.display = '';
                                            } else {
                                                chip.style.display = 'none';
                                            }
                                        });
                                    });
                                });
                            }
                            if (document.readyState === 'loading') {
                                document.addEventListener('DOMContentLoaded', setupReminderFilters);
                            } else {
                                setupReminderFilters();
                            }
                        })();
                    </script>
                @endif
            </div>
        @if(empty($embedded ?? false))
            </div>
        @endif
    </div>
@endif
