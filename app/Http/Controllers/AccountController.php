<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\CreditCardInvoiceReminders;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        $couple = Auth::user()->couple;
        $now = Carbon::now();

        $regularAccounts = $couple->accounts()
            ->where('kind', Account::KIND_REGULAR)
            ->orderByDesc('balance')
            ->orderBy('name')
            ->get();

        $creditCardAccounts = $couple->accounts()
            ->where('kind', Account::KIND_CREDIT_CARD)
            ->get();

        if ($creditCardAccounts->isNotEmpty()) {
            $reminders = CreditCardInvoiceReminders::openStatementsForCouple($couple->id, $creditCardAccounts, $now)
                ->keyBy('account_id');

            $creditCardAccounts = $creditCardAccounts->map(function (Account $card) use ($reminders) {
                $rem = $reminders->get($card->id);
                $card->current_invoice_amount = $rem ? (float) $rem['remaining'] : 0.0;
                $card->current_invoice_summary = $rem;

                return $card;
            })->sort(function (Account $a, Account $b) {
                if ($b->current_invoice_amount !== $a->current_invoice_amount) {
                    return $b->current_invoice_amount <=> $a->current_invoice_amount;
                }

                return strcasecmp($a->name, $b->name);
            })->values();
        }

        $accounts = $regularAccounts->concat($creditCardAccounts);

        $regularCount = $regularAccounts->count();
        $canCreateAccountTransfer = $regularCount >= 2;
        $transferPaymentMethods = PaymentMethods::forRegularAccounts();

        return view('accounts.index', compact(
            'accounts',
            'canCreateAccountTransfer',
            'transferPaymentMethods'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'kind' => ['required', 'string', Rule::in(Account::kinds())],
            'yields_interest' => ['nullable', 'boolean'],
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
            'yields_interest' => ! $isCard && $request->boolean('yields_interest'),
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
        } else {
            $rules['yields_interest'] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);

        $account->update([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'yields_interest' => ! $account->isCreditCard() && $request->boolean('yields_interest'),
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
        if ((int) $account->couple_id !== (int) Auth::user()->couple_id) {
            abort(403);
        }

        if ($account->transactions()->exists()) {
            return back()->with('error', 'Não é possível excluir esta conta porque existem lançamentos vinculados a ela.');
        }

        $account->delete();

        return back()->with('success', 'Conta excluída com sucesso!');
    }

    public function storeInterest(Request $request, Account $account)
    {
        if ((int) $account->couple_id !== (int) Auth::user()->couple_id) {
            abort(403);
        }

        if (! $account->yieldsInterest()) {
            return back()->with('error', 'Esta conta não está configurada para receber rendimentos.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) str_replace(',', '.', (string) $validated['amount']);
        $date = Carbon::parse($validated['date']);
        $description = ! empty($validated['description'])
            ? trim($validated['description'])
            : "Rendimento {$account->name}";

        $category = Category::accountYieldForCouple((int) Auth::user()->couple_id);

        DB::transaction(function () use ($account, $amount, $date, $description, $category) {
            $tx = Transaction::create([
                'couple_id' => Auth::user()->couple_id,
                'user_id' => Auth::id(),
                'account_id' => $account->id,
                'description' => $description,
                'amount' => number_format($amount, 2, '.', ''),
                'payment_method' => $account->getEffectivePaymentMethods()[0] ?? 'Pix',
                'type' => 'income',
                'date' => $date->toDateString(),
                'reference_month' => (int) $date->month,
                'reference_year' => (int) $date->year,
            ]);

            if ($category) {
                $tx->syncCategorySplits([
                    [
                        'category_id' => $category->id,
                        'amount' => number_format($amount, 2, '.', ''),
                    ],
                ]);
            }
        });

        return back()->with('success', 'Rendimento lançado na conta com sucesso!');
    }
}
