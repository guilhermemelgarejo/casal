<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();
        $couple = Auth::user()->couple;

        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('couple_id', $couple->id),
            ],
        ]);

        $selectedMonth = (int) ($validated['month'] ?? $now->month);
        $selectedYear = (int) ($validated['year'] ?? $now->year);
        $filterAccountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;

        $filteredRegularAccountBalance = null;
        if ($filterAccountId !== null) {
            $filteredAcc = Account::query()
                ->where('couple_id', $couple->id)
                ->whereKey($filterAccountId)
                ->first();
            if ($filteredAcc && ! $filteredAcc->isCreditCard()) {
                $filteredRegularAccountBalance = (float) $filteredAcc->balance;
            }
        }

        $transactions = $couple->transactions()
            ->with(['category', 'user', 'accountModel'])
            ->where('reference_month', $selectedMonth)
            ->where('reference_year', $selectedYear)
            ->when($filterAccountId !== null, fn ($q) => $q->where('account_id', $filterAccountId))
            ->latest()
            ->paginate(20);

        $installmentGroups = $this->installmentGroupsForTransactionPage($couple->id, $transactions->getCollection());
        $transactionDeleteMeta = [];
        foreach ($transactions as $txRow) {
            $transactionDeleteMeta[$txRow->id] = $this->transactionDeleteMeta($txRow, $installmentGroups);
        }

        $appendQuery = [
            'month' => $selectedMonth,
            'year' => $selectedYear,
        ];
        if ($filterAccountId !== null) {
            $appendQuery['account_id'] = $filterAccountId;
        }
        $transactions->appends($appendQuery);

        $categories = $couple->categories()
            ->excludingCreditCardInvoicePayment()
            ->orderBy('name')
            ->get();
        $accounts = $couple->accounts;

        $accountsSortedForFilter = $accounts->sortBy(function (Account $a) {
            return [
                $a->isCreditCard() ? 1 : 0,
                mb_strtolower($a->name),
            ];
        })->values();

        $regularAccounts = $accounts->where('kind', Account::KIND_REGULAR)->values();
        $cardAccounts = $accounts->where('kind', Account::KIND_CREDIT_CARD)->values();

        $monthTransactionsAll = $couple->transactions()
            ->excludingCreditCardInvoicePayments()
            ->with(['accountModel'])
            ->where('reference_month', $selectedMonth)
            ->where('reference_year', $selectedYear)
            ->when($filterAccountId !== null, fn ($q) => $q->where('account_id', $filterAccountId))
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
            'accountsSortedForFilter',
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
            'years',
            'filterAccountId',
            'filteredRegularAccountBalance',
            'transactionDeleteMeta'
        ));
    }

    /**
     * @param  Collection<int, Transaction>  $pageTransactions
     * @return Collection<int, Collection<int, Transaction>>
     */
    private function installmentGroupsForTransactionPage(int $coupleId, Collection $pageTransactions): Collection
    {
        if ($pageTransactions->isEmpty()) {
            return collect();
        }

        $roots = $pageTransactions->map(fn (Transaction $t) => $t->installmentRootId())->unique()->values()->all();

        $groupMembers = Transaction::query()
            ->where('couple_id', $coupleId)
            ->where(function ($q) use ($roots) {
                $q->whereIn('id', $roots)
                    ->orWhereIn('installment_parent_id', $roots);
            })
            ->get();

        // Chaves em string evitam falha de lookup int vs string em Collection::get().
        return $groupMembers->groupBy(fn (Transaction $t) => (string) $t->installmentRootId());
    }

    /**
     * @param  Collection<string, Collection<int, Transaction>>  $installmentGroups
     * @return array{paidInvoice: bool, peerCount: int, singleAllowed: bool}
     */
    private function transactionDeleteMeta(Transaction $t, Collection $installmentGroups): array
    {
        $rootId = $t->installmentRootId();
        $group = $installmentGroups->get((string) $rootId)
            ?? $installmentGroups->get($rootId);
        if ($group === null || $group->isEmpty()) {
            $group = collect([$t]);
        }

        $isParentWithSiblings = $t->installment_parent_id === null && $group->count() > 1;

        return [
            'paidInvoice' => $t->isInPaidCreditCardInvoiceCycle(),
            'peerCount' => $group->count(),
            'singleAllowed' => ! $isParentWithSiblings,
        ];
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

        if ($category->isCreditCardInvoicePayment()) {
            return back()->withErrors([
                'category_id' => 'Esta categoria é reservada para pagamentos de fatura de cartão. Use Faturas de cartão para registar a quitação.',
            ])->withInput();
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

    public function destroy(Request $request, Transaction $transaction)
    {
        if ($transaction->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        if ($transaction->isInPaidCreditCardInvoiceCycle()) {
            return back()->with(
                'error',
                'Este lançamento faz parte de um ciclo de fatura de cartão já marcado como pago. Desmarque o pagamento em Faturas de cartão se precisar alterar os lançamentos desse período.'
            );
        }

        $request->validate([
            'installment_scope' => ['nullable', 'string', Rule::in(['single', 'all'])],
        ]);

        $scope = $request->input('installment_scope', 'single');

        $rootId = $transaction->installmentRootId();
        $group = Transaction::query()
            ->where('couple_id', $transaction->couple_id)
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)
                    ->orWhere('installment_parent_id', $rootId);
            })
            ->get();

        if ($scope === 'all') {
            foreach ($group as $tx) {
                if ($tx->isInPaidCreditCardInvoiceCycle()) {
                    return back()->with(
                        'error',
                        'Não é possível excluir este conjunto: pelo menos uma parcela pertence a um ciclo de fatura já marcado como pago.'
                    );
                }
            }

            DB::transaction(function () use ($group, $rootId) {
                $children = $group->filter(fn (Transaction $x) => $x->installment_parent_id !== null)
                    ->sortByDesc('id');
                foreach ($children as $child) {
                    $child->delete();
                }
                $root = $group->firstWhere('id', $rootId);
                if ($root) {
                    $root->delete();
                }
            });

            $msg = $group->count() > 1
                ? 'Todas as parcelas deste lançamento foram excluídas.'
                : 'Lançamento excluído!';

            return back()->with('success', $msg);
        }

        if ((int) $transaction->id === $rootId && $group->count() > 1) {
            return back()->with(
                'error',
                'Não é possível excluir só a primeira parcela enquanto existirem as demais. Exclua as outras parcelas primeiro ou utilize a opção de excluir todas.'
            );
        }

        $transaction->delete();

        return back()->with('success', 'Lançamento excluído!');
    }
}
