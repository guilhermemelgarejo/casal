<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\TransactionCategorySplit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetReportController extends Controller
{
    public function index(Request $request)
    {
        $couple = Auth::user()->couple;
        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);
        $month = (int) ($validated['month'] ?? (int) date('n'));
        $year = (int) ($validated['year'] ?? (int) date('Y'));

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

        $budgets = Budget::query()
            ->where('couple_id', $couple->id)
            ->where('month', $month)
            ->where('year', $year)
            ->with('category')
            ->get()
            ->keyBy('category_id');

        $expenseCategories = $couple->categories()
            ->where('type', 'expense')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($expenseCategories as $cat) {
            if ($cat->isInternalTransferCategory()) {
                continue;
            }
            $real = (float) ($spentByCategory[$cat->id] ?? 0);
            $bud = $budgets->get($cat->id);
            $prev = $bud ? (float) $bud->amount : null;
            $pct = ($prev !== null && $prev > 0) ? ($real / $prev) * 100 : null;
            $rows[] = (object) [
                'category' => $cat,
                'planned' => $prev,
                'realized' => $real,
                'pct' => $pct,
                'over' => $prev !== null && $real > $prev,
                'fora' => $prev === null && $real > 0,
            ];
        }

        usort($rows, function ($a, $b) {
            if ($a->over !== $b->over) {
                return $a->over ? -1 : 1;
            }
            if ($a->fora !== $b->fora) {
                return $a->fora ? -1 : 1;
            }

            return $b->realized <=> $a->realized;
        });

        return view('reports.budget-vs-actual', compact('couple', 'month', 'year', 'rows'));
    }
}
