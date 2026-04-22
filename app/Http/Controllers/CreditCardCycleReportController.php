<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CreditCardStatement;
use App\Models\Transaction;
use App\Models\TransactionCategorySplit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CreditCardCycleReportController extends Controller
{
    public function show(Account $account, int $referenceYear, int $referenceMonth): View
    {
        $couple = Auth::user()->couple;
        abort_unless(
            $account->couple_id === $couple->id && $account->isCreditCard(),
            404
        );

        $statement = CreditCardStatement::query()
            ->where('couple_id', $couple->id)
            ->where('account_id', $account->id)
            ->where('reference_year', $referenceYear)
            ->where('reference_month', $referenceMonth)
            ->with('paymentTransactions')
            ->first();

        $spentTotal = $statement !== null
            ? (float) $statement->spent_total
            : (float) Transaction::query()
                ->where('couple_id', $couple->id)
                ->where('account_id', $account->id)
                ->where('reference_year', $referenceYear)
                ->where('reference_month', $referenceMonth)
                ->where('type', 'expense')
                ->sum('amount');

        $isPaid = $statement?->isPaid() ?? false;
        $hasPartial = $statement?->hasPartialPayments() ?? false;
        $isOpen = $statement === null || ! $isPaid || $hasPartial;

        $categoryTotals = TransactionCategorySplit::query()
            ->whereHas('transaction', function ($q) use ($couple, $account, $referenceMonth, $referenceYear) {
                $q->where('couple_id', $couple->id)
                    ->where('account_id', $account->id)
                    ->where('reference_month', $referenceMonth)
                    ->where('reference_year', $referenceYear)
                    ->where('type', 'expense')
                    ->whereNull('internal_transfer_group_id');
            })
            ->join('categories', 'categories.id', '=', 'transaction_category_splits.category_id')
            ->selectRaw('categories.name as category_name, SUM(transaction_category_splits.amount) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        $cyclePeriod = (int) $referenceYear * 12 + (int) $referenceMonth;
        $futureInstallments = collect();
        $roots = Transaction::query()
            ->where('couple_id', $couple->id)
            ->where('account_id', $account->id)
            ->where('reference_month', $referenceMonth)
            ->where('reference_year', $referenceYear)
            ->where('type', 'expense')
            ->whereNull('internal_transfer_group_id')
            ->whereNull('installment_parent_id')
            ->get();

        foreach ($roots as $root) {
            $group = Transaction::query()
                ->where('couple_id', $couple->id)
                ->where('account_id', $account->id)
                ->where(function ($q) use ($root) {
                    $q->where('id', $root->id)
                        ->orWhere('installment_parent_id', $root->id);
                })
                ->orderBy('id')
                ->get();

            if ($group->count() <= 1) {
                continue;
            }

            foreach ($group as $parcel) {
                $pPeriod = (int) $parcel->reference_year * 12 + (int) $parcel->reference_month;
                if ($pPeriod > $cyclePeriod) {
                    $futureInstallments->push($parcel);
                }
            }
        }

        $futureInstallments = $futureInstallments
            ->sortBy(fn (Transaction $t) => sprintf('%04d-%02d-%08d', $t->reference_year, $t->reference_month, $t->id))
            ->values();

        return view('reports.credit-card-cycle', [
            'couple' => $couple,
            'account' => $account,
            'referenceYear' => $referenceYear,
            'referenceMonth' => $referenceMonth,
            'statement' => $statement,
            'spentTotal' => $spentTotal,
            'isPaid' => $isPaid,
            'hasPartial' => $hasPartial,
            'isOpen' => $isOpen,
            'categoryTotals' => $categoryTotals,
            'futureInstallments' => $futureInstallments,
            'generatedAt' => Carbon::now(),
        ]);
    }
}
