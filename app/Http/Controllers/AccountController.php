<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Auth::user()->couple->accounts;

        return view('accounts.index', compact('accounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'kind' => ['required', 'string', Rule::in(Account::kinds())],
            'color' => 'required|string|size:7',
        ]);

        Auth::user()->couple->accounts()->create([
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'color' => $validated['color'],
            'allowed_payment_methods' => null,
        ]);

        return back()->with('success', 'Conta cadastrada com sucesso!');
    }

    public function update(Request $request, Account $account)
    {
        if ($account->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|size:7',
        ]);

        $account->update([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'allowed_payment_methods' => null,
        ]);

        return back()->with('success', 'Conta atualizada com sucesso!');
    }

    public function destroy(Account $account)
    {
        if ($account->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $account->delete();

        return back()->with('success', 'Conta excluída com sucesso!');
    }
}
