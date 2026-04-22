<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\CouplePlannedIncome;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
{
    public function index()
    {
        return redirect()
            ->route('categories.index')
            ->withFragment('orcamento');
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $category = Category::find($request->category_id);
        if ($category->couple_id !== Auth::user()->couple_id || $category->type !== 'expense') {
            abort(403);
        }

        if ($category->isCreditCardInvoicePayment() || $category->isInternalTransferCategory()) {
            return redirect()
                ->route('categories.index')
                ->withFragment('orcamento')
                ->withErrors([
                    'category_id' => 'Esta categoria é reservada pelo sistema. Use outra categoria no orçamento.',
                ])
                ->withInput();
        }

        Budget::updateOrCreate(
            [
                'couple_id' => Auth::user()->couple_id,
                'category_id' => $request->category_id,
                'month' => date('m'),
                'year' => date('Y'),
            ],
            ['amount' => $request->amount]
        );

        return redirect()
            ->route('categories.index')
            ->withFragment('orcamento')
            ->with('success', 'Orçamento atualizado!');
    }

    public function updateIncome(Request $request)
    {
        $request->validate([
            'monthly_income' => 'required|numeric|min:0',
        ]);

        $couple = Auth::user()->couple;
        $old = (float) ($couple->monthly_income ?? 0);
        $new = (float) $request->monthly_income;
        $couple->update([
            'monthly_income' => $request->monthly_income,
        ]);

        if (abs($old - $new) > 0.00001) {
            $now = Carbon::now();
            CouplePlannedIncome::recordVersion(
                (int) $couple->id,
                (int) $now->year,
                (int) $now->month,
                $new,
                (int) Auth::id()
            );
        }

        return redirect()
            ->route('categories.index')
            ->withFragment('orcamento')
            ->with('success', 'Renda mensal atualizada!');
    }
}
