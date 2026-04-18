<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesTransactionModalPayload;
use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\CreditCardInvoiceReminders;
use App\Support\PaymentMethods;
use App\Support\TransactionListingPresentation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    use PreparesTransactionModalPayload;

    public function index(Request $request)
    {
        $couple = Auth::user()->couple;

        $validated = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^\d{4}\-\d{2}$/'],
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('couple_id', $couple->id),
            ],
            'prefill_recurring' => [
                'nullable',
                'integer',
                Rule::exists('recurring_transactions', 'id')->where('couple_id', $couple->id),
            ],
            'focus_transaction' => [
                'nullable',
                'integer',
                Rule::exists('transactions', 'id')->where('couple_id', $couple->id),
            ],
        ]);

        $period = (string) ($validated['period'] ?? '');
        if ($period === '') {
            $monthFromQuery = isset($validated['month']) ? (int) $validated['month'] : (int) date('m');
            $yearFromQuery = isset($validated['year']) ? (int) $validated['year'] : (int) date('Y');
            $period = sprintf('%04d-%02d', $yearFromQuery, $monthFromQuery);
        }

        $parts = explode('-', $period);
        $year = (int) ($parts[0] ?? date('Y'));
        $month = (int) ($parts[1] ?? date('m'));

        $filterAccountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;
        $filteredRegularAccountBalance = null;
        if ($filterAccountId !== null) {
            $filteredAcc = Account::query()
                ->where('couple_id', $couple->id)
                ->whereKey($filterAccountId)
                ->first();
            if ($filteredAcc && ! $filteredAcc->isCreditCard()) {
                $filteredRegularAccountBalance = (float) $filteredAcc->balance;
            }
        }

        $statsTransactions = $couple->transactions()
            ->whereMatchesDashboardKpiPeriod($month, $year)
            ->with(['accountModel', 'categorySplits.category'])
            ->latest('date')
            ->get();

        $transactionsForPeriod = $couple->transactions()
            ->with(['user', 'accountModel', 'categorySplits.category', 'creditCardStatementsPaidFor'])
            ->whereMatchesTransactionsListingPeriod($month, $year)
            ->whereCreditCardInstallmentVisibleInList()
            ->when($filterAccountId !== null, fn ($q) => $q->where('account_id', $filterAccountId))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $focusTransactionId = isset($validated['focus_transaction']) ? (int) $validated['focus_transaction'] : null;
        $transactions = $transactionsForPeriod;
        if ($focusTransactionId !== null) {
            $focused = $transactionsForPeriod->firstWhere('id', $focusTransactionId);
            if ($focused instanceof Transaction) {
                $transactions = collect([$focused]);
            } else {
                $maybeParcel = Transaction::query()
                    ->where('couple_id', $couple->id)
                    ->whereKey($focusTransactionId)
                    ->with('accountModel')
                    ->first();
                if ($maybeParcel instanceof Transaction
                    && $maybeParcel->type === 'expense'
                    && $maybeParcel->accountModel?->isCreditCard()
                    && $maybeParcel->installment_parent_id !== null) {
                    $rootId = $maybeParcel->installmentRootId();
                    $focusedRoot = $transactionsForPeriod->firstWhere('id', $rootId);
                    if ($focusedRoot instanceof Transaction) {
                        $transactions = collect([$focusedRoot]);
                        $focusTransactionId = $rootId;
                    } else {
                        $focusTransactionId = null;
                    }
                } else {
                    $focusTransactionId = null;
                }
            }
        }

        $installmentGroups = TransactionListingPresentation::installmentGroupsForPage($couple->id, $transactions);
        $installmentGroupsModalPayload = TransactionListingPresentation::installmentGroupsModalPayload($installmentGroups);
        $creditCardPurchaseRowMeta = TransactionListingPresentation::creditCardPurchaseRowMetaForPage($transactions, $installmentGroups);
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

        $modalPayload = $this->transactionModalPayload();
        /** @var Collection<int, Account> $regularAccounts */
        $regularAccounts = $modalPayload['regularAccounts'] ?? collect();
        $canCreateAccountTransfer = $regularAccounts->count() >= 2;
        $transferPaymentMethods = PaymentMethods::forRegularAccounts();

        $txRecurringPrefill = null;
        $txRecurringPrefillBlockedReason = null;
        $prefillRecurringId = isset($validated['prefill_recurring']) ? (int) $validated['prefill_recurring'] : null;
        if ($prefillRecurringId !== null) {
            $rt = RecurringTransaction::query()
                ->where('couple_id', $couple->id)
                ->whereKey($prefillRecurringId)
                ->with('categorySplits')
                ->first();
            if ($rt !== null) {
                $anchor = Carbon::createFromDate($year, $month, 1);
                $payload = $rt->toTransactionPrefillPayload($anchor);
                $txFormMode = $modalPayload['txFormMode'] ?? 'regular_only';
                if ($txFormMode === 'regular_only' && ($payload['funding'] ?? '') === RecurringTransaction::FUNDING_CREDIT_CARD) {
                    $txRecurringPrefillBlockedReason = 'Este modelo usa cartão de crédito. Cadastre um cartão em Gerenciar contas para abrir o formulário já pré-preenchido.';
                } elseif ($txFormMode === 'cards_only' && ($payload['funding'] ?? '') === RecurringTransaction::FUNDING_ACCOUNT) {
                    $txRecurringPrefillBlockedReason = 'Este modelo usa conta à ordem. Cadastre uma conta em Gerenciar contas para abrir o formulário já pré-preenchido.';
                } else {
                    $txRecurringPrefill = $payload;
                }
            }
        }

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
                'filterAccountId',
                'focusTransactionId',
                'filteredRegularAccountBalance',
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
                'txRecurringPrefillBlockedReason',
                'canCreateAccountTransfer',
                'transferPaymentMethods'
            ),
            $modalPayload
        ));
    }
}
