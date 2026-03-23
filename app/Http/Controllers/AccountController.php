<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Auth::user()->couple->accounts;
        return view('accounts.index', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|size:7',
        ]);

        Auth::user()->couple->accounts()->create($request->all());

        return back()->with('success', 'Conta cadastrada com sucesso!');
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
