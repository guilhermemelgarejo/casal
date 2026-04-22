<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\TransactionCategorySplit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $couple = Auth::user()->couple;
        $all = $couple->categories;
        $categoriesIncome = $all->where('type', 'income')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $categoriesExpense = $all->where('type', 'expense')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $budgets = $couple->budgets()
            ->where('month', date('m'))
            ->where('year', date('Y'))
            ->whereHas('category', fn ($q) => $q->excludingCreditCardInvoicePayment()->excludingInternalTransferCategories())
            ->get();
        $spentByCategory = TransactionCategorySplit::query()
            ->whereHas('transaction', function ($q) use ($couple) {
                $q->where('couple_id', $couple->id)
                    ->where('reference_month', (int) date('m'))
                    ->where('reference_year', (int) date('Y'))
                    ->excludingCreditCardInvoicePayments()
                    ->excludingInternalTransfers();
            })
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return view('categories.index', compact(
            'categoriesIncome',
            'categoriesExpense',
            'budgets',
            'spentByCategory',
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::notIn([
                    Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
                    Category::NAME_INTERNAL_TRANSFER_EXPENSE,
                    Category::NAME_INTERNAL_TRANSFER_INCOME,
                    Category::NAME_INVESTMENTS,
                    Category::NAME_PIGGY_BANK_WITHDRAWAL,
                ]),
            ],
            'type' => 'required|in:income,expense',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
        ]);

        Category::create([
            'couple_id' => Auth::user()->couple_id,
            'name' => $request->name,
            'type' => $request->type,
            'color' => $request->color,
            'icon' => $request->icon,
        ]);

        return back()->with('success', 'Categoria criada!');
    }

    public function update(Request $request, Category $category)
    {
        if ($category->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        if ($category->isImmutableSystemCategory()) {
            return back()->withErrors([
                'name' => 'Esta categoria não pode ser editada.',
            ]);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::notIn([
                    Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
                    Category::NAME_INTERNAL_TRANSFER_EXPENSE,
                    Category::NAME_INTERNAL_TRANSFER_INCOME,
                    Category::NAME_INVESTMENTS,
                    Category::NAME_PIGGY_BANK_WITHDRAWAL,
                ]),
            ],
            'type' => 'required|in:income,expense',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
        ]);

        $category->update($request->only(['name', 'type', 'color', 'icon']));

        return back()->with('success', 'Categoria atualizada!');
    }

    public function destroy(Category $category)
    {
        if ($category->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        if ($category->isImmutableSystemCategory()) {
            return back()->withErrors([
                'category' => 'Esta categoria não pode ser excluída.',
            ]);
        }

        $category->delete();

        return back()->with('success', 'Categoria excluída!');
    }
}
