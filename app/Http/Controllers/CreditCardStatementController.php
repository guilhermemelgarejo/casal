<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCardStatement;
use App\Models\Transaction;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreditCardStatementController extends Controller
{
    public function index(Request $request)
    {
        $couple = Auth::user()->couple;
        $coupleId = $couple->id;

        $cardAccounts = $couple->accounts()
            ->where('kind', Account::KIND_CREDIT_CARD)
            ->orderBy('name')
            ->get();

        $filterCardId = null;
        if ($request->filled('account_id')) {
            $wanted = (int) $request->input('account_id');
            if ($cardAccounts->contains(fn (Account $a) => (int) $a->id === $wanted)) {
                $filterCardId = $wanted;
            }
        }

        $cardIds = $filterCardId !== null ? [$filterCardId] : [];

        $invoiceCycles = collect();
        $invoiceCycleLinesByKey = [];
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

            $linesByKey = collect();
            if ($periodRows->isNotEmpty()) {
                $linesQuery = Transaction::query()
                    ->where('couple_id', $coupleId)
                    ->where('type', 'expense')
                    ->whereIn('account_id', $cardIds)
                    ->where(function ($q) use ($periodRows) {
                        foreach ($periodRows as $row) {
                            $q->orWhere(function ($qq) use ($row) {
                                $qq->where('account_id', (int) $row->account_id)
                                    ->where('reference_month', (int) $row->reference_month)
                                    ->where('reference_year', (int) $row->reference_year);
                            });
                        }
                    });

                $linesByKey = $linesQuery
                    ->orderBy('date')
                    ->orderBy('id')
                    ->get()
                    ->groupBy(fn (Transaction $t) => $this->cycleKey((int) $t->account_id, (int) $t->reference_year, (int) $t->reference_month))
                    ->map(fn ($items) => $items->map(fn (Transaction $t) => [
                        'date' => $t->date->format('d/m/Y'),
                        'description' => $t->description,
                        'parcel_label' => self::parcelLabelFromDescription((string) $t->description),
                        'ref_label' => sprintf('%02d/%d', (int) $t->reference_month, (int) $t->reference_year),
                        'amount' => (float) $t->amount,
                        'amount_str' => number_format((float) $t->amount, 2, ',', '.'),
                        'transactions_url' => route('transactions.index', [
                            'month' => (int) $t->date->month,
                            'year' => (int) $t->date->year,
                            'account_id' => (int) $t->account_id,
                        ]),
                    ])->values()->all());

                $invoiceCycleLinesByKey = $linesByKey->all();
            }

            $metaByKey = CreditCardStatement::query()
                ->where('couple_id', $coupleId)
                ->when($filterCardId !== null, fn ($q) => $q->where('account_id', $filterCardId))
                ->with(['paymentTransactions.accountModel'])
                ->get()
                ->keyBy(fn (CreditCardStatement $s) => $this->cycleKey($s->account_id, $s->reference_year, $s->reference_month));

            $accountsById = $cardAccounts->keyBy('id');

            $invoiceCycles = $periodRows->map(function ($row) use ($metaByKey, $accountsById, $linesByKey) {
                $key = $this->cycleKey((int) $row->account_id, (int) $row->reference_year, (int) $row->reference_month);
                $meta = $metaByKey->get($key);
                $liveTotal = (float) $row->spent_total;

                return (object) [
                    'cycle_key' => $key,
                    'account' => $accountsById[$row->account_id],
                    'reference_month' => (int) $row->reference_month,
                    'reference_year' => (int) $row->reference_year,
                    'spent_total' => $meta !== null ? (float) $meta->spent_total : $liveTotal,
                    'meta' => $meta,
                    'cycle_lines' => $linesByKey->get($key, []),
                ];
            });
        }

        $regularAccounts = $couple->accounts()
            ->where('kind', Account::KIND_REGULAR)
            ->orderBy('name')
            ->get();

        $cardPickerSummaries = [];
        foreach ($cardAccounts as $a) {
            $cardPickerSummaries[$a->id] = $this->cardPickerOpenCycleSummary($a, $coupleId);
        }

        $pastOpenStatementCycles = $cardAccounts->isEmpty()
            ? collect()
            : $this->pastOpenCreditCardStatementRows($coupleId, $cardAccounts);

        $pastOpenStatementAccountIds = $pastOpenStatementCycles->map(fn (array $r) => $r['account']->id)->unique()->values();

        return view('credit-card-statements.index', compact(
            'cardAccounts',
            'cardPickerSummaries',
            'pastOpenStatementAccountIds',
            'invoiceCycles',
            'invoiceCycleLinesByKey',
            'regularAccounts',
            'filterCardId'
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
                'account_id' => $validated['account_id'],
                'description' => $description,
                'amount' => $amountStr,
                'payment_method' => $validated['payment_method'],
                'type' => 'expense',
                'date' => $paidDate->toDateString(),
                'reference_month' => $refMonth,
                'reference_year' => $refYear,
            ]);

            $tx->syncCategorySplits([
                ['category_id' => $invoiceCategory->id, 'amount' => $amountStr],
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

    private static function parcelLabelFromDescription(string $description): string
    {
        if (preg_match('/\(Parcela\s+(\d+)\/(\d+)\)\s*$/u', $description, $m)) {
            return $m[1].'/'.$m[2];
        }

        return 'Única';
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

    /**
     * Primeira fatura em aberto entre o mês de referência atual e o seguinte (calendário app), para o resumo nos cartões da listagem.
     *
     * @return array{
     *     reference_month: int,
     *     reference_year: int,
     *     ref_label: string,
     *     spent_total: float,
     *     spent_total_str: string,
     *     remaining: float,
     *     remaining_str: string,
     *     partial: bool,
     *     due_label: string|null,
     *     due_is_suggestion: bool
     * }|null
     */
    private function cardPickerOpenCycleSummary(Account $account, int $coupleId): ?array
    {
        $now = Carbon::now();
        $nextMonth = $now->copy()->addMonth();

        $candidates = [
            [(int) $now->month, (int) $now->year],
            [(int) $nextMonth->month, (int) $nextMonth->year],
        ];

        foreach ($candidates as [$refMonth, $refYear]) {
            if (! $this->cycleHasCardExpense($account, $refMonth, $refYear)) {
                continue;
            }

            $meta = $this->findMeta($account, $refMonth, $refYear);
            $spentTotal = $meta !== null
                ? (float) $meta->spent_total
                : (float) CreditCardStatement::sumCardExpensesForCycle($coupleId, $account->id, $refMonth, $refYear);

            if ($spentTotal < 0.005) {
                continue;
            }

            $isPaid = $meta !== null && $meta->isPaid();
            if ($isPaid) {
                continue;
            }

            $remaining = $meta !== null ? $meta->remainingToPay() : $spentTotal;
            $partial = $meta !== null && $meta->hasPartialPayments();

            $virtualDue = $account->defaultStatementDueDate($refMonth, $refYear);
            if ($meta?->due_date) {
                $dueForDisplay = $meta->due_date;
                $dueIsSuggestion = false;
            } elseif ($virtualDue) {
                $dueForDisplay = $virtualDue;
                $dueIsSuggestion = true;
            } else {
                $dueForDisplay = null;
                $dueIsSuggestion = false;
            }

            return [
                'reference_month' => $refMonth,
                'reference_year' => $refYear,
                'ref_label' => sprintf('%02d/%d', $refMonth, $refYear),
                'spent_total' => $spentTotal,
                'spent_total_str' => number_format($spentTotal, 2, ',', '.'),
                'remaining' => $remaining,
                'remaining_str' => number_format($remaining, 2, ',', '.'),
                'partial' => $partial,
                'due_label' => $dueForDisplay?->format('d/m/Y'),
                'due_is_suggestion' => $dueIsSuggestion,
            ];
        }

        return null;
    }

    /**
     * Ciclos de fatura (mês de referência) anteriores ao mês civil atual com despesa no cartão e ainda não quitados.
     *
     * @return Collection<int, array{
     *     account: Account,
     *     ref_label: string,
     *     reference_month: int,
     *     reference_year: int,
     *     spent_total_str: string,
     *     remaining_str: string,
     *     has_partial: bool,
     *     statements_url: string
     * }>
     */
    private function pastOpenCreditCardStatementRows(int $coupleId, Collection $cardAccounts): Collection
    {
        $now = Carbon::now();
        $currentOrdinal = $now->year * 12 + $now->month;

        $cardIds = $cardAccounts->pluck('id')->all();

        $candidates = Transaction::query()
            ->where('couple_id', $coupleId)
            ->where('type', 'expense')
            ->whereIn('account_id', $cardIds)
            ->whereRaw('(reference_year * 12 + reference_month) < ?', [$currentOrdinal])
            ->groupBy('account_id', 'reference_month', 'reference_year')
            ->selectRaw('account_id, reference_month, reference_year')
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $metaList = CreditCardStatement::query()
            ->where('couple_id', $coupleId)
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $c) {
                    $q->orWhere(function ($qq) use ($c) {
                        $qq->where('account_id', (int) $c->account_id)
                            ->where('reference_month', (int) $c->reference_month)
                            ->where('reference_year', (int) $c->reference_year);
                    });
                }
            })
            ->with('paymentTransactions')
            ->get()
            ->keyBy(fn (CreditCardStatement $s) => $this->cycleKey($s->account_id, $s->reference_year, $s->reference_month));

        $accountsById = $cardAccounts->keyBy('id');

        $rows = [];
        foreach ($candidates as $c) {
            $accId = (int) $c->account_id;
            $refMonth = (int) $c->reference_month;
            $refYear = (int) $c->reference_year;

            $account = $accountsById->get($accId);
            if ($account === null) {
                continue;
            }

            $key = $this->cycleKey($accId, $refYear, $refMonth);
            $meta = $metaList->get($key);

            $spent = $meta !== null
                ? (float) $meta->spent_total
                : (float) CreditCardStatement::sumCardExpensesForCycle($coupleId, $accId, $refMonth, $refYear);

            if ($spent < 0.005) {
                continue;
            }

            if ($meta !== null && $meta->isPaid()) {
                continue;
            }

            $remaining = $meta !== null ? $meta->remainingToPay() : $spent;
            $refLabel = sprintf('%02d/%d', $refMonth, $refYear);

            $rows[] = [
                'account' => $account,
                'ref_label' => $refLabel,
                'reference_month' => $refMonth,
                'reference_year' => $refYear,
                'spent_total_str' => number_format($spent, 2, ',', '.'),
                'remaining_str' => number_format($remaining, 2, ',', '.'),
                'has_partial' => $meta !== null && $meta->hasPartialPayments(),
                'statements_url' => route('credit-card-statements.index', ['account_id' => $accId])
                    .'#statement-cycle-'.$accId.'-'.$refYear.'-'.$refMonth,
            ];
        }

        usort($rows, fn (array $a, array $b): int => ($a['reference_year'] * 12 + $a['reference_month'])
            <=> ($b['reference_year'] * 12 + $b['reference_month']));

        return collect($rows);
    }

    private function firstOrCreateMeta(Account $account, int $referenceMonth, int $referenceYear): CreditCardStatement
    {
        return CreditCardStatement::materializeForCycle($account, $referenceMonth, $referenceYear);
    }
}
