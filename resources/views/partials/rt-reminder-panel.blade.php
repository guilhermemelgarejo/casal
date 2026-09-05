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
                    <div class="d-flex align-items-center gap-2">
                        <span style="font-size: 1.1rem; line-height: 1;">🔔</span>
                        <h3 class="rt-reminder-card__title mb-0" style="font-size: 0.92rem; font-weight: 800; color: var(--dz-text-title);">{{ $title }}</h3>
                        <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: {{ $reminderPanelHasOverdue ? 'rgba(244, 63, 94, 0.15)' : 'var(--dz-primary-subtle)' }}; color: {{ $reminderPanelHasOverdue ? 'var(--dz-danger)' : 'var(--dz-primary)' }};">
                            {{ count($monthlyReminders) + count($multipleReminders) + count($invoiceReminders) + count($debtReminders) }} pendência(s)
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($hasRecurring && $manageUrl)
                            <a href="{{ $manageUrl }}" class="rt-reminder-btn--header" style="font-size: 0.75rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                                {{ $manageLabel }} ↗
                            </a>
                        @endif
                        @if($hasRecurring && $hasInvoices)
                            <span style="color: var(--dz-border); font-size: 0.75rem;">•</span>
                        @endif
                        @if($hasInvoices && $invoiceManageUrl)
                            <a href="{{ $invoiceManageUrl }}" class="rt-reminder-btn--header" style="font-size: 0.75rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                                {{ $invoiceManageLabel }} ↗
                            </a>
                        @endif
                        @if(($hasRecurring || $hasInvoices) && $hasDebts)
                            <span style="color: var(--dz-border); font-size: 0.75rem;">•</span>
                        @endif
                        @if($hasDebts && $debtManageUrl)
                            <a href="{{ $debtManageUrl }}" class="rt-reminder-btn--header" style="font-size: 0.75rem; font-weight: 700; color: var(--dz-primary); text-decoration: none;">
                                {{ $debtManageLabel }} ↗
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Linhas de Lembretes por Tipo -->
                <div class="dz-reminder-groups d-flex flex-column gap-2 pt-2">
                    <!-- 1. Faturas de Cartão -->
                    @if($hasInvoices)
                        <div class="dz-reminder-type-block">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="dz-reminder-type-title dz-reminder-type-title--card">
                                        <span style="font-size: 0.85rem;">💳</span> Faturas de Cartão
                                    </span>
                                    <span class="badge rounded-pill dz-reminder-type-badge--card" style="font-size: 0.65rem; font-weight: 700;">
                                        {{ count($invoiceReminders) }}
                                    </span>
                                </div>
                            </div>
                            <div class="dz-reminder-track">
                                @foreach($invoiceReminders as $inv)
                                    @php
                                        $isOverdue = !empty($inv['is_overdue']);
                                    @endphp
                                    <div class="dz-reminder-chip dz-reminder-chip--card {{ $isOverdue ? 'dz-reminder-chip--overdue' : '' }}">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: rgba(124, 58, 237, 0.15); font-size: 0.85rem;">💳</span>
                                                <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(124, 58, 237, 0.15); color: #7C3AED;"><span class="d-none">Faturas de cartão</span>Fatura</span>
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
                                            <div class="fw-bolder dz-privacy-blur {{ $isOverdue ? 'text-danger' : '' }}" style="font-size: 1.05rem; color: var(--dz-text-title); margin-top: 0.15rem;">
                                                R$ {{ number_format((float) $inv['remaining'], 2, ',', '.') }}
                                            </div>
                                        </div>
                                        <a href="{{ $inv['statements_url'] }}" class="btn btn-sm btn-outline-primary rounded-pill w-100 py-1 fw-bold" style="font-size: 0.72rem; text-align: center;" title="Ver fatura do cartão">
                                            Ver Fatura ↗
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- 2. Recorrentes Mensais -->
                    @if($hasMonthly)
                        <div class="dz-reminder-type-block">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="dz-reminder-type-title dz-reminder-type-title--recurring">
                                        <span style="font-size: 0.85rem;">🔁</span> Recorrentes Mensais
                                    </span>
                                    <span class="badge rounded-pill dz-reminder-type-badge--recurring" style="font-size: 0.65rem; font-weight: 700;">
                                        {{ count($monthlyReminders) }}
                                    </span>
                                </div>
                            </div>
                            <div class="dz-reminder-track">
                                @foreach($monthlyReminders as $rec)
                                    @php
                                        $predDay = $rec->effectiveDayInMonth($reminderCivilYear, $reminderCivilMonth);
                                    @endphp
                                    <div class="dz-reminder-chip dz-reminder-chip--recurring">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: rgba(16, 185, 129, 0.15); font-size: 0.85rem;">🔁</span>
                                                <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #059669;"><span class="d-none">Recorrentes</span>Mensal</span>
                                            </div>
                                            <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: 600; background: var(--dz-border-subtle); color: var(--dz-text-secondary);">
                                                <span class="d-none">Dia previsto: {{ sprintf('%02d/%02d/%04d', $predDay, $reminderCivilMonth, $reminderCivilYear) }}</span>
                                                Dia {{ sprintf('%02d', $predDay) }}
                                            </span>
                                        </div>
                                        <div class="dz-reminder-chip__body">
                                            <div class="fw-bold text-truncate" style="font-size: 0.88rem; color: var(--dz-text-title);" title="{{ $rec->description }}">{{ $rec->description }}</div>
                                            <div class="fw-bolder dz-privacy-blur text-success" style="font-size: 1.05rem; margin-top: 0.15rem;">
                                                R$ {{ number_format((float) $rec->amount, 2, ',', '.') }}
                                            </div>
                                        </div>
                                        <a href="{{ route('dashboard', ['prefill_recurring' => $rec->id, 'period' => sprintf('%04d-%02d', $year, $month)]) }}" class="btn btn-sm btn-success rounded-pill w-100 py-1 text-white fw-bold" style="font-size: 0.72rem; text-align: center;" title="Lançar este modelo no painel">
                                            + Lançar Mensal
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- 3. Recorrentes Múltiplos / Atalhos -->
                    @if($hasMultiple)
                        <div class="dz-reminder-type-block">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="dz-reminder-type-title dz-reminder-type-title--shortcut">
                                        <span style="font-size: 0.85rem;">⚡</span> Atalhos de Lançamento
                                    </span>
                                    <span class="badge rounded-pill dz-reminder-type-badge--shortcut" style="font-size: 0.65rem; font-weight: 700;">
                                        {{ count($multipleReminders) }}
                                    </span>
                                </div>
                            </div>
                            <div class="dz-reminder-track">
                                @foreach($multipleReminders as $rec)
                                    <div class="dz-reminder-chip dz-reminder-chip--shortcut">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: rgba(245, 158, 11, 0.15); font-size: 0.85rem;">⚡</span>
                                                <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #D97706;"><span class="d-none">Múltiplo</span>Atalho</span>
                                            </div>
                                            <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: 600; background: rgba(245, 158, 11, 0.12); color: #D97706;">
                                                Rápido
                                            </span>
                                        </div>
                                        <div class="dz-reminder-chip__body">
                                            <div class="fw-bold text-truncate" style="font-size: 0.88rem; color: var(--dz-text-title);" title="{{ $rec->description }}">{{ $rec->description }}</div>
                                            <div class="fw-bolder dz-privacy-blur" style="font-size: 1.05rem; color: #D97706; margin-top: 0.15rem;">
                                                R$ {{ number_format((float) $rec->amount, 2, ',', '.') }}
                                            </div>
                                        </div>
                                        <a href="{{ route('dashboard', ['prefill_recurring' => $rec->id, 'period' => sprintf('%04d-%02d', $year, $month)]) }}" class="btn btn-sm rounded-pill w-100 py-1 fw-bold" style="font-size: 0.72rem; text-align: center; background: rgba(245, 158, 11, 0.15); color: #D97706; border: 1px solid rgba(245, 158, 11, 0.35);" title="Lançar este atalho no painel">
                                            + Lançar Rápido
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- 4. Parcelas de Dívidas & Boletos a Vencer -->
                    @if($hasDebts)
                        <div class="dz-reminder-type-block">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="dz-reminder-type-title dz-reminder-type-title--debt">
                                        <span style="font-size: 0.85rem;">📄</span> Boletos & Contas a Vencer
                                    </span>
                                    <span class="badge rounded-pill dz-reminder-type-badge--debt" style="font-size: 0.65rem; font-weight: 700;">
                                        {{ count($debtReminders) }}
                                    </span>
                                </div>
                            </div>
                            <div class="dz-reminder-track">
                                @foreach($debtReminders as $dInst)
                                    @php
                                        $isOverdue = $dInst->isOverdue($nowForReminderOverdue);
                                        $due = $dInst->due_date ? \Carbon\Carbon::parse($dInst->due_date) : null;
                                    @endphp
                                    <div class="dz-reminder-chip dz-reminder-chip--debt {{ $isOverdue ? 'dz-reminder-chip--overdue' : '' }}">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: rgba(245, 158, 11, 0.15); font-size: 0.85rem;">📄</span>
                                                <span class="badge rounded-pill" style="font-size: 0.68rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #D97706;">Boleto / Conta</span>
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
                                            <div class="fw-bold text-truncate" style="font-size: 0.88rem; color: var(--dz-text-title);" title="{{ $dInst->debt->name }}">{{ $dInst->debt->name }}</div>
                                            <div class="fw-bolder dz-privacy-blur {{ $isOverdue ? 'text-danger' : '' }}" style="font-size: 1.05rem; color: var(--dz-text-title); margin-top: 0.15rem;">
                                                R$ {{ number_format((float) $dInst->amount, 2, ',', '.') }}
                                            </div>
                                        </div>
                                        <a href="{{ route('debts.index', ['tab' => 'agenda', 'month' => $due?->month ?? $month, 'year' => $due?->year ?? $year]) }}" class="btn btn-sm rounded-pill w-100 py-1 fw-bold" style="font-size: 0.72rem; text-align: center; background: rgba(245, 158, 11, 0.15); color: #D97706; border: 1px solid rgba(245, 158, 11, 0.35);" title="Pagar na agenda">
                                            Pagar na Agenda ↗
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @if(empty($embedded ?? false))
            </div>
        @endif
    </div>
@endif
