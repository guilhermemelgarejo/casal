<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index()
    {
        $couple = Auth::user()->couple;
        $transactions = $couple->transactions()->with(['category', 'user', 'accountModel'])->latest()->paginate(20);
        $categories = $couple->categories;
        $accounts = $couple->accounts;

        // Resumos por Pagamento e Conta (Apenas despesas do mês atual)
        $monthTransactions = $couple->transactions()
            ->whereMonth('date', date('m'))
            ->whereYear('date', date('Y'))
            ->where('type', 'expense')
            ->get();

        $byPaymentMethod = $monthTransactions->groupBy('payment_method')
            ->map(fn($items) => $items->sum('amount'))
            ->forget('');

        // Agrupamento por conta cadastrada (usando o nome da conta no model)
        $byAccount = $monthTransactions->whereNotNull('account_id')->groupBy('account_id')
            ->mapWithKeys(function ($items, $accountId) {
                $account = Account::find($accountId);
                return [$account->name => $items->sum('amount')];
            });

        return view('transactions.index', compact('transactions', 'categories', 'accounts', 'byPaymentMethod', 'byAccount'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'account_id' => 'nullable|exists:accounts,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:100',
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
        ]);

        $category = Category::find($request->category_id);
        if ($category->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        if ($request->account_id) {
            $account = Account::find($request->account_id);
            if ($account->couple_id !== Auth::user()->couple_id) {
                abort(403);
            }
        }

        if ($category->type !== $request->type) {
            return back()->withErrors(['category_id' => 'A categoria selecionada não corresponde ao tipo de lançamento (Receita/Despesa).'])->withInput();
        }

        Transaction::create([
            'couple_id' => Auth::user()->couple_id,
            'user_id' => Auth::id(),
            'category_id' => $request->category_id,
            'account_id' => $request->account_id,
            'description' => $request->description,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'type' => $request->type,
            'date' => $request->date,
        ]);

        return back()->with('success', 'Lançamento realizado!');
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $transaction->delete();
        return back()->with('success', 'Lançamento excluído!');
    }
}
