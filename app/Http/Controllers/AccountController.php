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
        $paymentMethodOptions = PaymentMethods::all();

        return view('accounts.index', compact('accounts', 'paymentMethodOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'kind' => ['required', 'string', Rule::in(Account::kinds())],
            'color' => 'required|string|size:7',
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => ['string', Rule::in(PaymentMethods::all())],
        ]);

        $kind = $validated['kind'];
        if ($kind === Account::KIND_CREDIT_CARD) {
            $allowed = ['Cartão de Crédito'];
        } else {
            $allowed = array_values(array_unique(array_filter(
                $validated['payment_methods'],
                fn ($m) => $m !== 'Cartão de Crédito'
            )));
            if (count($allowed) < 1) {
                return back()->withErrors([
                    'payment_methods' => 'Contas que não são cartão de crédito não podem permitir "Cartão de Crédito". Marque ao menos uma outra forma.',
                ])->withInput();
            }
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
            'kind' => ['required', 'string', Rule::in(Account::kinds())],
            'color' => 'required|string|size:7',
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => ['string', Rule::in(PaymentMethods::all())],
        ]);

        $kind = $validated['kind'];
        if ($kind === Account::KIND_CREDIT_CARD) {
            $allowed = ['Cartão de Crédito'];
        } else {
            $allowed = array_values(array_unique(array_filter(
                $validated['payment_methods'],
                fn ($m) => $m !== 'Cartão de Crédito'
            )));
            if (count($allowed) < 1) {
                return back()->withErrors([
                    'payment_methods' => 'Contas que não são cartão de crédito não podem permitir "Cartão de Crédito". Marque ao menos uma outra forma.',
                ])->withInput();
            }
        }

        $account->update([
            'name' => $validated['name'],
            'kind' => $kind,
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
