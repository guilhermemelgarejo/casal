<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesTransactionModalPayload;
use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Support\CreditCardInvoiceReminders;
use App\Support\TransactionListingPresentation;
use Carbon\Carbon;
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
            ->whereMatchesDashboardKpiPeriod($month, $year)
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

        $now = Carbon::now();
        $recurringReminders = $couple->recurringTransactions()
            ->where('is_active', true)
            ->with('account')
            ->get()
            ->filter(fn (RecurringTransaction $r) => $r->shouldShowReminder($now))
            ->values();

        $cardAccounts = $couple->accounts()
            ->where('kind', Account::KIND_CREDIT_CARD)
            ->orderBy('name')
            ->get();
        $creditCardInvoiceReminders = CreditCardInvoiceReminders::openStatementsForCouple(
            (int) $couple->id,
            $cardAccounts,
            $now
        );

        $txRecurringPrefill = null;
        $txRecurringPrefillBlockedReason = null;

        return view('dashboard', array_merge(
            compact(
                'couple',
                'transactions',
                'totalIncome',
                'totalExpense',
                'balance',
                'period',
                'month',
                'year',
                'showAlert',
                'thresholdPercentage',
                'thresholdAmount',
                'transactionDeleteMeta',
                'transactionAmountEditMeta',
                'installmentGroupsModalPayload',
                'creditCardPurchaseRowMeta',
                'recurringReminders',
                'creditCardInvoiceReminders',
                'txRecurringPrefill',
                'txRecurringPrefillBlockedReason'
            ),
            $this->transactionModalPayload()
        ));
    }
}
