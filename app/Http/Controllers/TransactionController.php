<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();
        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $selectedMonth = (int) ($validated['month'] ?? $now->month);
        $selectedYear = (int) ($validated['year'] ?? $now->year);

        $couple = Auth::user()->couple;

        $transactions = $couple->transactions()
            ->with(['category', 'user', 'accountModel'])
            ->where('reference_month', $selectedMonth)
            ->where('reference_year', $selectedYear)
            ->latest()
            ->paginate(20);
        $transactions->appends(['month' => $selectedMonth, 'year' => $selectedYear]);

        $categories = $couple->categories;
        $accounts = $couple->accounts;

        $regularAccounts = $accounts->where('kind', Account::KIND_REGULAR)->values();
        $cardAccounts = $accounts->where('kind', Account::KIND_CREDIT_CARD)->values();

        $monthTransactionsAll = $couple->transactions()
            ->with(['accountModel'])
            ->where('reference_month', $selectedMonth)
            ->where('reference_year', $selectedYear)
            ->get();

        $expenseMonthTransactions = $monthTransactionsAll->where('type', 'expense');

        $byPaymentMethod = $expenseMonthTransactions->groupBy(function (Transaction $tx) {
            if ($tx->accountModel?->isCreditCard()) {
                return 'Cartão: '.$tx->accountModel->name;
            }

            return $tx->payment_method ?: '—';
        })
            ->map(fn ($items) => $items->sum('amount'))
            ->forget('');

        $byAccount = $expenseMonthTransactions->whereNotNull('account_id')->groupBy('account_id')
            ->mapWithKeys(function ($items, $accountId) {
                $account = Account::find($accountId);
                $accountName = $account?->name ?? 'Conta não encontrada';

                return [$accountName => $items->sum('amount')];
            });

        $fundingOld = old('funding');
        if (! in_array($fundingOld, ['account', 'credit_card'], true)) {
            if ($regularAccounts->isEmpty() && $cardAccounts->isNotEmpty()) {
                $fundingOld = 'credit_card';
            } else {
                $fundingOld = 'account';
            }
        }

        $paymentFlowOld = '';
        if (old('funding') === 'credit_card') {
            $paymentFlowOld = '__credit__';
        } elseif (old('payment_method')) {
            $paymentFlowOld = (string) old('payment_method');
        }

        $txFormMode = $regularAccounts->isNotEmpty() && $cardAccounts->isNotEmpty()
            ? 'both'
            : ($cardAccounts->isNotEmpty() ? 'cards_only' : 'regular_only');

        $txAccountsPayload = [
            'regular' => $regularAccounts->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'methods' => $a->getEffectivePaymentMethods(),
            ])->values()->all(),
            'cards' => $cardAccounts->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
            ])->values()->all(),
        ];

        $referenceDefaultNext = Carbon::now()->startOfMonth()->addMonth();
        $refDefaultMonth = (int) $referenceDefaultNext->month;
        $refDefaultYear = (int) $referenceDefaultNext->year;

        $selectedMonthYearLabel = Carbon::createFromDate($selectedYear, $selectedMonth, 1)->format('m/Y');
        $years = range($now->year - 5, $now->year + 5);

        return view('transactions.index', compact(
            'transactions',
            'monthTransactionsAll',
            'categories',
            'accounts',
            'regularAccounts',
            'cardAccounts',
            'byPaymentMethod',
            'byAccount',
            'fundingOld',
            'paymentFlowOld',
            'txFormMode',
            'txAccountsPayload',
            'refDefaultMonth',
            'refDefaultYear',
            'selectedMonth',
            'selectedYear',
            'selectedMonthYearLabel',
            'years'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'funding' => ['required', 'string', Rule::in(['account', 'credit_card'])],
            'category_id' => 'required|exists:categories,id',
            'account_id' => 'required|exists:accounts,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['nullable', 'string', 'max:100', Rule::in(PaymentMethods::forRegularAccounts())],
            'installments' => 'nullable|integer|min:1|max:12',
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
            'reference_month' => 'nullable|integer|min:1|max:12',
            'reference_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $category = Category::find($request->category_id);
        if ($category->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $account = Account::find($request->account_id);
        if (! $account || $account->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $funding = $request->input('funding');

        if ($funding === 'credit_card') {
            if (! $account->isCreditCard()) {
                return back()->withErrors([
                    'account_id' => 'Selecione um cartão de crédito cadastrado.',
                ])->withInput();
            }
            if ($request->filled('payment_method')) {
                return back()->withErrors([
                    'payment_method' => 'Em cartão de crédito não informe forma de pagamento separada; o cartão já identifica o meio.',
                ])->withInput();
            }
            $paymentMethod = null;
        } else {
            if ($account->isCreditCard()) {
                return back()->withErrors([
                    'account_id' => 'Para Pix, débito, dinheiro etc., escolha uma conta (não um cartão de crédito).',
                ])->withInput();
            }
            if ($account->getEffectivePaymentMethods() === []) {
                return back()->withErrors([
                    'account_id' => 'Esta conta não tem formas de pagamento habilitadas. Edite-a em Gerenciar contas.',
                ])->withInput();
            }
            if (! $request->filled('payment_method')) {
                return back()->withErrors([
                    'payment_method' => 'Selecione a forma de pagamento.',
                ])->withInput();
            }
            if (! $account->allowsPaymentMethod($request->payment_method)) {
                return back()->withErrors([
                    'payment_method' => 'Esta forma de pagamento não está habilitada para a conta selecionada.',
                ])->withInput();
            }
            $paymentMethod = $request->payment_method;
        }

        if ($category->type !== $request->type) {
            return back()->withErrors(['category_id' => 'A categoria selecionada não corresponde ao tipo de lançamento (Receita/Despesa).'])->withInput();
        }

        $isCredit = $funding === 'credit_card';
        $installments = $isCredit ? (int) $request->input('installments', 1) : 1;
        if ($isCredit && $installments < 1) {
            return back()->withErrors(['installments' => 'Informe a quantidade de parcelas.'])->withInput();
        }

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $amountCents = (int) round(((float) $amountNormalized) * 100);
        $baseCents = intdiv($amountCents, $installments);
        $remainderCents = $amountCents - ($baseCents * $installments);

        $startDate = Carbon::parse($request->date);
        $baseDescription = (string) $request->description;
        $installmentParentId = null;

        if ($isCredit) {
            if ($request->filled('reference_month') && $request->filled('reference_year')) {
                $referenceMonth = (int) $request->input('reference_month');
                $referenceYear = (int) $request->input('reference_year');
            } else {
                $refDefault = Carbon::now()->startOfMonth()->addMonth();
                $referenceMonth = (int) $refDefault->month;
                $referenceYear = (int) $refDefault->year;
            }
        } else {
            $referenceMonth = (int) ($request->input('reference_month') ?: $startDate->month);
            $referenceYear = (int) ($request->input('reference_year') ?: $startDate->year);
        }
        $referenceBase = Carbon::createFromDate($referenceYear, $referenceMonth, 1);

        DB::transaction(function () use (
            $installments,
            $baseCents,
            $remainderCents,
            $startDate,
            $referenceBase,
            $baseDescription,
            &$installmentParentId,
            $request,
            $paymentMethod
        ) {
            for ($i = 0; $i < $installments; $i++) {
                $parcelIndex = $i + 1;
                $cents = $baseCents + ($i === $installments - 1 ? $remainderCents : 0);
                $parcelAmount = number_format($cents / 100, 2, '.', '');

                $ref = $referenceBase->copy()->addMonths($i);
                $data = [
                    'couple_id' => Auth::user()->couple_id,
                    'user_id' => Auth::id(),
                    'category_id' => $request->category_id,
                    'account_id' => $request->account_id,
                    'description' => $installments > 1
                        ? $baseDescription.' (Parcela '.$parcelIndex.'/'.$installments.')'
                        : $baseDescription,
                    'amount' => $parcelAmount,
                    'payment_method' => $paymentMethod,
                    'type' => $request->type,
                    'date' => $startDate->copy()->addMonths($i)->toDateString(),
                    'reference_month' => (int) $ref->month,
                    'reference_year' => (int) $ref->year,
                ];

                if ($installments > 1) {
                    if ($i === 0) {
                        $data['installment_parent_id'] = null;
                    } else {
                        $data['installment_parent_id'] = $installmentParentId;
                    }
                }

                $created = Transaction::create($data);

                if ($installments > 1 && $i === 0) {
                    $installmentParentId = $created->id;
                }
            }
        });

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
