<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Auth::user()->couple->accounts;
        $paymentMethodOptions = PaymentMethods::forRegularAccounts();

        return view('accounts.index', compact('accounts', 'paymentMethodOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'kind' => ['required', 'string', Rule::in(Account::kinds())],
            'color' => 'required|string|size:7',
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', Rule::in(PaymentMethods::forRegularAccounts())],
        ]);

        $kind = $validated['kind'];
        if ($kind === Account::KIND_CREDIT_CARD) {
            $allowed = null;
        } else {
            $methods = array_values(array_unique($validated['payment_methods'] ?? []));
            if (count($methods) < 1) {
                return back()->withErrors([
                    'payment_methods' => 'Marque ao menos uma forma de pagamento para esta conta.',
                ])->withInput();
            }
            $allowed = $methods;
        }

        Auth::user()->couple->accounts()->create([
            'name' => $validated['name'],
            'kind' => $kind,
            'color' => $validated['color'],
            'allowed_payment_methods' => $allowed,
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
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', Rule::in(PaymentMethods::forRegularAccounts())],
        ]);

        $kind = $account->kind;
        if ($kind === Account::KIND_CREDIT_CARD) {
            $allowed = null;
        } else {
            $methods = array_values(array_unique($validated['payment_methods'] ?? []));
            if (count($methods) < 1) {
                return back()->withErrors([
                    'payment_methods' => 'Marque ao menos uma forma de pagamento para esta conta.',
                ])->withInput();
            }
            $allowed = $methods;
        }

        $account->update([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'allowed_payment_methods' => $allowed,
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
