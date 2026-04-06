<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Auth::user()->couple->categories;

        return view('categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::notIn([Category::NAME_CREDIT_CARD_INVOICE_PAYMENT])],
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

        if ($category->isCreditCardInvoicePayment()) {
            return back()->withErrors([
                'name' => 'Esta categoria não pode ser editada.',
            ]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::notIn([Category::NAME_CREDIT_CARD_INVOICE_PAYMENT])],
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

        if ($category->isCreditCardInvoicePayment()) {
            return back()->withErrors([
                'category' => 'Esta categoria não pode ser excluída.',
            ]);
        }

        $category->delete();

        return back()->with('success', 'Categoria excluída!');
    }
}
