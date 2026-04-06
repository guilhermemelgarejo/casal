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
                ->with(['paymentTransactions.accountModel'])
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

        return view('credit-card-statements.index', compact(
            'cardAccounts',
            'invoiceCycles',
            'regularAccounts'
        ));
    }

    public function update(Request $request, Account $account, int $referenceYear, int $referenceMonth)
    {
        $this->authorizeCreditCardAccount($account);

        if (! $this->cycleHasCardExpense($account, $referenceMonth, $referenceYear)) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'due_date' => ['nullable', 'date'],
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

        $meta->update([
            'due_date' => $validated['due_date'] ?? null,
        ]);

        return back()->with('success', 'Fatura atualizada.');
    }

    public function attachPayment(Request $request, Account $account, int $referenceYear, int $referenceMonth)
    {
        $this->authorizeCreditCardAccount($account);

        if (! $this->cycleHasCardExpense($account, $referenceMonth, $referenceYear)) {
            abort(404);
        }

        $meta = $this->firstOrCreateMeta($account, $referenceMonth, $referenceYear);

        $meta->refresh();

        if ($meta->isFullyPaidByPayments()) {
            return back()->withErrors([
                'payment' => 'A fatura já está quitada pelos lançamentos vinculados.',
            ]);
        }

        if ($request->filled('amount')) {
            $request->merge([
                'amount' => str_replace(',', '.', (string) $request->input('amount')),
            ]);
        }

        $paymentFlash = $this->openStatementPaymentFlash($account, $referenceYear, $referenceMonth);

        $validator = Validator::make($request->all(), [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'payment_method' => ['required', 'string', 'max:100', Rule::in(PaymentMethods::forRegularAccounts())],
            'paid_date' => ['required', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('open_statement_payment', $paymentFlash);
        }

        $validated = $validator->validated();

        $coupleId = Auth::user()->couple_id;
        $cycleTotal = $this->cycleSpentTotal($account, $referenceMonth, $referenceYear);
        $cycleTotalFloat = (float) $cycleTotal;
        $paidSoFar = $meta->paymentsTotal();

        if (empty($validated['account_id']) || empty($validated['paid_date'])) {
            return back()->withErrors([
                'account_id' => 'Conta e data de pagamento são obrigatórios para gerar o lançamento.',
            ])->withInput()->with('open_statement_payment', $paymentFlash);
        }

        $payAccount = Account::find($validated['account_id']);
        if (! $payAccount || $payAccount->couple_id !== $coupleId || $payAccount->isCreditCard()) {
            abort(403);
        }

        if (! $payAccount->allowsPaymentMethod($validated['payment_method'])) {
            return back()->withErrors(['payment_method' => 'Esta forma não está habilitada para a conta selecionada.'])
                ->withInput()
                ->with('open_statement_payment', $paymentFlash);
        }

        $invoiceCategory = Category::creditCardInvoicePaymentForCouple($coupleId);
        if (! $invoiceCategory) {
            return back()->withErrors([
                'account_id' => 'Categoria de quitação de fatura não encontrada para este casal.',
            ])->withInput()->with('open_statement_payment', $paymentFlash);
        }

        $defaultAmount = $paidSoFar > 0.005
            ? max(0.01, round($cycleTotalFloat - $paidSoFar, 2))
            : $cycleTotalFloat;
        $amountNormalized = $validated['amount'] ?? $defaultAmount;
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
            $meta,
            $invoiceCategory
        ) {
            $tx = Transaction::create([
                'couple_id' => $coupleId,
                'user_id' => Auth::id(),
                'category_id' => $invoiceCategory->id,
                'account_id' => $validated['account_id'],
                'description' => $description,
                'amount' => $amountStr,
                'payment_method' => $validated['payment_method'],
                'type' => 'expense',
                'date' => $paidDate->toDateString(),
                'reference_month' => $refMonth,
                'reference_year' => $refYear,
            ]);

            $meta->paymentTransactions()->attach($tx->id);
            $meta->refresh();
            $meta->syncPaidMetadata();
        });

        $meta->refresh();

        return back()->with(
            'success',
            $meta->isFullyPaidByPayments()
                ? 'Lançamento criado e fatura quitada.'
                : 'Lançamento criado. Ainda há valor pendente na fatura.'
        );
    }

    private function authorizeCreditCardAccount(Account $account): void
    {
        if ($account->couple_id !== Auth::user()->couple_id || ! $account->isCreditCard()) {
            abort(403);
        }
    }

    /**
     * @return array{account_id: int, reference_year: int, reference_month: int}
     */
    private function openStatementPaymentFlash(Account $account, int $referenceYear, int $referenceMonth): array
    {
        return [
            'account_id' => $account->id,
            'reference_year' => $referenceYear,
            'reference_month' => $referenceMonth,
        ];
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
