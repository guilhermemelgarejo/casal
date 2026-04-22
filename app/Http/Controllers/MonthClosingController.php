<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CreditCardStatement;
use App\Support\DashboardAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonthClosingController extends Controller
{
    public function show(Request $request)
    {
        $couple = Auth::user()->couple;
        $validated = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^\d{4}\-\d{2}$/'],
        ]);
        $period = (string) ($validated['period'] ?? Carbon::now()->format('Y-m'));
        [$y, $m] = array_map('intval', explode('-', $period));
        $month = $m;
        $year = $y;

        $kpi = DashboardAnalytics::dashboardKpiTotals($couple, $month, $year);
        $regularFlow = DashboardAnalytics::regularAccountCashFlow($couple, $month, $year, null);
        $cycle = DashboardAnalytics::creditCardCycleReferenceFromFilterMonth($month, $year);
        $cardSpendCycle = DashboardAnalytics::creditCardExpensesInCycle($couple, $cycle['month'], $cycle['year']);

        $top = DashboardAnalytics::topExpenseCategories($couple, $month, $year, 10);

        $cardAccounts = $couple->accounts()->where('kind', Account::KIND_CREDIT_CARD)->orderBy('name')->get();
        $cardUsage = [];
        foreach ($cardAccounts as $acc) {
            $cardUsage[] = [
                'account' => $acc,
                'total' => DashboardAnalytics::creditCardExpensesInCycle($couple, $cycle['month'], $cycle['year'], $acc->id),
                'limit_available' => (float) ($acc->credit_card_limit_available ?? 0),
            ];
        }

        $statements = CreditCardStatement::query()
            ->where('couple_id', $couple->id)
            ->where(function ($q) use ($month, $year) {
                $q->where(function ($q2) use ($month, $year) {
                    $q2->where('reference_year', $year)->where('reference_month', $month);
                })->orWhere(function ($q2) use ($month, $year) {
                    $prev = Carbon::createFromDate($year, $month, 1)->subMonth();
                    $q2->where('reference_year', (int) $prev->year)->where('reference_month', (int) $prev->month);
                });
            })
            ->with('account')
            ->orderBy('reference_year')
            ->orderBy('reference_month')
            ->get();

        $largestExpenses = $couple->transactions()
            ->whereMatchesDashboardKpiPeriod($month, $year)
            ->where('type', 'expense')
            ->with(['accountModel', 'categorySplits.category'])
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        $prev = Carbon::createFromDate($year, $month, 1)->subMonth();
        $prevPeriod = $prev->format('Y-m');
        $next = Carbon::createFromDate($year, $month, 1)->addMonth();
        $nextPeriod = $next->format('Y-m');

        return view('reports.month-closing', compact(
            'couple',
            'period',
            'month',
            'year',
            'kpi',
            'regularFlow',
            'cycle',
            'cardSpendCycle',
            'top',
            'cardUsage',
            'statements',
            'largestExpenses',
            'prevPeriod',
            'nextPeriod'
        ));
    }
}
