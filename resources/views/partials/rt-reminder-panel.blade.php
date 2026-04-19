{{--
    Lembretes: modelos recorrentes + faturas de cartão em aberto (painel e /recorrentes).
    Obrigatório: $reminders (collection de RecurringTransaction), $month (int), $year (int).
    Opcional: $invoiceReminders (collection de arrays — ver CreditCardInvoiceReminders::openStatementsForCouple);
    $title, $description (HTML); $manageUrl (padrão = /recorrentes; vazio oculta), $manageLabel;
    $invoiceManageUrl (padrão = faturas-cartão sem filtro), $invoiceManageLabel
--}}
@php
    $invoiceReminders = $invoiceReminders ?? collect();
    $hasRecurring = $reminders->isNotEmpty();
    $hasInvoices = $invoiceReminders->isNotEmpty();
    $showPanel = $hasRecurring || $hasInvoices;
    $bothReminderKinds = $hasRecurring && $hasInvoices;

    if (! isset($title)) {
        if ($bothReminderKinds) {
            $title = 'Lembretes';
        } elseif ($hasInvoices) {
            $title = 'Faturas em aberto';
        } else {
            $title = 'Lembretes deste mês';
        }
    }
    if (! isset($description)) {
        if ($bothReminderKinds) {
            $description = 'Há <strong class="fw-medium text-body">modelos recorrentes</strong> sem lançamento no mês civil atual e <strong class="fw-medium text-body">faturas de cartão</strong> com saldo em aberto.';
        } elseif ($hasInvoices) {
            $description = 'Cartões com fatura não quitada ou com pagamento parcial. Itens <strong class="fw-medium text-body">vencidos</strong> aparecem primeiro.';
        } else {
            $description = 'Ainda sem lançamento vinculado ao modelo no mês civil atual. Use o <strong class="fw-medium text-body">Painel</strong> para pré-preencher e confirmar.';
        }
    }

    $manageUrl = $manageUrl ?? route('recurring-transactions.index');
    $manageLabel = $manageLabel ?? 'Gerenciar modelos';
    $invoiceManageUrl = $invoiceManageUrl ?? route('credit-card-statements.index');
    $invoiceManageLabel = $invoiceManageLabel ?? 'Ver faturas';

    $nowForReminderOverdue = \Carbon\Carbon::now();
    $reminderCivilYear = (int) $nowForReminderOverdue->year;
    $reminderCivilMonth = (int) $nowForReminderOverdue->month;
    $reminderPanelHasOverdue = $invoiceReminders->contains(fn (array $inv) => ! empty($inv['is_overdue'] ?? false))
        || $reminders->contains(
            fn ($rec) => $rec instanceof \App\Models\RecurringTransaction
                && $rec->isReminderOverdueForCalendarMonth($nowForReminderOverdue)
        );
@endphp
@if($showPanel)
    <div class="rt-reminder-strip mb-3">
        <div class="container-xxl px-3 px-lg-4">
            <div class="rt-reminder-card border-0 shadow-sm @if($reminderPanelHasOverdue) rt-reminder-card--overdue @endif" role="status">
                <div class="rt-reminder-card__inner">
                    <div class="rt-reminder-card__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                    </div>
                    <div class="rt-reminder-card__body min-w-0">
                        <div class="rt-reminder-card__head">
                            <h3 class="rt-reminder-card__title mb-0">{{ $title }}</h3>
                            <div class="rt-reminder-card__head-actions">
                                @if($hasRecurring && $manageUrl)
                                    <a href="{{ $manageUrl }}" class="btn btn-sm rounded-pill rt-reminder-btn rt-reminder-btn--header @if($reminderPanelHasOverdue) btn-outline-danger @else btn-outline-primary @endif" data-bs-toggle="tooltip" data-bs-placement="top" title="Abrir a página de modelos recorrentes">{{ $manageLabel }}</a>
                                @endif
                                @if($hasInvoices && $invoiceManageUrl)
                                    <a href="{{ $invoiceManageUrl }}" class="btn btn-sm rounded-pill rt-reminder-btn rt-reminder-btn--header @if($reminderPanelHasOverdue) btn-outline-danger @else btn-outline-primary @endif" data-bs-toggle="tooltip" data-bs-placement="top" title="Abrir faturas de cartão">{{ $invoiceManageLabel }}</a>
                                @endif
                            </div>
                        </div>
                        <p class="rt-reminder-card__lead small text-secondary mb-0">{!! $description !!}</p>

                        <div class="rt-reminder-columns @if($bothReminderKinds) rt-reminder-columns--split @endif">
                            @if($hasRecurring)
                                <div class="rt-reminder-col rt-reminder-col--recurring min-w-0">
                                    <p class="rt-reminder-subhead small fw-semibold text-secondary mb-1 text-uppercase">Recorrentes</p>
                                    <ul class="list-unstyled rt-reminder-list mb-0">
                                        @foreach($reminders as $rec)
                                            @php
                                                $predDay = $rec->effectiveDayInMonth($reminderCivilYear, $reminderCivilMonth);
                                                $predDateLabel = sprintf('%02d/%02d/%04d', $predDay, $reminderCivilMonth, $reminderCivilYear);
                                            @endphp
                                            <li class="rt-reminder-list__item">
                                                <div class="rt-reminder-list__row rt-reminder-list__row--invoice">
                                                    <span class="rt-reminder-list__name min-w-0">
                                                        <span class="d-block text-truncate">{{ $rec->description }}</span>
                                                        <span class="d-block small text-secondary text-truncate">Dia previsto: {{ $predDateLabel }}</span>
                                                    </span>
                                                    <span class="rt-reminder-list__amount">R$ {{ number_format((float) $rec->amount, 2, ',', '.') }}</span>
                                                    <a href="{{ route('dashboard', ['prefill_recurring' => $rec->id, 'period' => sprintf('%04d-%02d', $year, $month)]) }}" class="btn btn-sm btn-primary rounded-pill rt-reminder-btn rt-reminder-list__cta" data-bs-toggle="tooltip" data-bs-placement="top" title="Ir ao painel com este modelo pré-preenchido">Criar lançamento</a>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if($hasInvoices)
                                <div class="rt-reminder-col rt-reminder-col--invoices min-w-0">
                                    <p class="rt-reminder-subhead small fw-semibold text-secondary mb-1 text-uppercase">Faturas de cartão</p>
                                    <ul class="list-unstyled rt-reminder-list mb-0">
                                        @foreach($invoiceReminders as $inv)
                                            <li class="rt-reminder-list__item">
                                                <div class="rt-reminder-list__row rt-reminder-list__row--invoice">
                                                    <span class="rt-reminder-list__name min-w-0">
                                                        <span class="d-flex flex-wrap align-items-center gap-2">
                                                            <span class="text-truncate">{{ $inv['account_name'] }} · {{ $inv['ref_label'] }}</span>
                                                            @if(!empty($inv['is_overdue']))
                                                                <span class="badge rounded-pill text-bg-warning text-dark-emphasis flex-shrink-0">Vencida</span>
                                                            @endif
                                                        </span>
                                                        @if(!empty($inv['due_label']))
                                                            <span class="d-block small text-secondary text-truncate">{{ $inv['due_label'] }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="rt-reminder-list__amount">R$ {{ number_format((float) $inv['remaining'], 2, ',', '.') }}</span>
                                                    <a href="{{ $inv['statements_url'] }}" class="btn btn-sm btn-primary rounded-pill rt-reminder-btn rt-reminder-list__cta" data-bs-toggle="tooltip" data-bs-placement="top" title="Abrir o cartão e o período desta fatura">Ver fatura</a>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
