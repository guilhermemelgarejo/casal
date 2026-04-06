<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
{
    public function index()
    {
        $couple = Auth::user()->couple;
        $categories = $couple->categories()
            ->where('type', 'expense')
            ->excludingCreditCardInvoicePayment()
            ->orderBy('name')
            ->get();
        $budgets = $couple->budgets()
            ->where('month', date('m'))
            ->where('year', date('Y'))
            ->whereHas('category', fn ($q) => $q->excludingCreditCardInvoicePayment())
            ->get();

        $spentByCategory = $couple->transactions()
            ->excludingCreditCardInvoicePayments()
            ->where('reference_month', (int) date('m'))
            ->where('reference_year', (int) date('Y'))
            ->get(['category_id', 'amount'])
            ->groupBy('category_id')
            ->map(fn ($rows) => $rows->sum('amount'));

        return view('budgets.index', compact('categories', 'budgets', 'spentByCategory'));
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

        if ($category->isCreditCardInvoicePayment()) {
            return back()->withErrors([
                'category_id' => 'Esta categoria é reservada para pagamentos de fatura de cartão. Use outra categoria no orçamento.',
            ])->withInput();
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

        return back()->with('success', 'Orçamento atualizado!');
    }

    public function updateIncome(Request $request)
    {
        $request->validate([
            'monthly_income' => 'required|numeric|min:0',
        ]);

        Auth::user()->couple->update([
            'monthly_income' => $request->monthly_income,
        ]);

        return back()->with('success', 'Renda mensal atualizada!');
    }
}
