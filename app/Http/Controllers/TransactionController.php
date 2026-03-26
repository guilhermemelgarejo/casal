<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\Account;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

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
            ->whereMonth('date', $selectedMonth)
            ->whereYear('date', $selectedYear)
            ->latest()
            ->paginate(20);
        $transactions->appends(['month' => $selectedMonth, 'year' => $selectedYear]);

        $categories = $couple->categories;
        $accounts = $couple->accounts;

        // Resumos do mês selecionado (considera o mês inteiro, não apenas a página do paginate)
        $monthTransactionsAll = $couple->transactions()
            ->whereMonth('date', $selectedMonth)
            ->whereYear('date', $selectedYear)
            ->get();

        $expenseMonthTransactions = $monthTransactionsAll->where('type', 'expense');

        $byPaymentMethod = $expenseMonthTransactions->groupBy('payment_method')
            ->map(fn($items) => $items->sum('amount'))
            ->forget('');

        // Agrupamento por conta cadastrada (usando o nome da conta no model)
        $byAccount = $expenseMonthTransactions->whereNotNull('account_id')->groupBy('account_id')
            ->mapWithKeys(function ($items, $accountId) {
                $account = Account::find($accountId);
                $accountName = $account?->name ?? 'Conta não encontrada';
                return [$accountName => $items->sum('amount')];
            });

        $availablePaymentMethods = [];
        $autoPaymentMethod = null;
        if (old('account_id')) {
            $selectedAccount = $accounts->firstWhere('id', (int) old('account_id'));
            if ($selectedAccount) {
                $availablePaymentMethods = $selectedAccount->getEffectivePaymentMethods();
                if (count($availablePaymentMethods) === 1) {
                    $autoPaymentMethod = $availablePaymentMethods[0];
                }
            }
        }

        $selectedMonthYearLabel = Carbon::createFromDate($selectedYear, $selectedMonth, 1)->format('m/Y');
        $years = range($now->year - 5, $now->year + 5);

        return view('transactions.index', compact(
            'transactions',
            'monthTransactionsAll',
            'categories',
            'accounts',
            'byPaymentMethod',
            'byAccount',
            'availablePaymentMethods',
            'autoPaymentMethod',
            'selectedMonth',
            'selectedYear',
            'selectedMonthYearLabel',
            'years'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'account_id' => 'required|exists:accounts,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', 'string', 'max:100', Rule::in(PaymentMethods::all())],
            'installments' => 'nullable|integer|min:1|max:12',
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
        ]);

        $category = Category::find($request->category_id);
        if ($category->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $account = Account::find($request->account_id);
        if (! $account || $account->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        if ($account->getEffectivePaymentMethods() === []) {
            return back()->withErrors([
                'account_id' => 'Esta conta não tem formas de pagamento habilitadas. Edite-a em Gerenciar contas.',
            ])->withInput();
        }

        if (! $account->allowsPaymentMethod($request->payment_method)) {
            return back()->withErrors([
                'payment_method' => 'Esta forma de pagamento não está habilitada para a conta selecionada.',
            ])->withInput();
        }

        if ($category->type !== $request->type) {
            return back()->withErrors(['category_id' => 'A categoria selecionada não corresponde ao tipo de lançamento (Receita/Despesa).'])->withInput();
        }

        $isCredit = $request->payment_method === 'Cartão de Crédito';
        $installments = $isCredit ? (int) $request->input('installments', 1) : 1;
        if ($isCredit && $installments < 1) {
            return back()->withErrors(['installments' => 'Informe a quantidade de parcelas.'])->withInput();
        }

        // Converte valor para centavos para dividir sem erros de ponto flutuante.
        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $amountCents = (int) round(((float) $amountNormalized) * 100);
        $baseCents = intdiv($amountCents, $installments);
        $remainderCents = $amountCents - ($baseCents * $installments);

        $startDate = Carbon::parse($request->date);
        $baseDescription = (string) $request->description;
        $installmentParentId = null;

        DB::transaction(function () use (
            $installments,
            $baseCents,
            $remainderCents,
            $startDate,
            $baseDescription,
            &$installmentParentId,
            $request
        ) {
            for ($i = 0; $i < $installments; $i++) {
                $parcelIndex = $i + 1;
                $cents = $baseCents + ($i === $installments - 1 ? $remainderCents : 0);
                // Envia como string com 2 casas para evitar erros de ponto flutuante no decimal do banco.
                $parcelAmount = number_format($cents / 100, 2, '.', '');

                $data = [
                    'couple_id' => Auth::user()->couple_id,
                    'user_id' => Auth::id(),
                    'category_id' => $request->category_id,
                    'account_id' => $request->account_id,
                    'description' => $installments > 1
                        ? $baseDescription . ' (Parcela ' . $parcelIndex . '/' . $installments . ')'
                        : $baseDescription,
                    'amount' => $parcelAmount,
                    'payment_method' => $request->payment_method,
                    'type' => $request->type,
                    'date' => $startDate->copy()->addMonths($i)->toDateString(),
                ];

                // Vínculo (autorelacionamento) entre as parcelas:
                // - a primeira parcela fica como "pai"
                // - as demais apontam para ela
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
