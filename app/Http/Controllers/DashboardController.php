<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesTransactionModalPayload;
use App\Support\TransactionListingPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    use PreparesTransactionModalPayload;

    public function index(Request $request)
    {
        $couple = Auth::user()->couple;

        $period = $request->get('period', date('Y-m'));

        $parts = explode('-', $period);
        $year = intval($parts[0] ?? date('Y'));
        $month = intval($parts[1] ?? date('m'));

        $statsTransactions = $couple->transactions()
            ->excludingCreditCardInvoicePayments()
            ->where('reference_month', $month)
            ->where('reference_year', $year)
            ->with(['accountModel', 'categorySplits.category'])
            ->latest('date')
            ->get();

        $transactions = $couple->transactions()
            ->with(['user', 'accountModel', 'categorySplits.category', 'creditCardStatementsPaidFor'])
            ->whereMatchesTransactionsListingPeriod($month, $year)
            ->whereCreditCardInstallmentVisibleInList()
            ->latest()
            ->paginate(20)
            ->appends(['period' => $period]);

        $installmentGroups = TransactionListingPresentation::installmentGroupsForPage($couple->id, $transactions->getCollection());
        $installmentGroupsModalPayload = TransactionListingPresentation::installmentGroupsModalPayload($installmentGroups);
        $creditCardPurchaseRowMeta = TransactionListingPresentation::creditCardPurchaseRowMetaForPage($transactions->getCollection(), $installmentGroups);
        $transactionDeleteMeta = [];
        $transactionAmountEditMeta = [];
        foreach ($transactions as $txRow) {
            $transactionDeleteMeta[$txRow->id] = TransactionListingPresentation::transactionDeleteMeta($txRow, $installmentGroups);
            $transactionAmountEditMeta[$txRow->id] = TransactionListingPresentation::transactionAmountEditMeta($txRow);
        }

        $totalIncome = $statsTransactions->where('type', 'income')->sum('amount');
        $totalExpense = $statsTransactions->where('type', 'expense')->sum('amount');
        $balance = $totalIncome - $totalExpense;

        $thresholdPercentage = $couple->spending_alert_threshold ?? 80.00;
        $income = $couple->monthly_income ?? 0;
        $thresholdAmount = ($income * $thresholdPercentage) / 100;
        $showAlert = $income > 0 && $totalExpense >= $thresholdAmount;

        $spendingByAccount = $statsTransactions->where('type', 'expense')
            ->whereNotNull('account_id')
            ->groupBy('account_id')
            ->map(function ($accountTransactions) {
                $account = $accountTransactions->first()->accountModel;
                if (! $account) {
                    return null;
                }

                return [
                    'account_id' => (int) $account->id,
                    'account_name' => $account->name,
                    'account_color' => $account->color,
                    'is_credit_card' => $account->isCreditCard(),
                    'account_kind_label' => $account->isCreditCard()
                        ? 'Cartão de crédito'
                        : 'Conta',
                    'total' => (float) $accountTransactions->sum('amount'),
                ];
            })
            ->filter()
            ->sortByDesc('total')
            ->values();

        return view('dashboard', array_merge(
            compact(
                'couple',
                'transactions',
                'totalIncome',
                'totalExpense',
                'balance',
                'spendingByAccount',
                'period',
                'month',
                'year',
                'showAlert',
                'thresholdPercentage',
                'thresholdAmount',
                'transactionDeleteMeta',
                'transactionAmountEditMeta',
                'installmentGroupsModalPayload',
                'creditCardPurchaseRowMeta'
            ),
            $this->transactionModalPayload()
        ));
    }
}
