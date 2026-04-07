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
            'credit_card_invoice_due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'credit_card_limit_total' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
        ]);

        $isCard = $validated['kind'] === Account::KIND_CREDIT_CARD;

        $limitTotal = null;
        if ($isCard && $request->filled('credit_card_limit_total')) {
            $limitTotal = number_format((float) $validated['credit_card_limit_total'], 2, '.', '');
        }

        $account = Auth::user()->couple->accounts()->create([
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'color' => $validated['color'],
            'credit_card_invoice_due_day' => $isCard
                ? ($request->filled('credit_card_invoice_due_day')
                    ? (int) $validated['credit_card_invoice_due_day']
                    : 10)
                : null,
        ]);

        if ($isCard && $limitTotal !== null) {
            $account->forceFill(['credit_card_limit_total' => $limitTotal])->save();
            $account->recalculateCreditCardLimitAvailable();
        }

        return back()->with('success', 'Conta cadastrada com sucesso!');
    }

    public function update(Request $request, Account $account)
    {
        if ($account->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'color' => 'required|string|size:7',
        ];
        if ($account->isCreditCard()) {
            $rules['credit_card_invoice_due_day'] = ['nullable', 'integer', 'min:1', 'max:31'];
            $rules['credit_card_limit_total'] = ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'];
        }

        $validated = $request->validate($rules);

        $account->update([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'credit_card_invoice_due_day' => $account->isCreditCard()
                ? ($request->filled('credit_card_invoice_due_day')
                    ? (int) $request->credit_card_invoice_due_day
                    : null)
                : null,
        ]);

        if ($account->isCreditCard()) {
            $limitTotal = $request->filled('credit_card_limit_total')
                ? number_format((float) $validated['credit_card_limit_total'], 2, '.', '')
                : null;
            $account->forceFill(['credit_card_limit_total' => $limitTotal])->save();
            $account->recalculateCreditCardLimitAvailable();
        }

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
