<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Couple;
use App\Models\TransactionCategorySplit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardAnalytics
{
    /**
     * Mês de referência do ciclo de fatura alinhado ao KPI do painel: mês do filtro + 1.
     *
     * @return array{month:int,year:int}
     */
    public static function creditCardCycleReferenceFromFilterMonth(int $filterMonth, int $filterYear): array
    {
        $d = Carbon::createFromDate($filterYear, $filterMonth, 1)->addMonth();

        return ['month' => (int) $d->month, 'year' => (int) $d->year];
    }

    /**
     * Soma despesas em cartão no ciclo (reference_month/year), opcionalmente por cartão.
     */
    public static function creditCardExpensesInCycle(
        Couple $couple,
        int $cycleMonth,
        int $cycleYear,
        ?int $cardAccountId = null
    ): float {
        $q = $couple->transactions()
            ->where('type', 'expense')
            ->where('reference_month', $cycleMonth)
            ->where('reference_year', $cycleYear)
            ->whereNull('internal_transfer_group_id')
            ->whereHas('accountModel', fn ($a) => $a->where('kind', Account::KIND_CREDIT_CARD));

        if ($cardAccountId !== null) {
            $q->where('account_id', $cardAccountId);
        }

        return (float) $q->sum('amount');
    }

    /**
     * Fluxo em contas regular: entradas e saídas por reference_month/year (inclui pagamento fatura).
     *
     * @return array{in: float, out: float}
     */
    public static function regularAccountCashFlow(
        Couple $couple,
        int $month,
        int $year,
        ?int $regularAccountId = null
    ): array {
        $base = fn () => $couple->transactions()
            ->where('reference_month', $month)
            ->where('reference_year', $year)
            ->whereNull('internal_transfer_group_id')
            ->whereHas('accountModel', fn ($a) => $a->where('kind', Account::KIND_REGULAR))
            ->when($regularAccountId !== null, fn ($q) => $q->where('account_id', $regularAccountId));

        $in = (float) $base()->where('type', 'income')->sum('amount');
        $out = (float) $base()->where('type', 'expense')->sum('amount');

        return ['in' => $in, 'out' => $out];
    }

    /**
     * Dias corridos restantes no mês do seletor (inclusivo hoje até fim do mês), no fuso da app.
     */
    public static function calendarDaysRemainingInSelectorMonth(int $month, int $year): int
    {
        $today = Carbon::now()->startOfDay();
        $startSel = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endSel = Carbon::createFromDate($year, $month, 1)->endOfMonth()->startOfDay();

        if ($today->lt($startSel)) {
            return (int) $startSel->diffInDays($endSel) + 1;
        }

        if ($today->gt($endSel)) {
            return 0;
        }

        return (int) $today->diffInDays($endSel) + 1;
    }

    /**
     * Soma orçamentos de despesa no mês (excl. categorias internas/fatura) − realizado nas mesmas categorias.
     */
    public static function budgetHeadroomRemaining(Couple $couple, int $month, int $year): float
    {
        $budgetSum = (float) Budget::query()
            ->where('couple_id', $couple->id)
            ->where('month', $month)
            ->where('year', $year)
            ->whereHas('category', function ($q) {
                $q->where('type', 'expense')
                    ->excludingCreditCardInvoicePayment()
                    ->excludingInternalTransferCategories();
            })
            ->sum('amount');

        $spentByCategory = TransactionCategorySplit::query()
            ->whereHas('transaction', function ($q) use ($couple, $month, $year) {
                $q->where('couple_id', $couple->id)
                    ->where('reference_month', $month)
                    ->where('reference_year', $year)
                    ->excludingCreditCardInvoicePayments()
                    ->excludingInternalTransfers();
            })
            ->whereHas('category', fn ($q) => $q->where('type', 'expense'))
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $spent = (float) $spentByCategory->sum();

        return max(0.0, $budgetSum - $spent);
    }

    /**
     * @return array{income: float, expense: float, balance: float, card_spend: float}
     */
    public static function dashboardKpiTotals(Couple $couple, int $month, int $year): array
    {
        $stats = $couple->transactions()
            ->whereMatchesDashboardKpiPeriod($month, $year)
            ->get();

        $cycle = self::creditCardCycleReferenceFromFilterMonth($month, $year);
        $cardSpend = self::creditCardExpensesInCycle($couple, $cycle['month'], $cycle['year']);

        return [
            'income' => (float) $stats->where('type', 'income')->sum('amount'),
            'expense' => (float) $stats->where('type', 'expense')->sum('amount'),
            'balance' => (float) ($stats->where('type', 'income')->sum('amount') - $stats->where('type', 'expense')->sum('amount')),
            'card_spend' => $cardSpend,
        ];
    }

    /**
     * Top N categorias de despesa (splits) no mês de referência.
     *
     * @return Collection<int, object{category_id:int,name:string,total:float}>
     */
    public static function topExpenseCategories(
        Couple $couple,
        int $month,
        int $year,
        int $limit = 8
    ): Collection {
        $rows = TransactionCategorySplit::query()
            ->selectRaw('category_id, SUM(amount) as total')
            ->whereHas('transaction', function ($q) use ($couple, $month, $year) {
                $q->where('couple_id', $couple->id)
                    ->where('reference_month', $month)
                    ->where('reference_year', $year)
                    ->excludingCreditCardInvoicePayments()
                    ->excludingInternalTransfers();
            })
            ->whereHas('category', fn ($q) => $q->where('type', 'expense'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $catNames = $couple->categories()->whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');

        return $rows->map(function ($r) use ($catNames) {
            return (object) [
                'category_id' => (int) $r->category_id,
                'name' => (string) ($catNames[$r->category_id] ?? '?'),
                'total' => (float) $r->total,
            ];
        });
    }
}
