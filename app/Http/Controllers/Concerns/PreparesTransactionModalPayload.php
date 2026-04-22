<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Account;
use App\Models\FinancialProject;
use App\Models\Transaction;
use App\Support\TransactionListingPresentation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

trait PreparesTransactionModalPayload
{
    /**
     * Dados compartilhados pelos modais de novo lançamento / edição rápida (Lançamentos e Painel).
     *
     * @return array<string, mixed>
     */
    protected function transactionModalPayload(): array
    {
        $couple = Auth::user()->couple;
        $now = Carbon::now();

        $categories = $couple->categories()
            ->excludingCreditCardInvoicePayment()
            ->excludingInternalTransferCategories()
            ->orderBy('name')
            ->get();
        $accounts = $couple->accounts;

        $accountsSortedForFilter = $accounts->sortBy(function (Account $a) {
            return [
                $a->isCreditCard() ? 1 : 0,
                mb_strtolower($a->name),
            ];
        })->values();

        $regularAccounts = $accounts->where('kind', Account::KIND_REGULAR)->values();
        $cardAccounts = $accounts->where('kind', Account::KIND_CREDIT_CARD)->values();

        $fundingOld = old('funding');
        if (! in_array($fundingOld, ['account', 'credit_card'], true)) {
            if ($regularAccounts->isEmpty() && $cardAccounts->isNotEmpty()) {
                $fundingOld = 'credit_card';
            } else {
                $fundingOld = 'account';
            }
        }

        $paymentFlowOld = '';
        if (old('funding') === 'credit_card') {
            $paymentFlowOld = '__credit__';
        } elseif (old('payment_method')) {
            $paymentFlowOld = (string) old('payment_method');
        }

        $txFormMode = $regularAccounts->isNotEmpty() && $cardAccounts->isNotEmpty()
            ? 'both'
            : ($cardAccounts->isNotEmpty() ? 'cards_only' : 'regular_only');

        $txAccountsPayload = [
            'regular' => $regularAccounts->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'balance_label' => number_format((float) $a->balance, 2, ',', '.'),
                'methods' => $a->getEffectivePaymentMethods(),
            ])->values()->all(),
            'cards' => $cardAccounts->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'limit_tracked' => $a->tracksCreditCardLimit(),
                'limit_total_label' => $a->tracksCreditCardLimit()
                    ? number_format((float) $a->credit_card_limit_total, 2, ',', '.')
                    : null,
                'limit_available_label' => $a->tracksCreditCardLimit()
                    ? number_format((float) $a->credit_card_limit_available, 2, ',', '.')
                    : null,
            ])->values()->all(),
        ];

        $referenceDefaultNext = Carbon::now()->startOfMonth()->addMonth();
        $refDefaultMonth = (int) $referenceDefaultNext->month;
        $refDefaultYear = (int) $referenceDefaultNext->year;

        $years = range($now->year - 5, $now->year + 5);

        $financialProjects = FinancialProject::query()
            ->where('couple_id', $couple->id)
            ->orderBy('name')
            ->get();

        $editTransactionModalMeta = null;
        $editTransactionIdSession = session('edit_transaction_id');
        if ($editTransactionIdSession !== null) {
            $editTx = Transaction::query()
                ->where('couple_id', $couple->id)
                ->whereKey((int) $editTransactionIdSession)
                ->with(['accountModel', 'categorySplits', 'creditCardStatementsPaidFor'])
                ->first();
            if ($editTx) {
                $editAllocRows = [];
                $editTx->loadMissing('categorySplits.category');
                foreach ($editTx->categorySplits()->orderBy('id')->get() as $sp) {
                    $editAllocRows[] = [
                        'category_id' => (int) $sp->category_id,
                        'amount' => number_format((float) $sp->amount, 2, '.', ''),
                    ];
                }
                $editTransactionModalMeta = [
                    'id' => $editTx->id,
                    'action' => route('transactions.update', $editTx),
                    'amount' => old('amount', $editTx->amount),
                    'description' => old('description', $editTx->baseDescriptionWithoutInstallmentSuffix()),
                    'edit' => TransactionListingPresentation::transactionAmountEditMeta($editTx),
                    'category_allocations' => $editAllocRows,
                    'financial_project_id' => old('financial_project_id', $editTx->financial_project_id),
                    'is_credit' => (bool) $editTx->accountModel?->isCreditCard(),
                ];
            }
        }

        return [
            'categories' => $categories,
            'accounts' => $accounts,
            'accountsSortedForFilter' => $accountsSortedForFilter,
            'regularAccounts' => $regularAccounts,
            'cardAccounts' => $cardAccounts,
            'fundingOld' => $fundingOld,
            'paymentFlowOld' => $paymentFlowOld,
            'txFormMode' => $txFormMode,
            'txAccountsPayload' => $txAccountsPayload,
            'refDefaultMonth' => $refDefaultMonth,
            'refDefaultYear' => $refDefaultYear,
            'years' => $years,
            'editTransactionModalMeta' => $editTransactionModalMeta,
            'financialProjects' => $financialProjects,
        ];
    }
}
