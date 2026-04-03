<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCardStatement;
use App\Models\Transaction;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreditCardStatementController extends Controller
{
    public function index()
    {
        $couple = Auth::user()->couple;
        $coupleId = $couple->id;

        $cardAccounts = $couple->accounts()
            ->where('kind', Account::KIND_CREDIT_CARD)
            ->orderBy('name')
            ->get();

        $cardIds = $cardAccounts->pluck('id')->all();

        $invoiceCycles = collect();
        if ($cardIds !== []) {
            $periodRows = Transaction::query()
                ->where('couple_id', $coupleId)
                ->where('type', 'expense')
                ->whereIn('account_id', $cardIds)
                ->groupBy('account_id', 'reference_month', 'reference_year')
                ->selectRaw('account_id, reference_month, reference_year, SUM(amount) as spent_total')
                ->orderByDesc('reference_year')
                ->orderByDesc('reference_month')
                ->get();

            $metaByKey = CreditCardStatement::query()
                ->where('couple_id', $coupleId)
                ->with(['paymentTransaction.accountModel'])
                ->get()
                ->keyBy(fn (CreditCardStatement $s) => $this->cycleKey($s->account_id, $s->reference_year, $s->reference_month));

            $accountsById = $cardAccounts->keyBy('id');

            $invoiceCycles = $periodRows->map(function ($row) use ($metaByKey, $accountsById) {
                $key = $this->cycleKey($row->account_id, $row->reference_year, $row->reference_month);
                $meta = $metaByKey->get($key);
                $liveTotal = (float) $row->spent_total;

                return (object) [
                    'account' => $accountsById[$row->account_id],
                    'reference_month' => (int) $row->reference_month,
                    'reference_year' => (int) $row->reference_year,
                    'spent_total' => $meta !== null ? (float) $meta->spent_total : $liveTotal,
                    'meta' => $meta,
                ];
            });
        }

        $regularAccounts = $couple->accounts()
            ->where('kind', Account::KIND_REGULAR)
            ->orderBy('name')
            ->get();

        $expenseCategories = $couple->categories()
            ->where('type', 'expense')
            ->orderBy('name')
            ->get();

        $usedPaymentTxIds = CreditCardStatement::query()
            ->where('couple_id', $coupleId)
            ->whereNotNull('payment_transaction_id')
            ->pluck('payment_transaction_id')
            ->all();

        $linkableTransactions = Transaction::query()
            ->where('couple_id', $coupleId)
            ->where('type', 'expense')
            ->whereHas('accountModel', fn ($q) => $q->where('kind', Account::KIND_REGULAR))
            ->with(['accountModel', 'category'])
            ->when(count($usedPaymentTxIds) > 0, fn ($q) => $q->whereNotIn('id', $usedPaymentTxIds))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        return view('credit-card-statements.index', compact(
            'cardAccounts',
            'invoiceCycles',
            'regularAccounts',
            'expenseCategories',
            'linkableTransactions'
        ));
    }

    public function update(Request $request, Account $account, int $referenceYear, int $referenceMonth)
    {
        $this->authorizeCreditCardAccount($account);

        if (! $this->cycleHasCardExpense($account, $referenceMonth, $referenceYear)) {
            abort(404);
        }

        $request->merge([
            'paid_at' => $request->filled('paid_at') ? $request->input('paid_at') : null,
        ]);

        $validator = Validator::make($request->all(), [
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('open_statement_edit', [
                    'account_id' => $account->id,
                    'reference_year' => $referenceYear,
                    'reference_month' => $referenceMonth,
                ]);
        }

        $validated = $validator->validated();

        $meta = $this->firstOrCreateMeta($account, $referenceMonth, $referenceYear);

        $paidAt = $validated['paid_at'] ?? null;
        $updates = [
            'due_date' => $validated['due_date'] ?? null,
            'paid_at' => $paidAt,
        ];

        if ($paidAt === null) {
            $updates['payment_transaction_id'] = null;
        }

        $meta->update($updates);

        return back()->with('success', 'Fatura atualizada.');
    }

    public function attachPayment(Request $request, Account $account, int $referenceYear, int $referenceMonth)
    {
        $this->authorizeCreditCardAccount($account);

        if (! $this->cycleHasCardExpense($account, $referenceMonth, $referenceYear)) {
            abort(404);
        }

        $meta = $this->firstOrCreateMeta($account, $referenceMonth, $referenceYear);

        if ($meta->payment_transaction_id !== null) {
            return back()->withErrors([
                'mode' => 'Já existe um lançamento vinculado. Remova o vínculo antes de registrar outro pagamento.',
            ]);
        }

        if ($request->filled('amount')) {
            $request->merge([
                'amount' => str_replace(',', '.', (string) $request->input('amount')),
            ]);
        }

        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in(['create', 'link'])],
            'existing_transaction_id' => ['required_if:mode,link', 'nullable', 'integer', 'exists:transactions,id'],
            'account_id' => ['required_if:mode,create', 'nullable', 'integer', 'exists:accounts,id'],
            'payment_method' => ['required_if:mode,create', 'nullable', 'string', 'max:100', Rule::in(PaymentMethods::forRegularAccounts())],
            'category_id' => ['required_if:mode,create', 'nullable', 'integer', 'exists:categories,id'],
            'paid_date' => ['required_if:mode,create', 'nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $coupleId = Auth::user()->couple_id;
        $cycleTotal = $this->cycleSpentTotal($account, $referenceMonth, $referenceYear);

        if ($validated['mode'] === 'link') {
            if (empty($validated['existing_transaction_id'])) {
                return back()->withErrors(['existing_transaction_id' => 'Selecione um lançamento.'])->withInput();
            }

            $tx = Transaction::find($validated['existing_transaction_id']);
            if (! $tx || $tx->couple_id !== $coupleId || $tx->type !== 'expense') {
                abort(403);
            }

            $txAccount = $tx->accountModel;
            if (! $txAccount || $txAccount->couple_id !== $coupleId || $txAccount->isCreditCard()) {
                return back()->withErrors(['existing_transaction_id' => 'O lançamento deve ser uma despesa em conta corrente (não cartão).'])->withInput();
            }

            $already = CreditCardStatement::query()
                ->where('payment_transaction_id', $tx->id)
                ->where('id', '!=', $meta->id)
                ->exists();

            if ($already) {
                return back()->withErrors(['existing_transaction_id' => 'Este lançamento já está vinculado a outra fatura.'])->withInput();
            }

            $meta->update([
                'payment_transaction_id' => $tx->id,
                'paid_at' => $tx->date->toDateString(),
            ]);

            return back()->with('success', 'Pagamento vinculado à fatura.');
        }

        if (empty($validated['account_id']) || empty($validated['category_id']) || empty($validated['paid_date'])) {
            return back()->withErrors([
                'account_id' => 'Conta, categoria e data de pagamento são obrigatórios para gerar o lançamento.',
            ])->withInput();
        }

        $payAccount = Account::find($validated['account_id']);
        if (! $payAccount || $payAccount->couple_id !== $coupleId || $payAccount->isCreditCard()) {
            abort(403);
        }

        if (! $request->filled('payment_method')) {
            return back()->withErrors(['payment_method' => 'Selecione a forma de pagamento.'])->withInput();
        }

        if (! $payAccount->allowsPaymentMethod($request->payment_method)) {
            return back()->withErrors(['payment_method' => 'Esta forma não está habilitada para a conta selecionada.'])->withInput();
        }

        $category = Category::find($validated['category_id']);
        if (! $category || $category->couple_id !== $coupleId || $category->type !== 'expense') {
            abort(403);
        }

        $amountNormalized = $validated['amount'] ?? $cycleTotal;
        $amountStr = number_format((float) str_replace(',', '.', (string) $amountNormalized), 2, '.', '');

        $paidDate = Carbon::parse($validated['paid_date']);
        $refMonth = (int) $paidDate->month;
        $refYear = (int) $paidDate->year;

        $cardName = $account->name;
        $refLabel = sprintf('%02d/%d', $referenceMonth, $referenceYear);
        $description = 'Pagamento fatura '.$cardName.' ('.$refLabel.')';

        DB::transaction(function () use (
            $coupleId,
            $validated,
            $amountStr,
            $paidDate,
            $refMonth,
            $refYear,
            $description,
            $meta
        ) {
            $tx = Transaction::create([
                'couple_id' => $coupleId,
                'user_id' => Auth::id(),
                'category_id' => $validated['category_id'],
                'account_id' => $validated['account_id'],
                'description' => $description,
                'amount' => $amountStr,
                'payment_method' => $validated['payment_method'],
                'type' => 'expense',
                'date' => $paidDate->toDateString(),
                'reference_month' => $refMonth,
                'reference_year' => $refYear,
            ]);

            $meta->update([
                'payment_transaction_id' => $tx->id,
                'paid_at' => $paidDate->toDateString(),
            ]);
        });

        return back()->with('success', 'Lançamento criado e fatura marcada como paga.');
    }

    public function detachPayment(Account $account, int $referenceYear, int $referenceMonth)
    {
        $this->authorizeCreditCardAccount($account);

        $meta = $this->findMeta($account, $referenceMonth, $referenceYear);
        if (! $meta) {
            abort(404);
        }

        $meta->update([
            'payment_transaction_id' => null,
            'paid_at' => null,
        ]);

        return back()->with('success', 'Vínculo de pagamento removido. O lançamento permanece em Lançamentos (exclua lá se quiser).');
    }

    public function destroy(Account $account, int $referenceYear, int $referenceMonth)
    {
        $this->authorizeCreditCardAccount($account);

        $meta = $this->findMeta($account, $referenceMonth, $referenceYear);
        if (! $meta) {
            return back()->with('success', 'Não havia dados extras para este ciclo.');
        }

        $meta->delete();

        return back()->with('success', 'Vencimento e dados de pagamento deste ciclo foram removidos. O total materializado da fatura foi removido; ao voltar a haver lançamentos no ciclo, o valor é recalculado.');
    }

    private function authorizeCreditCardAccount(Account $account): void
    {
        if ($account->couple_id !== Auth::user()->couple_id || ! $account->isCreditCard()) {
            abort(403);
        }
    }

    private function cycleKey(int $accountId, int $referenceYear, int $referenceMonth): string
    {
        return $accountId.'-'.$referenceYear.'-'.$referenceMonth;
    }

    private function cycleHasCardExpense(Account $account, int $referenceMonth, int $referenceYear): bool
    {
        return Transaction::query()
            ->where('couple_id', $account->couple_id)
            ->where('account_id', $account->id)
            ->where('reference_month', $referenceMonth)
            ->where('reference_year', $referenceYear)
            ->where('type', 'expense')
            ->exists();
    }

    private function cycleSpentTotal(Account $account, int $referenceMonth, int $referenceYear): string
    {
        $meta = $this->findMeta($account, $referenceMonth, $referenceYear);
        if ($meta !== null) {
            return number_format((float) $meta->spent_total, 2, '.', '');
        }

        return CreditCardStatement::sumCardExpensesForCycle(
            $account->couple_id,
            $account->id,
            $referenceMonth,
            $referenceYear
        );
    }

    private function findMeta(Account $account, int $referenceMonth, int $referenceYear): ?CreditCardStatement
    {
        return CreditCardStatement::query()
            ->where('couple_id', $account->couple_id)
            ->where('account_id', $account->id)
            ->where('reference_month', $referenceMonth)
            ->where('reference_year', $referenceYear)
            ->first();
    }

    private function firstOrCreateMeta(Account $account, int $referenceMonth, int $referenceYear): CreditCardStatement
    {
        return CreditCardStatement::materializeForCycle($account, $referenceMonth, $referenceYear);
    }
}
