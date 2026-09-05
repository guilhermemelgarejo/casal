<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DebtController extends Controller
{
    public function index(Request $request): View
    {
        $couple = Auth::user()->couple;
        $activeTab = $request->input('tab', 'agenda'); // 'agenda' ou 'dividas'

        // Controles de data para a agenda
        $now = Carbon::now();
        $selectedYear = (int) $request->input('year', $now->year);
        $selectedMonth = (int) $request->input('month', $now->month);
        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = (int) $now->month;
        }
        if ($selectedYear < 2000 || $selectedYear > 2100) {
            $selectedYear = (int) $now->year;
        }

        $agendaPeriodDate = Carbon::createFromDate($selectedYear, $selectedMonth, 1);
        $prevPeriod = $agendaPeriodDate->copy()->subMonth();
        $nextPeriod = $agendaPeriodDate->copy()->addMonth();

        // 1. Dados da Agenda de Vencimentos
        $statusFilter = $request->input('status', 'all'); // 'all', 'pending', 'paid', 'overdue'
        $selectedDebtId = $request->filled('debt_id') ? (int) $request->input('debt_id') : null;
        $viewAllInstallments = $request->boolean('all_installments');

        // Consulta de parcelas da Agenda
        $agendaItemsQuery = DebtInstallment::query()
            ->where('couple_id', $couple->id)
            ->where(function ($q) {
                $q->whereHas('debt', fn ($dq) => $dq->where('type', '!=', Debt::TYPE_FREE))
                    ->orWhere('status', DebtInstallment::STATUS_PAID);
            })
            ->with(['debt', 'debt.defaultCategory', 'debt.defaultAccount', 'transaction.accountModel']);

        if ($selectedDebtId) {
            $agendaItemsQuery->where('debt_id', $selectedDebtId);
        }

        if (! $viewAllInstallments) {
            $agendaItemsQuery
                ->whereYear('due_date', $selectedYear)
                ->whereMonth('due_date', $selectedMonth);
        }

        $allAgendaItems = $agendaItemsQuery->orderBy('due_date')->get();

        // Total global de atrasadas para alertar o usuário caso haja pendências em outros meses
        $globalOverdueQuery = DebtInstallment::query()
            ->where('couple_id', $couple->id)
            ->where('status', DebtInstallment::STATUS_PENDING)
            ->whereHas('debt', fn ($dq) => $dq->where('type', '!=', Debt::TYPE_FREE))
            ->where('due_date', '<', $now->toDateString());
        $globalOverdueCount = $globalOverdueQuery->count();
        $globalOverdueAmount = (float) $globalOverdueQuery->sum('amount');

        // Totais e métricas da agenda exibida
        $totalMonthAmount = (float) $allAgendaItems->sum('amount');
        $totalMonthPaid = (float) $allAgendaItems->where('status', DebtInstallment::STATUS_PAID)->sum('amount');
        $totalMonthPending = (float) $allAgendaItems->where('status', DebtInstallment::STATUS_PENDING)->sum('amount');

        // Itens atrasados: qualquer parcela pendente com vencimento anterior a hoje (do mês atual ou anteriores)
        $overdueItems = $allAgendaItems->filter(fn (DebtInstallment $item) => $item->isOverdue($now));
        $totalOverdueAmount = (float) $overdueItems->sum('amount');
        $overdueCount = $overdueItems->count();

        // Aplicar filtro de status na lista visual
        $displayedAgendaItems = $allAgendaItems->filter(function (DebtInstallment $item) use ($statusFilter, $now) {
            if ($statusFilter === 'pending') {
                return $item->status === DebtInstallment::STATUS_PENDING && ! $item->isOverdue($now);
            }
            if ($statusFilter === 'paid') {
                return $item->status === DebtInstallment::STATUS_PAID;
            }
            if ($statusFilter === 'overdue') {
                return $item->isOverdue($now);
            }
            return true;
        })->values();

        // 2. Dados das Dívidas & Financiamentos (Macro)
        $debts = Debt::query()
            ->where('couple_id', $couple->id)
            ->with(['installments.transaction.accountModel', 'defaultAccount', 'defaultCategory', 'user'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $activeDebts = $debts->where('is_active', true)->values();
        $paidOffDebts = $debts->where('is_active', false)->values();

        $totalOriginalDebt = (float) $activeDebts->sum('total_amount');
        $totalRemainingDebt = (float) $activeDebts->sum(fn (Debt $d) => $d->remainingBalance());
        $totalPaidDebt = (float) $activeDebts->sum(fn (Debt $d) => $d->totalPaid());
        $totalProgressPct = $totalOriginalDebt > 0
            ? min(100.0, round(($totalPaidDebt / $totalOriginalDebt) * 100, 1))
            : 0;

        // Categorias e Contas para os modais
        $regularAccounts = $couple->accounts()
            ->where('kind', Account::KIND_REGULAR)
            ->orderBy('name')
            ->get();

        $categories = $couple->categories()
            ->where('is_active', true)
            ->where('type', 'expense')
            ->excludingCreditCardInvoicePayment()
            ->excludingInternalTransferCategories()
            ->orderBy('name')
            ->get();

        $members = $couple->users()->orderBy('name')->get();

        return view('debts.index', [
            'activeTab' => $activeTab,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'agendaPeriodDate' => $agendaPeriodDate,
            'prevPeriod' => $prevPeriod,
            'nextPeriod' => $nextPeriod,
            'statusFilter' => $statusFilter,
            'selectedDebtId' => $selectedDebtId,
            'viewAllInstallments' => $viewAllInstallments,
            'selectedDebt' => $selectedDebtId ? $debts->firstWhere('id', $selectedDebtId) : null,
            'displayedAgendaItems' => $displayedAgendaItems,
            'totalMonthAmount' => $totalMonthAmount,
            'totalMonthPaid' => $totalMonthPaid,
            'totalMonthPending' => $totalMonthPending,
            'totalOverdueAmount' => $totalOverdueAmount,
            'overdueCount' => $overdueCount,
            'globalOverdueCount' => $globalOverdueCount,
            'globalOverdueAmount' => $globalOverdueAmount,
            'debts' => $debts,
            'activeDebts' => $activeDebts,
            'paidOffDebts' => $paidOffDebts,
            'totalOriginalDebt' => $totalOriginalDebt,
            'totalRemainingDebt' => $totalRemainingDebt,
            'totalPaidDebt' => $totalPaidDebt,
            'totalProgressPct' => $totalProgressPct,
            'regularAccounts' => $regularAccounts,
            'categories' => $categories,
            'members' => $members,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $couple = Auth::user()->couple;

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'creditor' => 'nullable|string|max:191',
            'type' => ['required', Rule::in([Debt::TYPE_INSTALLMENTS, Debt::TYPE_FREE])],
            'total_amount' => 'required|string',
            'installment_amount' => 'nullable|string',
            'total_installments' => 'nullable|integer|min:1|max:360',
            'due_day' => 'nullable|integer|min:1|max:31',
            'start_date' => 'nullable|date',
            'default_account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('couple_id', $couple->id),
            ],
            'default_category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('couple_id', $couple->id)->where('type', 'expense'),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('couple_id', $couple->id),
            ],
            'color' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:1000',
        ]);

        $totalAmountNormalized = $this->normalizeMoneyString($validated['total_amount']);
        if ((float) $totalAmountNormalized <= 0) {
            return back()->withErrors(['total_amount' => 'O valor total deve ser maior que zero.'])->withInput();
        }

        $installmentAmountNormalized = null;
        if (! empty($validated['installment_amount'])) {
            $installmentAmountNormalized = $this->normalizeMoneyString($validated['installment_amount']);
        }

        if ($validated['type'] === Debt::TYPE_INSTALLMENTS) {
            if (empty($validated['total_installments']) || (int) $validated['total_installments'] < 1) {
                return back()->withErrors(['total_installments' => 'Informe a quantidade de parcelas.'])->withInput();
            }
            if (empty($validated['start_date'])) {
                return back()->withErrors(['start_date' => 'Informe a data do primeiro vencimento.'])->withInput();
            }

            // Validação: Valor Total não pode ser menor que a soma das parcelas
            if ($installmentAmountNormalized !== null && (float) $installmentAmountNormalized > 0) {
                $totalCount = (int) $validated['total_installments'];
                $sumOfInstallments = round((float) $installmentAmountNormalized * $totalCount, 2);
                $totalAmountFloat = round((float) $totalAmountNormalized, 2);

                if ($totalAmountFloat < ($sumOfInstallments - 0.05)) {
                    $totalFormatted = number_format($totalAmountFloat, 2, ',', '.');
                    $sumFormatted = number_format($sumOfInstallments, 2, ',', '.');
                    return back()->withErrors([
                        'total_amount' => "O valor total (R$ {$totalFormatted}) não pode ser menor que a soma das {$totalCount} parcelas (R$ {$sumFormatted}). Ajuste o valor total para incluir os juros ou recalcule o valor da parcela.",
                    ])->withInput();
                }
            }
        }

        DB::transaction(function () use ($couple, $validated, $totalAmountNormalized, $installmentAmountNormalized) {
            $debt = Debt::create([
                'couple_id' => $couple->id,
                'user_id' => $validated['user_id'] ?? null,
                'name' => $validated['name'],
                'creditor' => $validated['creditor'] ?? null,
                'type' => $validated['type'],
                'total_amount' => $totalAmountNormalized,
                'installment_amount' => $installmentAmountNormalized,
                'total_installments' => $validated['type'] === Debt::TYPE_INSTALLMENTS ? (int) $validated['total_installments'] : null,
                'due_day' => ! empty($validated['due_day']) ? (int) $validated['due_day'] : null,
                'start_date' => ! empty($validated['start_date']) ? $validated['start_date'] : null,
                'default_account_id' => $validated['default_account_id'] ?? null,
                'default_category_id' => $validated['default_category_id'] ?? null,
                'color' => $validated['color'] ?: '#f59e0b',
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
            ]);

            if ($debt->isInstallments()) {
                $debt->generateScheduledInstallments();
            }
        });

        $redirectTab = $validated['type'] === Debt::TYPE_INSTALLMENTS ? 'agenda' : 'dividas';
        return redirect()->route('debts.index', ['tab' => $redirectTab])
            ->with('success', 'Dívida / compromisso cadastrado com sucesso!');
    }

    public function update(Request $request, Debt $debt): RedirectResponse
    {
        $couple = Auth::user()->couple;
        abort_if((int) $debt->couple_id !== (int) $couple->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'creditor' => 'nullable|string|max:191',
            'color' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:1000',
            'default_account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('couple_id', $couple->id),
            ],
            'default_category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('couple_id', $couple->id)->where('type', 'expense'),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('couple_id', $couple->id),
            ],
            'is_active' => 'nullable|boolean',
        ]);

        $debt->update([
            'name' => $validated['name'],
            'creditor' => $validated['creditor'] ?? null,
            'color' => $validated['color'] ?: $debt->color,
            'notes' => $validated['notes'] ?? null,
            'default_account_id' => $validated['default_account_id'] ?? null,
            'default_category_id' => $validated['default_category_id'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $debt->is_active,
        ]);

        return redirect()->route('debts.index', ['tab' => 'dividas'])
            ->with('success', 'Dados da dívida atualizados com sucesso!');
    }

    public function destroy(Debt $debt): RedirectResponse
    {
        $couple = Auth::user()->couple;
        abort_if((int) $debt->couple_id !== (int) $couple->id, 403);

        $debt->delete();

        return redirect()->route('debts.index', ['tab' => 'dividas'])
            ->with('success', 'Dívida removida com sucesso!');
    }

    public function toggleActive(Debt $debt): RedirectResponse
    {
        $couple = Auth::user()->couple;
        abort_if((int) $debt->couple_id !== (int) $couple->id, 403);

        $debt->update(['is_active' => ! $debt->is_active]);

        $statusLabel = $debt->is_active ? 'reativada' : 'arquivada';
        return redirect()->route('debts.index', ['tab' => 'dividas'])
            ->with('success', "Dívida {$statusLabel} com sucesso!");
    }

    /**
     * Amortização / pagamento avulso (usado principalmente para dívidas livres/esporádicas).
     */
    public function amortize(Request $request, Debt $debt): RedirectResponse
    {
        $couple = Auth::user()->couple;
        abort_if((int) $debt->couple_id !== (int) $couple->id, 403);

        $strategy = $request->input('strategy', 'free'); // 'free', 'reduce_term', 'reduce_amount', 'select_installments'

        $validated = $request->validate([
            'strategy' => 'nullable|string|in:free,reduce_term,reduce_amount,select_installments',
            'amount' => 'required|string',
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('couple_id', $couple->id)
                    ->where('kind', Account::KIND_REGULAR),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('couple_id', $couple->id)->where('type', 'expense'),
            ],
            'paid_at' => 'required|date',
            'notes' => 'nullable|string|max:255',
            // Modalidade 1: Reduzir Prazo
            'term_installments_count' => 'nullable|integer|min:1',
            'residual_installment_id' => 'nullable|integer',
            'residual_new_amount' => 'nullable|string',
            // Modalidade 2: Reduzir Parcela
            'new_installment_amount' => 'nullable|string',
            // Modalidade 3: Selecionar Parcelas
            'selected_installments' => 'nullable|array',
            'selected_installments.*.id' => 'nullable|integer',
            'selected_installments.*.paid_amount' => 'nullable|string',
            'selected_installments.*.is_fully_paid' => 'nullable|string',
            'selected_installments.*.new_remaining_amount' => 'nullable|string',
        ]);

        $amountNormalized = $this->normalizeMoneyString($validated['amount']);
        if ((float) $amountNormalized <= 0) {
            return back()->withErrors(['amount' => 'Informe um valor maior que zero.'])->withInput();
        }

        DB::transaction(function () use ($debt, $couple, $validated, $amountNormalized, $strategy) {
            $account = Account::findOrFail($validated['account_id']);
            $dateObj = Carbon::parse($validated['paid_at']);
            $desc = "Amortização: {$debt->name}";

            $tx = Transaction::create([
                'couple_id' => $couple->id,
                'user_id' => Auth::id(),
                'account_id' => $account->id,
                'description' => $desc,
                'amount' => $amountNormalized,
                'payment_method' => $account->getEffectivePaymentMethods()[0] ?? 'Pix',
                'type' => 'expense',
                'date' => $validated['paid_at'],
                'reference_month' => (int) $dateObj->month,
                'reference_year' => (int) $dateObj->year,
                'notes' => $validated['notes'] ?? null,
            ]);

            $catId = $validated['category_id'] ?? $debt->default_category_id;
            if ($catId) {
                $tx->syncCategorySplits([
                    [
                        'category_id' => $catId,
                        'amount' => $amountNormalized,
                    ],
                ]);
            }

            if ($debt->isFree() || $strategy === 'free') {
                // Fluxo de dívida livre
                $nextNum = $debt->installments()->count() + 1;
                $debt->installments()->create([
                    'couple_id' => $couple->id,
                    'installment_number' => $nextNum,
                    'due_date' => $validated['paid_at'],
                    'amount' => $amountNormalized,
                    'status' => DebtInstallment::STATUS_PAID,
                    'paid_at' => $validated['paid_at'],
                    'transaction_id' => $tx->id,
                    'notes' => $validated['notes'] ?? 'Amortização avulsa',
                ]);
            } elseif ($strategy === 'reduce_term') {
                // Modalidade 1: Reduzir Prazo (quitar parcelas do final)
                $countToSettle = (int) ($validated['term_installments_count'] ?? 1);
                $pendingDesc = $debt->pendingInstallments()->reorder('installment_number', 'desc')->get();
                $installmentsToSettle = $pendingDesc->take($countToSettle);
                $numSettled = count($installmentsToSettle);

                if ($numSettled > 0) {
                    $unitAmount = round((float) $amountNormalized / $numSettled, 2);
                    foreach ($installmentsToSettle as $index => $inst) {
                        $instAmount = ($index === $numSettled - 1)
                            ? ((float) $amountNormalized - ($unitAmount * ($numSettled - 1)))
                            : $unitAmount;

                        $inst->update([
                            'amount' => $instAmount,
                            'paid_amount' => $instAmount,
                            'status' => DebtInstallment::STATUS_PAID,
                            'paid_at' => $validated['paid_at'],
                            'transaction_id' => $tx->id,
                        ]);
                    }
                }

                // Ajusta o valor da parcela residual caso informada
                if (! empty($validated['residual_installment_id']) && isset($validated['residual_new_amount'])) {
                    $residualNormalized = $this->normalizeMoneyString($validated['residual_new_amount']);
                    $residualInst = $debt->installments()->where('id', (int) $validated['residual_installment_id'])->first();
                    if ($residualInst && $residualInst->isPending()) {
                        $residualInst->update(['amount' => $residualNormalized]);
                    }
                }
            } elseif ($strategy === 'reduce_amount') {
                // Modalidade 2: Reduzir Valor das Parcelas
                $newAmountNormalized = ! empty($validated['new_installment_amount'])
                    ? $this->normalizeMoneyString($validated['new_installment_amount'])
                    : null;

                // Registra o aporte amortizado
                $nextNum = ($debt->installments()->max('installment_number') ?? 0) + 1;
                $debt->installments()->create([
                    'couple_id' => $couple->id,
                    'installment_number' => $nextNum,
                    'due_date' => $validated['paid_at'],
                    'original_amount' => $amountNormalized,
                    'amount' => $amountNormalized,
                    'paid_amount' => $amountNormalized,
                    'status' => DebtInstallment::STATUS_PAID,
                    'paid_at' => $validated['paid_at'],
                    'transaction_id' => $tx->id,
                    'notes' => (! empty($validated['notes'])) ? $validated['notes'] : 'Redução de parcelas',
                ]);

                // Se o novo valor das parcelas não foi especificado, calcula com base no aporte
                if ($newAmountNormalized === null) {
                    $pendingCount = $debt->pendingInstallments()->count();
                    if ($pendingCount > 0) {
                        $totalPending = (float) $debt->pendingInstallments()->sum('amount');
                        $newRemaining = max(0, $totalPending - (float) $amountNormalized);
                        $newAmountNormalized = number_format($newRemaining / $pendingCount, 2, '.', '');
                    }
                }

                // Atualiza todas as parcelas pendentes com o novo valor definido pelo usuário
                if ($newAmountNormalized !== null && (float) $newAmountNormalized >= 0) {
                    $debt->pendingInstallments()->update([
                        'amount' => $newAmountNormalized,
                    ]);
                }
            } elseif ($strategy === 'select_installments') {
                // Modalidade 3: Escolher Parcelas Específicas
                $selectedList = $validated['selected_installments'] ?? [];
                foreach ($selectedList as $item) {
                    if (empty($item['id'])) continue;
                    $inst = $debt->installments()->where('id', (int) $item['id'])->first();
                    if (! $inst) continue;

                    $isFullyPaid = ! isset($item['is_fully_paid']) || $item['is_fully_paid'] === '1' || $item['is_fully_paid'] === true;
                    $paidItemAmount = ! empty($item['paid_amount']) ? $this->normalizeMoneyString($item['paid_amount']) : $inst->amount;

                    if ($isFullyPaid) {
                        $inst->update([
                            'amount' => $paidItemAmount,
                            'paid_amount' => $paidItemAmount,
                            'status' => DebtInstallment::STATUS_PAID,
                            'paid_at' => $validated['paid_at'],
                            'transaction_id' => $tx->id,
                        ]);
                    } else {
                        // Pagamento parcial: define o novo valor em que a parcela ficará
                        if (isset($item['new_remaining_amount'])) {
                            $remainingNormalized = $this->normalizeMoneyString($item['new_remaining_amount']);
                            $inst->update(['amount' => $remainingNormalized]);
                        }

                        $nextNum = ($debt->installments()->max('installment_number') ?? 0) + 1;
                        $debt->installments()->create([
                            'couple_id' => $couple->id,
                            'installment_number' => $nextNum,
                            'due_date' => $validated['paid_at'],
                            'original_amount' => $paidItemAmount,
                            'amount' => $paidItemAmount,
                            'paid_amount' => $paidItemAmount,
                            'status' => DebtInstallment::STATUS_PAID,
                            'paid_at' => $validated['paid_at'],
                            'transaction_id' => $tx->id,
                            'notes' => "Amortização parcial da parcela #{$inst->installment_number}",
                        ]);
                    }
                }
            }

            // Se quitou totalmente, inativa a dívida
            if ($debt->isPaidOff()) {
                $debt->update(['is_active' => false]);
            }
        });

        $redirectUrl = $request->input('redirect_to');
        $scheduleDebtId = $request->input('schedule_debt_id');

        if ($redirectUrl && str_starts_with($redirectUrl, url('/'))) {
            $redirect = redirect($redirectUrl);
        } elseif ($request->headers->has('referer')) {
            $redirect = redirect()->back();
        } else {
            $redirect = redirect()->route('debts.index', ['tab' => 'dividas']);
        }

        if ($scheduleDebtId) {
            $redirect->with('open_schedule_debt_id', (int) $scheduleDebtId);
        }

        return $redirect->with('success', 'Amortização registrada e saldo bancário debitado com sucesso!');
    }

    /**
     * Baixa/Pagamento de uma parcela agendada da Agenda de Vencimentos.
     */
    public function payInstallment(Request $request, DebtInstallment $installment): RedirectResponse
    {
        $couple = Auth::user()->couple;
        abort_if((int) $installment->couple_id !== (int) $couple->id, 403);

        $debt = $installment->debt;

        $validated = $request->validate([
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('couple_id', $couple->id)
                    ->where('kind', Account::KIND_REGULAR),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('couple_id', $couple->id)->where('type', 'expense'),
            ],
            'paid_at' => 'required|date',
            'amount' => 'required|string',
            'notes' => 'nullable|string|max:255',
        ]);

        $amountNormalized = $this->normalizeMoneyString($validated['amount']);
        if ((float) $amountNormalized <= 0) {
            return redirect()->back()->withErrors(['amount' => 'O valor pago deve ser maior que zero.']);
        }

        $account = Account::findOrFail($validated['account_id']);
        $catId = $validated['category_id'] ?? $debt->default_category_id;

        DB::transaction(function () use ($installment, $debt, $couple, $validated, $amountNormalized) {
            $account = Account::findOrFail($validated['account_id']);
            $dateObj = Carbon::parse($validated['paid_at']);
            $totalCount = $debt->total_installments ?: $debt->installments()->count();
            $desc = "Pagamento: {$debt->name} (Parcela {$installment->installment_number}/{$totalCount})";

            $tx = Transaction::create([
                'couple_id' => $couple->id,
                'user_id' => Auth::id(),
                'account_id' => $account->id,
                'description' => $desc,
                'amount' => $amountNormalized,
                'payment_method' => $account->getEffectivePaymentMethods()[0] ?? 'Pix',
                'type' => 'expense',
                'date' => $validated['paid_at'],
                'reference_month' => (int) $dateObj->month,
                'reference_year' => (int) $dateObj->year,
            ]);

            $catId = $validated['category_id'] ?? $debt->default_category_id;
            if ($catId) {
                $tx->syncCategorySplits([
                    [
                        'category_id' => $catId,
                        'amount' => $amountNormalized,
                    ],
                ]);
            }

            // Se ainda não tinha original_amount gravado, define como o valor de contrato atual
            $origAmt = $installment->original_amount ?? $installment->amount;

            $installment->update([
                'original_amount' => $origAmt,
                'amount' => $amountNormalized,
                'paid_amount' => $amountNormalized,
                'status' => DebtInstallment::STATUS_PAID,
                'paid_at' => $validated['paid_at'],
                'transaction_id' => $tx->id,
                'notes' => $validated['notes'] ?? $installment->notes,
            ]);

            // Se todas as parcelas foram pagas, marcar como quitada
            if ($debt->isPaidOff()) {
                $debt->update(['is_active' => false]);
            }
        });

        $redirectUrl = $request->input('redirect_to');
        $scheduleDebtId = $request->input('schedule_debt_id');

        if ($redirectUrl && str_starts_with($redirectUrl, url('/'))) {
            $redirect = redirect($redirectUrl);
        } elseif ($request->headers->has('referer')) {
            $redirect = redirect()->back();
        } else {
            $due = $installment->due_date ? Carbon::parse($installment->due_date) : Carbon::now();
            $redirect = redirect()->route('debts.index', [
                'tab' => 'agenda',
                'month' => $due->month,
                'year' => $due->year,
            ]);
        }

        if ($scheduleDebtId) {
            $redirect->with('open_schedule_debt_id', (int) $scheduleDebtId);
        }

        return $redirect->with('success', 'Parcela quitada e despesa lançada na conta com sucesso!');
    }

    /**
     * Desfazer quitação de parcela (exclui a transação correspondente e restaura o saldo bancário).
     */
    public function unpayInstallment(Request $request, DebtInstallment $installment): RedirectResponse
    {
        $couple = Auth::user()->couple;
        abort_if((int) $installment->couple_id !== (int) $couple->id, 403);

        $debt = $installment->debt;
        $isFree = $debt && $debt->isFree();
        $isExtraordinary = $installment->isExtraordinaryAmortization();
        $due = $installment->due_date ? Carbon::parse($installment->due_date) : Carbon::now();

        DB::transaction(function () use ($installment, $debt, $isFree, $isExtraordinary) {
            if ($installment->transaction_id) {
                $tx = Transaction::find($installment->transaction_id);
                if ($tx) {
                    $tx->delete(); // Hook em Transaction já cuida da remoção/reversão
                }
            }

            if ($isFree || $isExtraordinary) {
                if ($installment->exists) {
                    $installment->delete();
                }

                // Se era uma amortização extraordinária em dívida parcelada,
                // restaura o valor original das parcelas pendentes que haviam sido reduzidas
                if ($debt && $debt->isInstallments()) {
                    foreach ($debt->pendingInstallments()->get() as $pInst) {
                        if ($pInst->original_amount !== null && abs((float) $pInst->original_amount - (float) $pInst->amount) > 0.01) {
                            $pInst->update(['amount' => $pInst->original_amount]);
                        }
                    }
                }
            } else {
                if ($installment->exists) {
                    $originalAmt = $installment->original_amount ?? $installment->amount;
                    $installment->update([
                        'status' => DebtInstallment::STATUS_PENDING,
                        'paid_at' => null,
                        'paid_amount' => null,
                        'amount' => $originalAmt,
                        'transaction_id' => null,
                    ]);
                }
            }

            // Reativa a dívida caso tivesse sido marcada como concluída
            if ($debt && ! $debt->is_active) {
                $debt->update(['is_active' => true]);
            }
        });

        $redirectUrl = $request->input('redirect_to');
        $scheduleDebtId = $request->input('schedule_debt_id');

        if ($redirectUrl && str_starts_with($redirectUrl, url('/'))) {
            $redirect = redirect($redirectUrl);
        } elseif ($request->headers->has('referer')) {
            $redirect = redirect()->back();
        } else {
            $tab = $isFree ? 'dividas' : 'agenda';
            $redirect = redirect()->route('debts.index', [
                'tab' => $tab,
                'month' => $due->month,
                'year' => $due->year,
            ]);
        }

        if ($scheduleDebtId) {
            $redirect->with('open_schedule_debt_id', (int) $scheduleDebtId);
        }

        $successMsg = $isExtraordinary
            ? 'Amortização desfeita, saldo restaurado e parcelas pendentes retornadas ao valor original!'
            : 'Pagamento desfeito e saldo restaurado!';

        return $redirect->with('success', $successMsg);
    }

    public function resetInstallmentAmount(Request $request, DebtInstallment $installment): RedirectResponse
    {
        $couple = Auth::user()->couple;
        abort_if((int) $installment->couple_id !== (int) $couple->id, 403);

        $debt = $installment->debt;
        abort_if(! $debt || ! $debt->isInstallments(), 404);

        if ($installment->original_amount !== null) {
            $installment->update([
                'amount' => $installment->original_amount,
            ]);
        }

        $redirectUrl = $request->input('redirect_to');
        $scheduleDebtId = $request->input('schedule_debt_id');

        if ($redirectUrl && str_starts_with($redirectUrl, url('/'))) {
            $redirect = redirect($redirectUrl);
        } elseif ($request->headers->has('referer')) {
            $redirect = redirect()->back();
        } else {
            $due = $installment->due_date ? Carbon::parse($installment->due_date) : Carbon::now();
            $redirect = redirect()->route('debts.index', [
                'tab' => 'agenda',
                'month' => $due->month,
                'year' => $due->year,
            ]);
        }

        if ($scheduleDebtId) {
            $redirect->with('open_schedule_debt_id', (int) $scheduleDebtId);
        }

        $origFormatted = number_format((float) ($installment->original_amount ?? $installment->amount), 2, ',', '.');
        return $redirect->with('success', "Parcela #{$installment->installment_number} restaurada para o valor original de R$ {$origFormatted}.");
    }

    public function resetAllInstallmentsAmount(Request $request, Debt $debt): RedirectResponse
    {
        $couple = Auth::user()->couple;
        abort_if((int) $debt->couple_id !== (int) $couple->id, 403);
        abort_if(! $debt->isInstallments(), 404);

        $count = 0;
        foreach ($debt->pendingInstallments()->get() as $inst) {
            if ($inst->original_amount !== null && abs((float) $inst->original_amount - (float) $inst->amount) > 0.01) {
                $inst->update(['amount' => $inst->original_amount]);
                $count++;
            }
        }

        $redirectUrl = $request->input('redirect_to');
        $scheduleDebtId = $request->input('schedule_debt_id', $debt->id);

        if ($redirectUrl && str_starts_with($redirectUrl, url('/'))) {
            $redirect = redirect($redirectUrl);
        } elseif ($request->headers->has('referer')) {
            $redirect = redirect()->back();
        } else {
            $redirect = redirect()->route('debts.index', ['tab' => 'agenda']);
        }

        if ($scheduleDebtId) {
            $redirect->with('open_schedule_debt_id', (int) $scheduleDebtId);
        }

        return $redirect->with('success', "{$count} parcela(s) pendente(s) restaurada(s) para o valor original do contrato.");
    }

    private function normalizeMoneyString(string $val): string
    {
        $clean = trim($val);
        $clean = preg_replace('/[^\d,\.]/', '', $clean);
        if ($clean === '') {
            return '0.00';
        }

        $hasComma = str_contains($clean, ',');
        $hasDot = str_contains($clean, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($clean, ',');
            $lastDot = strrpos($clean, '.');
            if ($lastComma > $lastDot) {
                // Formato BR: 1.300,50 -> remove pontos de milhar, troca vírgula por ponto
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                // Formato US: 1,300.50 -> remove vírgulas de milhar
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($hasComma) {
            // Apenas vírgula: 1300,50 ou 1,5 -> vírgula decimal
            $clean = str_replace(',', '.', $clean);
        } elseif ($hasDot) {
            // Apenas ponto: verificar se são milhares (ex: 1.300, 15.000 ou 1.000.000)
            if (substr_count($clean, '.') > 1) {
                $clean = str_replace('.', '', $clean);
            } elseif (preg_match('/^\d{1,3}\.\d{3}$/', $clean)) {
                $clean = str_replace('.', '', $clean);
            }
        }

        return number_format((float) $clean, 2, '.', '');
    }
}
