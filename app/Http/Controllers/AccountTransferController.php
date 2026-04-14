<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountTransferController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $coupleId = (int) Auth::user()->couple_id;

        if ($request->has('amount')) {
            $request->merge([
                'amount' => str_replace(',', '.', (string) $request->input('amount')),
            ]);
        }

        $validated = $request->validate([
            'from_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('couple_id', $coupleId),
            ],
            'to_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('couple_id', $coupleId),
                'different:from_account_id',
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethods::forRegularAccounts())],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $from = Account::query()->where('couple_id', $coupleId)->whereKey($validated['from_account_id'])->firstOrFail();
        $to = Account::query()->where('couple_id', $coupleId)->whereKey($validated['to_account_id'])->firstOrFail();

        if ($from->isCreditCard() || $to->isCreditCard()) {
            return back()->withErrors([
                'to_account_id' => ['Transferências só são permitidas entre contas correntes (não cartão de crédito).'],
            ])->withInput();
        }

        if (! $from->allowsPaymentMethod($validated['payment_method'])) {
            return back()->withErrors([
                'payment_method' => ['Esta forma de pagamento não está habilitada para a conta de origem.'],
            ])->withInput();
        }

        Category::ensureInternalTransferCategoriesForCouple($coupleId);
        $catExpense = Category::internalTransferExpenseForCouple($coupleId);
        $catIncome = Category::internalTransferIncomeForCouple($coupleId);
        if ($catExpense === null || $catIncome === null) {
            return back()->withErrors([
                'amount' => ['Não foi possível preparar as categorias de transferência. Tente novamente.'],
            ])->withInput();
        }

        $amountNormalized = str_replace(',', '.', (string) $validated['amount']);
        $amountFormatted = number_format((float) $amountNormalized, 2, '.', '');
        $date = Carbon::parse($validated['date']);
        $refMonth = (int) $date->month;
        $refYear = (int) $date->year;
        $baseDesc = trim((string) ($validated['description'] ?? ''));
        if ($baseDesc === '') {
            $expenseDesc = 'Transferência → '.$to->name;
            $incomeDesc = 'Transferência ← '.$from->name;
        } else {
            $expenseDesc = $baseDesc;
            $incomeDesc = $baseDesc;
        }

        $groupId = (string) Str::uuid();
        $userId = (int) Auth::id();
        $splitRow = [['category_id' => $catExpense->id, 'amount' => $amountFormatted]];
        $splitRowIn = [['category_id' => $catIncome->id, 'amount' => $amountFormatted]];

        DB::transaction(function () use (
            $coupleId,
            $userId,
            $from,
            $to,
            $amountFormatted,
            $validated,
            $date,
            $refMonth,
            $refYear,
            $expenseDesc,
            $incomeDesc,
            $groupId,
            $splitRow,
            $splitRowIn
        ) {
            $expense = Transaction::create([
                'couple_id' => $coupleId,
                'user_id' => $userId,
                'account_id' => $from->id,
                'description' => $expenseDesc,
                'amount' => $amountFormatted,
                'payment_method' => $validated['payment_method'],
                'type' => 'expense',
                'date' => $date->toDateString(),
                'reference_month' => $refMonth,
                'reference_year' => $refYear,
                'internal_transfer_group_id' => $groupId,
            ]);
            $expense->syncCategorySplits($splitRow);

            $income = Transaction::create([
                'couple_id' => $coupleId,
                'user_id' => $userId,
                'account_id' => $to->id,
                'description' => $incomeDesc,
                'amount' => $amountFormatted,
                'payment_method' => $validated['payment_method'],
                'type' => 'income',
                'date' => $date->toDateString(),
                'reference_month' => $refMonth,
                'reference_year' => $refYear,
                'internal_transfer_group_id' => $groupId,
            ]);
            $income->syncCategorySplits($splitRowIn);
        });

        $previous = (string) url()->previous();
        if ($previous === '' || str_contains($previous, '/accounts/transfer')) {
            $previous = route('accounts.index');
        }

        return redirect()
            ->to($previous)
            ->with('success', 'Transferência registrada: saída em '.$from->name.' e entrada em '.$to->name.'.');
    }
}
