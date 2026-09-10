<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Couple;
use App\Models\CreditCardStatement;
use App\Models\DebtInstallment;
use App\Models\RecurringTransaction;
use Carbon\Carbon;

final class DailyBudgetForecast
{
    /**
     * Calcula o orçamento diário para os dias restantes do mês atual (contando hoje),
     * com base na receita prevista e nos gastos contratados (faturas, recorrentes e dívidas) do próximo mês.
     *
     * @return array{
     *     current_year: int,
     *     current_month: int,
     *     current_month_label: string,
     *     days_remaining_current_month: int,
     *     current_day: int,
     *     is_viewing_current_calendar_month: bool,
     *     target_year: int,
     *     target_month: int,
     *     target_month_label: string,
     *     base_planned_income: float,
     *     recurring_incomes_total: float,
     *     recurring_incomes_count: int,
     *     recurring_incomes_items: array<int, array{id: int, description: string, day_of_month: ?int, is_multiple: bool, amount: float}>,
     *     planned_income: float,
     *     has_planned_income_configured: bool,
     *     card_invoices_total: float,
     *     card_invoices_count: int,
     *     card_invoices_items: array<int, array{account_id: int, account_name: string, account_color: string, amount: float}>,
     *     recurring_expenses_total: float,
     *     recurring_expenses_count: int,
     *     recurring_expenses_items: array<int, array{id: int, description: string, funding: string, day_of_month: ?int, is_multiple: bool, amount: float}>,
     *     debt_installments_total: float,
     *     debt_installments_count: int,
     *     debt_installments_items: array<int, array{id: int, debt_name: string, installment_number: int, due_date: ?string, amount: float}>,
     *     committed_total: float,
     *     free_amount: float,
     *     daily_budget: float,
     *     committed_pct: float,
     *     status: string,
     * }
     */
    public static function calculateForNextMonth(Couple $couple, ?int $viewYear = null, ?int $viewMonth = null, ?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $viewYear = $viewYear ?? (int) $now->year;
        $viewMonth = $viewMonth ?? (int) $now->month;

        $isViewingCurrentCalendarMonth = ($viewYear === (int) $now->year && $viewMonth === (int) $now->month);

        $currentMonthCarbon = Carbon::createFromDate($viewYear, $viewMonth, 1);
        $currentMonthLabel = $currentMonthCarbon->locale(app()->getLocale())->translatedFormat('F \d\e Y');

        $dim = (int) $currentMonthCarbon->daysInMonth;
        $currentDay = $isViewingCurrentCalendarMonth ? (int) $now->day : 1;
        // "o restante dos dias do mês atual, contando hoje"
        $daysRemaining = max(1, $dim - $currentDay + 1);

        // Próximo mês
        $targetCarbon = $currentMonthCarbon->copy()->addMonth();
        $targetYear = (int) $targetCarbon->year;
        $targetMonth = (int) $targetCarbon->month;
        $targetMonthLabel = $targetCarbon->locale(app()->getLocale())->translatedFormat('F \d\e Y');

        // 1. Receita Prevista (Renda Planejada + Receitas Recorrentes Ativas)
        $basePlannedIncome = (float) $couple->resolvePlannedMonthlyIncomeForMonth($targetYear, $targetMonth);
        if ($basePlannedIncome <= 0.005) {
            $basePlannedIncome = (float) $couple->resolvePlannedMonthlyIncomeForMonth($viewYear, $viewMonth);
        }

        $recurringIncomes = $couple->recurringTransactions()
            ->where('is_active', true)
            ->where('type', 'income')
            ->where(fn ($q) => $q->where('is_multiple', false)->orWhereNull('is_multiple'))
            ->with('account')
            ->get();

        $recurringIncomesTotal = 0.0;
        $recurringIncomeItems = [];

        foreach ($recurringIncomes as $rInc) {
            $amt = (float) $rInc->amount;
            $recurringIncomesTotal += $amt;
            $recurringIncomeItems[] = [
                'id' => (int) $rInc->id,
                'description' => (string) $rInc->description,
                'day_of_month' => $rInc->day_of_month !== null ? (int) $rInc->day_of_month : null,
                'is_multiple' => (bool) $rInc->is_multiple,
                'amount' => round($amt, 2),
            ];
        }

        $recurringIncomesTotal = round($recurringIncomesTotal, 2);
        usort($recurringIncomeItems, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);
        $plannedIncome = round($basePlannedIncome + $recurringIncomesTotal, 2);
        $hasPlannedConfigured = $plannedIncome > 0.005;

        // 2. Faturas de Cartão do Próximo Mês
        $cardAccounts = $couple->accounts()->where('kind', Account::KIND_CREDIT_CARD)->get();
        $cardInvoicesTotal = 0.0;
        $cardInvoiceItems = [];

        foreach ($cardAccounts as $card) {
            $stmt = CreditCardStatement::query()
                ->where('couple_id', $couple->id)
                ->where('account_id', $card->id)
                ->where('reference_month', $targetMonth)
                ->where('reference_year', $targetYear)
                ->first();

            $spent = $stmt !== null
                ? (float) $stmt->spent_total
                : (float) CreditCardStatement::sumCardExpensesForCycle((int) $couple->id, (int) $card->id, $targetMonth, $targetYear);

            $remaining = 0.0;
            if ($stmt !== null) {
                $remaining = $stmt->isPaid() ? 0.0 : $stmt->remainingToPay();
            } else {
                $remaining = $spent;
            }

            if ($remaining > 0.005) {
                $cardInvoicesTotal += $remaining;
                $cardInvoiceItems[] = [
                    'account_id' => (int) $card->id,
                    'account_name' => (string) $card->name,
                    'account_color' => (string) ($card->color ?: '#7C3AED'),
                    'amount' => round($remaining, 2),
                ];
            }
        }

        usort($cardInvoiceItems, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        // 3. Despesas Recorrentes do Próximo Mês
        $recurringExpenses = $couple->recurringTransactions()
            ->where('is_active', true)
            ->where('type', 'expense')
            ->where(fn ($q) => $q->where('is_multiple', false)->orWhereNull('is_multiple'))
            ->with('account')
            ->get();

        $recurringExpensesTotal = 0.0;
        $recurringItems = [];

        foreach ($recurringExpenses as $rec) {
            // Evita duplicar se for em cartão e já foi lançada no ciclo do cartão do próximo mês
            if ($rec->funding === RecurringTransaction::FUNDING_CREDIT_CARD
                && $rec->hasGeneratedForCalendarMonth($targetYear, $targetMonth)) {
                continue;
            }

            $amt = (float) $rec->amount;
            $recurringExpensesTotal += $amt;
            $recurringItems[] = [
                'id' => (int) $rec->id,
                'description' => (string) $rec->description,
                'funding' => (string) $rec->funding,
                'day_of_month' => $rec->day_of_month !== null ? (int) $rec->day_of_month : null,
                'is_multiple' => (bool) $rec->is_multiple,
                'amount' => round($amt, 2),
            ];
        }

        usort($recurringItems, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        // 4. Parcelas de Dívidas do Próximo Mês
        $debtInstallments = DebtInstallment::query()
                ->where('couple_id', $couple->id)
            ->where('status', DebtInstallment::STATUS_PENDING)
            ->whereHas('debt', fn ($q) => $q->where('is_active', true))
            ->whereYear('due_date', $targetYear)
            ->whereMonth('due_date', $targetMonth)
            ->with('debt')
            ->orderBy('due_date')
            ->get();

        $debtInstallmentsTotal = 0.0;
        $debtItems = [];

        foreach ($debtInstallments as $inst) {
            $instAmount = (float) $inst->amount;
            $debtInstallmentsTotal += $instAmount;
            $debtItems[] = [
                'id' => (int) $inst->id,
                'debt_name' => (string) ($inst->debt?->name ?? 'Dívida'),
                'installment_number' => (int) $inst->installment_number,
                'due_date' => $inst->due_date ? $inst->due_date->format('d/m/Y') : null,
                'amount' => round($instAmount, 2),
            ];
        }

        usort($debtItems, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        // 5. Consolidação
        $cardInvoicesTotal = round($cardInvoicesTotal, 2);
        $recurringExpensesTotal = round($recurringExpensesTotal, 2);
        $debtInstallmentsTotal = round($debtInstallmentsTotal, 2);

        $committedTotal = round($cardInvoicesTotal + $recurringExpensesTotal + $debtInstallmentsTotal, 2);
        $freeAmount = round($plannedIncome - $committedTotal, 2);

        // Orçamento Diário para os dias restantes do mês atual
        $dailyBudget = $freeAmount > 0.005 ? round($freeAmount / $daysRemaining, 2) : 0.0;

        // Comprometimento em %
        $committedPct = $plannedIncome > 0.005
            ? round(($committedTotal / $plannedIncome) * 100, 1)
            : ($committedTotal > 0.005 ? 100.0 : 0.0);

        $status = 'healthy';
        if ($freeAmount <= 0.005) {
            $status = 'deficit';
        } elseif ($committedPct >= 75.0) {
            $status = 'warning';
        }

        return [
            'current_year' => $viewYear,
            'current_month' => $viewMonth,
            'current_month_label' => $currentMonthLabel,
            'days_remaining_current_month' => $daysRemaining,
            'current_day' => $currentDay,
            'is_viewing_current_calendar_month' => $isViewingCurrentCalendarMonth,
            'target_year' => $targetYear,
            'target_month' => $targetMonth,
            'target_month_label' => $targetMonthLabel,
            'base_planned_income' => $basePlannedIncome,
            'recurring_incomes_total' => $recurringIncomesTotal,
            'recurring_incomes_count' => count($recurringIncomeItems),
            'recurring_incomes_items' => $recurringIncomeItems,
            'planned_income' => $plannedIncome,
            'has_planned_income_configured' => $hasPlannedConfigured,
            'card_invoices_total' => $cardInvoicesTotal,
            'card_invoices_count' => count($cardInvoiceItems),
            'card_invoices_items' => $cardInvoiceItems,
            'recurring_expenses_total' => $recurringExpensesTotal,
            'recurring_expenses_count' => count($recurringItems),
            'recurring_expenses_items' => $recurringItems,
            'debt_installments_total' => $debtInstallmentsTotal,
            'debt_installments_count' => count($debtItems),
            'debt_installments_items' => $debtItems,
            'committed_total' => $committedTotal,
            'free_amount' => $freeAmount,
            'daily_budget' => $dailyBudget,
            'committed_pct' => $committedPct,
            'status' => $status,
        ];
    }
}
