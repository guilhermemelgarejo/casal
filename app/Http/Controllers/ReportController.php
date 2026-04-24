<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CreditCardStatement;
use App\Models\FinancialProjectEntry;
use App\Models\RecurringTransaction;
use App\Models\TransactionCategorySplit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $couple = Auth::user()->couple;

        $validated = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^\d{4}\-\d{2}$/'],
        ]);

        $period = (string) ($validated['period'] ?? now()->format('Y-m'));
        [$year, $month] = array_map('intval', explode('-', $period));

        $anchorMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $trendMonths = collect(range(5, 0))
            ->map(fn (int $offset) => $anchorMonth->copy()->subMonths($offset))
            ->values();

        $statsTransactions = $couple->transactions()
            ->whereMatchesDashboardPeriod($month, $year)
            ->select('type', 'amount')
            ->get();

        $totalIncome = (float) $statsTransactions->where('type', 'income')->sum('amount');
        $totalExpense = (float) $statsTransactions->where('type', 'expense')->sum('amount');
        $netResult = round($totalIncome - $totalExpense, 2);

        $plannedIncomeResolved = (float) $couple->resolvePlannedMonthlyIncomeForMonth($year, $month);
        $spendingPressurePct = $plannedIncomeResolved > 0
            ? round(($totalExpense / $plannedIncomeResolved) * 100, 2)
            : 0.0;

        $budgets = $couple->budgets()
            ->with('category')
            ->where('month', $month)
            ->where('year', $year)
            ->whereHas('category', fn ($q) => $q->excludingCreditCardInvoicePayment()->excludingInternalTransferCategories())
            ->get();

        $spentByCategory = TransactionCategorySplit::query()
            ->whereHas('transaction', function ($q) use ($couple, $month, $year) {
                $q->where('couple_id', $couple->id)
                    ->where('type', 'expense')
                    ->where('reference_month', $month)
                    ->where('reference_year', $year)
                    ->excludingCreditCardInvoicePayments()
                    ->excludingInternalTransfers();
            })
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categoriesForReport = $couple->categories()
            ->excludingCreditCardInvoicePayment()
            ->excludingInternalTransferCategories()
            ->where('type', 'expense')
            ->orderBy('name')
            ->get();

        $budgetRows = $categoriesForReport->map(function ($category) use ($budgets, $spentByCategory) {
            $budgetAmount = (float) ($budgets->firstWhere('category_id', $category->id)?->amount ?? 0);
            $spentAmount = (float) ($spentByCategory[$category->id] ?? 0);
            $variance = round($budgetAmount - $spentAmount, 2);

            return [
                'name' => (string) $category->name,
                'budget' => $budgetAmount,
                'spent' => $spentAmount,
                'variance' => $variance,
                'execution_pct' => $budgetAmount > 0 ? round(($spentAmount / $budgetAmount) * 100, 2) : null,
            ];
        })
            ->filter(fn (array $row) => $row['budget'] > 0 || $row['spent'] > 0)
            ->sortByDesc('spent')
            ->values();

        $budgetTotal = (float) $budgetRows->sum('budget');
        $budgetSpentTotal = (float) $budgetRows->sum('spent');
        $budgetCommitmentPct = $plannedIncomeResolved > 0
            ? round(($budgetTotal / $plannedIncomeResolved) * 100, 2)
            : 0.0;

        $topCategoryShare = $budgetSpentTotal > 0
            ? $budgetRows->take(5)->map(function (array $row) use ($budgetSpentTotal) {
                $row['share_pct'] = round(($row['spent'] / $budgetSpentTotal) * 100, 2);

                return $row;
            })->values()
            : collect();

        $cardAccounts = $couple->accounts()
            ->where('kind', Account::KIND_CREDIT_CARD)
            ->orderBy('name')
            ->get();

        $cardRows = $cardAccounts->map(function (Account $card) {
            $outstanding = (float) Account::outstandingCreditCardUtilizationAmount($card);
            $limitTotal = $card->credit_card_limit_total !== null ? (float) $card->credit_card_limit_total : null;

            return [
                'name' => (string) $card->name,
                'limit_total' => $limitTotal,
                'outstanding' => $outstanding,
                'utilization_pct' => $limitTotal !== null && $limitTotal > 0
                    ? round(($outstanding / $limitTotal) * 100, 2)
                    : null,
            ];
        })->values();

        $openStatements = CreditCardStatement::query()
            ->with('account')
            ->where('couple_id', $couple->id)
            ->whereHas('account', fn ($q) => $q->where('kind', Account::KIND_CREDIT_CARD))
            ->orderByDesc('reference_year')
            ->orderByDesc('reference_month')
            ->get()
            ->filter(fn (CreditCardStatement $statement) => ! $statement->isPaid())
            ->map(function (CreditCardStatement $statement) {
                $remaining = $statement->remainingToPay();
                if ($remaining < 0.01) {
                    return null;
                }

                $dueDate = $statement->due_date ?? $statement->account?->defaultStatementDueDate(
                    (int) $statement->reference_month,
                    (int) $statement->reference_year
                );
                $daysToDue = $dueDate ? Carbon::today()->diffInDays($dueDate, false) : null;

                return [
                    'account_name' => (string) ($statement->account?->name ?? 'Cartão'),
                    'reference_label' => sprintf('%02d/%d', (int) $statement->reference_month, (int) $statement->reference_year),
                    'remaining' => $remaining,
                    'due_label' => $dueDate?->format('d/m/Y'),
                    'days_to_due' => $daysToDue,
                ];
            })
            ->filter()
            ->values();

        $totalLimit = (float) $cardRows->whereNotNull('limit_total')->sum('limit_total');
        $totalOutstanding = (float) $cardRows->sum('outstanding');
        $overallCardUtilizationPct = $totalLimit > 0
            ? round(($totalOutstanding / $totalLimit) * 100, 2)
            : 0.0;

        $projects = $couple->financialProjects()->orderBy('name')->get();
        $projectRows = $projects->map(function ($project) use ($month, $year) {
            $saved = (float) $project->savedProgress();
            $target = $project->target_amount !== null ? (float) $project->target_amount : null;
            $monthlyIn = (float) $project->transactions()
                ->where('type', 'expense')
                ->where('reference_month', $month)
                ->where('reference_year', $year)
                ->sum('amount');
            $monthlyOut = (float) $project->transactions()
                ->where('type', 'income')
                ->where('reference_month', $month)
                ->where('reference_year', $year)
                ->sum('amount');
            $monthlyInterest = (float) FinancialProjectEntry::query()
                ->where('couple_id', $project->couple_id)
                ->where('financial_project_id', $project->id)
                ->where('type', 'interest')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            return [
                'name' => (string) $project->name,
                'saved' => $saved,
                'target' => $target,
                'progress_pct' => $target !== null && $target > 0 ? round(($saved / $target) * 100, 2) : null,
                'monthly_net' => round(($monthlyIn - $monthlyOut) + $monthlyInterest, 2),
            ];
        })->values();

        $targetableProjects = $projectRows->filter(fn (array $row) => $row['target'] !== null && $row['target'] > 0);
        $avgProjectProgressPct = $targetableProjects->isNotEmpty()
            ? round(
                ((float) $targetableProjects->sum('saved') / (float) $targetableProjects->sum('target')) * 100,
                2
            )
            : null;

        $activeRecurring = $couple->recurringTransactions()
            ->where('is_active', true)
            ->with('account')
            ->orderBy('description')
            ->get();

        $completedRecurring = $activeRecurring->filter(
            fn (RecurringTransaction $rt) => $rt->hasGeneratedForCalendarMonth($year, $month)
        )->count();
        $activeRecurringCount = $activeRecurring->count();
        $recurringDisciplinePct = $activeRecurringCount > 0
            ? round(($completedRecurring / $activeRecurringCount) * 100, 2)
            : null;
        $pendingRecurringRows = $activeRecurring
            ->filter(fn (RecurringTransaction $rt) => ! $rt->hasGeneratedForCalendarMonth($year, $month))
            ->map(fn (RecurringTransaction $rt) => [
                'description' => (string) $rt->description,
                'amount' => (float) $rt->amount,
                'day_of_month' => (int) $rt->day_of_month,
                'account_name' => (string) ($rt->account?->name ?? 'Sem conta'),
            ])
            ->values();

        $executiveKpis = [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_result' => $netResult,
            'planned_income' => $plannedIncomeResolved,
            'spending_pressure_pct' => $spendingPressurePct,
            'budget_commitment_pct' => $budgetCommitmentPct,
        ];

        $executiveTrend = $this->buildExecutiveTrend($trendMonths, $couple);
        $budgetCommitmentTrend = $this->buildBudgetCommitmentTrend($couple->id, $trendMonths, $couple);
        $cardUtilizationTrend = $this->buildCardUtilizationTrend($couple->id, $trendMonths, $cardAccounts, $totalLimit);
        $projectMonthlyNetTrend = $this->buildProjectMonthlyNetTrend($couple->id, $trendMonths);
        $recurringDisciplineTrend = $this->buildRecurringDisciplineTrend($trendMonths, $activeRecurring);

        return view('reports.index', compact(
            'period',
            'month',
            'year',
            'executiveKpis',
            'executiveTrend',
            'budgetRows',
            'budgetTotal',
            'budgetSpentTotal',
            'topCategoryShare',
            'budgetCommitmentTrend',
            'cardRows',
            'openStatements',
            'totalLimit',
            'totalOutstanding',
            'overallCardUtilizationPct',
            'cardUtilizationTrend',
            'projectRows',
            'avgProjectProgressPct',
            'projectMonthlyNetTrend',
            'activeRecurringCount',
            'completedRecurring',
            'recurringDisciplinePct',
            'recurringDisciplineTrend',
            'pendingRecurringRows'
        ));
    }

    private function buildExecutiveTrend(Collection $trendMonths, $couple): array
    {
        $rows = $trendMonths->map(function (Carbon $monthAnchor) use ($couple) {
            $month = (int) $monthAnchor->month;
            $year = (int) $monthAnchor->year;

            $stats = $couple->transactions()
                ->whereMatchesDashboardPeriod($month, $year)
                ->select('type', 'amount')
                ->get();

            $income = (float) $stats->where('type', 'income')->sum('amount');
            $expense = (float) $stats->where('type', 'expense')->sum('amount');
            $plannedIncome = (float) $couple->resolvePlannedMonthlyIncomeForMonth($year, $month);
            $pressure = $plannedIncome > 0 ? round(($expense / $plannedIncome) * 100, 2) : 0.0;

            return [
                'label' => $monthAnchor->format('m/y'),
                'income' => $income,
                'expense' => $expense,
                'net' => round($income - $expense, 2),
                'pressure_pct' => $pressure,
            ];
        })->values();

        return [
            'labels' => $rows->pluck('label')->values()->all(),
            'net_values' => $rows->pluck('net')->values()->all(),
            'pressure_values' => $rows->pluck('pressure_pct')->values()->all(),
        ];
    }

    private function buildBudgetCommitmentTrend(int $coupleId, Collection $trendMonths, $couple): array
    {
        $values = $trendMonths->map(function (Carbon $monthAnchor) use ($coupleId, $couple) {
            $month = (int) $monthAnchor->month;
            $year = (int) $monthAnchor->year;
            $plannedIncome = (float) $couple->resolvePlannedMonthlyIncomeForMonth($year, $month);

            $budgetTotal = (float) $couple->budgets()
                ->where('month', $month)
                ->where('year', $year)
                ->whereHas('category', fn ($q) => $q->excludingCreditCardInvoicePayment()->excludingInternalTransferCategories())
                ->sum('amount');

            $spentTotal = (float) TransactionCategorySplit::query()
                ->whereHas('transaction', function ($q) use ($coupleId, $month, $year) {
                    $q->where('couple_id', $coupleId)
                        ->where('type', 'expense')
                        ->where('reference_month', $month)
                        ->where('reference_year', $year)
                        ->excludingCreditCardInvoicePayments()
                        ->excludingInternalTransfers();
                })
                ->sum('amount');

            return [
                'label' => $monthAnchor->format('m/y'),
                'commitment_pct' => $plannedIncome > 0 ? round(($budgetTotal / $plannedIncome) * 100, 2) : 0.0,
                'execution_pct' => $budgetTotal > 0 ? round(($spentTotal / $budgetTotal) * 100, 2) : 0.0,
            ];
        })->values();

        return [
            'labels' => $values->pluck('label')->values()->all(),
            'commitment_values' => $values->pluck('commitment_pct')->values()->all(),
            'execution_values' => $values->pluck('execution_pct')->values()->all(),
        ];
    }

    private function buildCardUtilizationTrend(int $coupleId, Collection $trendMonths, Collection $cardAccounts, float $totalLimit): array
    {
        $cardIds = $cardAccounts->pluck('id')->all();
        if ($cardIds === []) {
            return ['labels' => [], 'values' => []];
        }

        $values = $trendMonths->map(function (Carbon $monthAnchor) use ($coupleId, $cardIds, $totalLimit) {
            $month = (int) $monthAnchor->month;
            $year = (int) $monthAnchor->year;

            $spentInCycle = (float) CreditCardStatement::query()
                ->where('couple_id', $coupleId)
                ->whereIn('account_id', $cardIds)
                ->where('reference_month', $month)
                ->where('reference_year', $year)
                ->sum('spent_total');

            return [
                'label' => $monthAnchor->format('m/y'),
                'utilization_pct' => $totalLimit > 0 ? round(($spentInCycle / $totalLimit) * 100, 2) : 0.0,
            ];
        })->values();

        return [
            'labels' => $values->pluck('label')->values()->all(),
            'values' => $values->pluck('utilization_pct')->values()->all(),
        ];
    }

    private function buildProjectMonthlyNetTrend(int $coupleId, Collection $trendMonths): array
    {
        $values = $trendMonths->map(function (Carbon $monthAnchor) use ($coupleId) {
            $month = (int) $monthAnchor->month;
            $year = (int) $monthAnchor->year;

            $in = (float) \App\Models\Transaction::query()
                ->where('couple_id', $coupleId)
                ->whereNotNull('financial_project_id')
                ->where('type', 'expense')
                ->where('reference_month', $month)
                ->where('reference_year', $year)
                ->sum('amount');
            $out = (float) \App\Models\Transaction::query()
                ->where('couple_id', $coupleId)
                ->whereNotNull('financial_project_id')
                ->where('type', 'income')
                ->where('reference_month', $month)
                ->where('reference_year', $year)
                ->sum('amount');
            $interest = (float) FinancialProjectEntry::query()
                ->where('couple_id', $coupleId)
                ->where('type', 'interest')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            return [
                'label' => $monthAnchor->format('m/y'),
                'value' => round(($in - $out) + $interest, 2),
            ];
        })->values();

        return [
            'labels' => $values->pluck('label')->values()->all(),
            'values' => $values->pluck('value')->values()->all(),
        ];
    }

    private function buildRecurringDisciplineTrend(Collection $trendMonths, Collection $activeRecurring): array
    {
        if ($activeRecurring->isEmpty()) {
            return ['labels' => [], 'values' => []];
        }

        $values = $trendMonths->map(function (Carbon $monthAnchor) use ($activeRecurring) {
            $month = (int) $monthAnchor->month;
            $year = (int) $monthAnchor->year;
            $activeCount = $activeRecurring->count();
            $completed = $activeRecurring->filter(
                fn (RecurringTransaction $rt) => $rt->hasGeneratedForCalendarMonth($year, $month)
            )->count();
            $pct = $activeCount > 0 ? round(($completed / $activeCount) * 100, 2) : 0.0;

            return [
                'label' => $monthAnchor->format('m/y'),
                'value' => $pct,
            ];
        })->values();

        return [
            'labels' => $values->pluck('label')->values()->all(),
            'values' => $values->pluck('value')->values()->all(),
        ];
    }
}
