<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesTransactionModalPayload;
use App\Models\Account;
use App\Models\Category;
use App\Models\FinancialProject;
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
            'prefill_cofrinho' => [
                Rule::requiredIf(fn () => $request->filled('prefill_cofrinho_kind')),
                'nullable',
                'integer',
            ],
            'prefill_cofrinho_kind' => [
                Rule::requiredIf(fn () => $request->filled('prefill_cofrinho')),
                'nullable',
                'string',
                Rule::in(['aporte', 'retirada']),
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
        $year = (int) $parts[0];
        $month = (int) $parts[1];

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
            ->whereMatchesDashboardPeriod($month, $year)
            ->select('type', 'amount')
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

        $totalExpense = $statsTransactions->where('type', 'expense')->sum('amount');

        $couple->refresh();
        $plannedIncomeResolved = $couple->resolvePlannedMonthlyIncomeForMonth($year, $month);

        $thresholdPercentage = $couple->spending_alert_threshold ?? 80.00;
        $income = $plannedIncomeResolved;
        $thresholdAmount = ($income * $thresholdPercentage) / 100;
        $showAlert = $income > 0 && $totalExpense >= $thresholdAmount;

        $now = Carbon::now();
        $recurringReminders = $couple->recurringTransactions()
            ->where('is_active', true)
            ->with('account')
            ->get()
            ->filter(fn (RecurringTransaction $r) => $r->shouldShowReminder($now))
            ->values();

        $creditCardInvoiceReminders = CreditCardInvoiceReminders::openStatementsForCouple(
            (int) $couple->id,
            $couple->accounts()->where('kind', Account::KIND_CREDIT_CARD)->orderBy('name')->get(),
            $now
        );

        $modalPayload = $this->transactionModalPayload();
        /** @var Collection<int, Account> $regularAccounts */
        $regularAccounts = $modalPayload['regularAccounts'] ?? collect();
        $canCreateAccountTransfer = $regularAccounts->count() >= 2;
        $transferPaymentMethods = PaymentMethods::forRegularAccounts();
        $txFormMode = $modalPayload['txFormMode'] ?? 'regular_only';

        $txCofrinhoPrefill = null;
        $txCofrinhoPrefillBlockedReason = null;
        $prefillCofrinhoId = isset($validated['prefill_cofrinho']) ? (int) $validated['prefill_cofrinho'] : null;
        $prefillCofrinhoKind = isset($validated['prefill_cofrinho_kind']) ? (string) $validated['prefill_cofrinho_kind'] : null;

        if ($prefillCofrinhoId !== null && $prefillCofrinhoKind !== null && $prefillCofrinhoKind !== '') {
            if ($txFormMode === 'cards_only') {
                $txCofrinhoPrefillBlockedReason = 'Aportes e retiradas de cofrinho só em conta corrente. Cadastre uma conta em Gerenciar contas para lançar a partir do cofrinho.';
            } else {
                Category::ensureSavingsCategoriesForCouple((int) $couple->id);
                $project = FinancialProject::query()
                    ->where('couple_id', $couple->id)
                    ->whereKey($prefillCofrinhoId)
                    ->first();
                $investCat = Category::investmentsForCouple((int) $couple->id);
                $withdrawCat = Category::piggyBankWithdrawalForCouple((int) $couple->id);

                if ($project === null) {
                    $txCofrinhoPrefillBlockedReason = 'Cofrinho não encontrado.';
                } elseif ($investCat === null || $withdrawCat === null) {
                    $txCofrinhoPrefillBlockedReason = 'Categorias de cofrinho não encontradas.';
                } else {
                    $paymentMethod = null;
                    $accountId = null;
                    foreach (PaymentMethods::forRegularAccounts() as $pm) {
                        $acc = $regularAccounts->first(function (Account $a) use ($pm) {
                            return in_array($pm, $a->getEffectivePaymentMethods(), true);
                        });
                        if ($acc !== null) {
                            $paymentMethod = $pm;
                            $accountId = (int) $acc->id;

                            break;
                        }
                    }
                    if ($paymentMethod === null || $accountId === null) {
                        $txCofrinhoPrefillBlockedReason = 'Nenhuma conta corrente compatível com as formas de pagamento. Ajuste em Gerenciar contas.';
                    } elseif ($prefillCofrinhoKind === 'aporte') {
                        $txCofrinhoPrefill = [
                            'kind' => 'aporte',
                            'type' => 'expense',
                            'category_id' => (int) $investCat->id,
                            'financial_project_id' => (int) $project->id,
                            'description' => 'Aporte: '.$project->name,
                            'payment_method' => $paymentMethod,
                            'account_id' => $accountId,
                        ];
                    } else {
                        $txCofrinhoPrefill = [
                            'kind' => 'retirada',
                            'type' => 'income',
                            'category_id' => (int) $withdrawCat->id,
                            'financial_project_id' => (int) $project->id,
                            'description' => 'Retirada: '.$project->name,
                            'payment_method' => $paymentMethod,
                            'account_id' => $accountId,
                        ];
                    }
                }
            }
        }

        $txRecurringPrefill = null;
        $txRecurringPrefillBlockedReason = null;
        $prefillRecurringId = isset($validated['prefill_recurring']) ? (int) $validated['prefill_recurring'] : null;
        if ($txCofrinhoPrefill === null && $prefillRecurringId !== null) {
            $rt = RecurringTransaction::query()
                ->where('couple_id', $couple->id)
                ->whereKey($prefillRecurringId)
                ->with('categorySplits')
                ->first();
            if ($rt !== null) {
                $anchor = Carbon::createFromDate($year, $month, 1);
                $payload = $rt->toTransactionPrefillPayload($anchor);
                if ($txFormMode === 'regular_only' && $payload['funding'] === RecurringTransaction::FUNDING_CREDIT_CARD) {
                    $txRecurringPrefillBlockedReason = 'Este modelo usa cartão de crédito. Cadastre um cartão em Gerenciar contas para abrir o formulário já pré-preenchido.';
                } elseif ($txFormMode === 'cards_only' && $payload['funding'] === RecurringTransaction::FUNDING_ACCOUNT) {
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
                'totalExpense',
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
                'txCofrinhoPrefill',
                'txCofrinhoPrefillBlockedReason',
                'canCreateAccountTransfer',
                'transferPaymentMethods',
                'plannedIncomeResolved',
            ),
            $modalPayload
        ));
    }
}
