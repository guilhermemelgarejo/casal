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
    private static function normalizeMoneyBrToNumeric(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }

        // Aceita "5653,37", "5.653,37", "5653.37", "5,653.37".
        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $commaIsDecimal = $lastComma > $lastDot;
            if ($commaIsDecimal) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($lastComma !== false) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return $s;
    }

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
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy(fn (Transaction $t) => $this->cycleKey((int) $t->account_id, (int) $t->reference_year, (int) $t->reference_month))
                    ->map(fn ($items) => $items->map(fn (Transaction $t) => [
                        'date' => $t->date->format('d/m/Y'),
                        'description' => $t->description,
                        'parcel_label' => self::parcelLabelFromDescription((string) $t->description),
                        'ref_label' => sprintf('%02d/%d', (int) $t->reference_month, (int) $t->reference_year),
                        'amount' => (float) $t->amount,
                        'amount_str' => number_format((float) $t->amount, 2, ',', '.'),
                        'amount_abs_str' => number_format(abs((float) $t->amount), 2, ',', '.'),
                        'is_credit' => (float) $t->amount < -0.004,
                        'transactions_url' => route('dashboard', [
                            'period' => sprintf('%04d-%02d', (int) $t->date->year, (int) $t->date->month),
                            'account_id' => (int) $t->account_id,
                            'focus_transaction' => (int) $t->installmentRootId(),
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

            // Inclui faturas avulsas (meta existente mesmo sem itens lançados).
            $existingKeys = $invoiceCycles->map(fn ($c) => (string) $c->cycle_key)->flip();
            $avulsas = $metaByKey
                ->filter(fn (CreditCardStatement $m) => $m->is_avulsa)
                ->reject(fn (CreditCardStatement $m) => $existingKeys->has($this->cycleKey($m->account_id, $m->reference_year, $m->reference_month)))
                ->map(function (CreditCardStatement $meta) use ($accountsById) {
                    $key = $this->cycleKey($meta->account_id, $meta->reference_year, $meta->reference_month);

                    return (object) [
                        'cycle_key' => $key,
                        'account' => $accountsById[$meta->account_id],
                        'reference_month' => (int) $meta->reference_month,
                        'reference_year' => (int) $meta->reference_year,
                        'spent_total' => (float) $meta->spent_total,
                        'meta' => $meta,
                        'cycle_lines' => [],
                    ];
                });

            if ($avulsas->isNotEmpty()) {
                $invoiceCycles = $invoiceCycles
                    ->concat($avulsas)
                    ->sortByDesc(fn ($c) => sprintf('%04d-%02d', (int) $c->reference_year, (int) $c->reference_month))
                    ->values();
            }
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

    public function storeAvulsa(Request $request, Account $account)
    {
        $this->authorizeCreditCardAccount($account);

        // Identifica o formulário para reabrir o modal no retorno com erro.
        $request->merge(['_form' => (string) $request->input('_form', 'cc-statement-avulsa')]);

        if ($request->filled('spent_total')) {
            $request->merge([
                'spent_total' => self::normalizeMoneyBrToNumeric((string) $request->input('spent_total')),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'reference_month' => ['required', 'integer', 'min:1', 'max:12'],
            'reference_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'spent_total' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Se já houver itens lançados nesse ciclo, não permitir fatura avulsa.
        if ($this->cycleHasCardExpense($account, (int) $validated['reference_month'], (int) $validated['reference_year'])) {
            return back()->withErrors([
                'reference_month' => 'Este ciclo já tem itens lançados no cartão. Use a fatura normal do ciclo.',
            ])->withInput();
        }

        $coupleId = (int) Auth::user()->couple_id;
        $refMonth = (int) $validated['reference_month'];
        $refYear = (int) $validated['reference_year'];

        $existing = CreditCardStatement::query()
            ->where('couple_id', $coupleId)
            ->where('account_id', (int) $account->id)
            ->where('reference_month', $refMonth)
            ->where('reference_year', $refYear)
            ->first();

        $spent = number_format((float) $validated['spent_total'], 2, '.', '');

        // Se já existe um meta “vazio” (sem itens e sem pagamentos), em vez de bloquear,
        // promovemos para fatura avulsa e atualizamos o total/vencimento.
        if ($existing) {
            if ($existing->paymentTransactions()->exists()) {
                return back()->withErrors([
                    'reference_month' => 'Já existe uma fatura neste ciclo e ela possui pagamentos vinculados.',
                ])->withInput();
            }

            $existing->update([
                'is_avulsa' => true,
                'spent_total' => $spent,
                'due_date' => $validated['due_date'] ?? null,
                'paid_at' => null,
            ]);

            return back()->with('success', 'Fatura avulsa atualizada.');
        }

        try {
            CreditCardStatement::create([
                'couple_id' => $coupleId,
                'account_id' => (int) $account->id,
                'reference_month' => $refMonth,
                'reference_year' => $refYear,
                'spent_total' => $spent,
                'due_date' => $validated['due_date'] ?? null,
                'paid_at' => null,
                'is_avulsa' => true,
            ]);
        } catch (\Throwable) {
            // Em caso de falha ao gravar: só afirmamos “já existe” se realmente existir registro.
            $existsNow = CreditCardStatement::query()
                ->where('account_id', (int) $account->id)
                ->where('reference_month', $refMonth)
                ->where('reference_year', $refYear)
                ->exists();

            return back()->withErrors([
                'reference_month' => $existsNow
                    ? 'Já existe uma fatura para este cartão neste mês de referência.'
                    : 'Não foi possível cadastrar a fatura avulsa. Tente novamente.',
            ])->withInput();
        }

        return back()->with('success', 'Fatura avulsa cadastrada.');
    }

    public function update(Request $request, Account $account, int $referenceYear, int $referenceMonth)
    {
        $this->authorizeCreditCardAccount($account);

        if ($request->filled('spent_total')) {
            $request->merge([
                'spent_total' => self::normalizeMoneyBrToNumeric((string) $request->input('spent_total')),
            ]);
        }

        $meta = CreditCardStatement::query()
            ->where('couple_id', Auth::user()->couple_id)
            ->where('account_id', $account->id)
            ->where('reference_month', $referenceMonth)
            ->where('reference_year', $referenceYear)
            ->with('paymentTransactions')
            ->first();

        if (! $meta) {
            // Para faturas “normais”, só edita se houver itens no ciclo.
            if (! $this->cycleHasCardExpense($account, $referenceMonth, $referenceYear)) {
                abort(404);
            }
            $meta = $this->firstOrCreateMeta($account, $referenceMonth, $referenceYear);
        }

        $validator = Validator::make($request->all(), [
            'due_date' => ['nullable', 'date'],
            'spent_total' => ['nullable', 'numeric', 'min:0.01'],
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

        // Referência nunca é editável (já está no path), e total/vencimento só podem mudar
        // em fatura avulsa antes de qualquer pagamento.
        if ($meta->is_avulsa) {
            if (! $meta->canEditAvulsaFields()) {
                return back()->withErrors([
                    'due_date' => 'Esta fatura avulsa não pode mais ser editada após registrar pagamentos.',
                ])->withInput()->with('open_statement_edit', [
                    'account_id' => $account->id,
                    'reference_year' => $referenceYear,
                    'reference_month' => $referenceMonth,
                ]);
            }
        } else {
            // Fatura normal: só permite alterar vencimento (total vem dos itens).
            if (array_key_exists('spent_total', $validated) && $validated['spent_total'] !== null) {
                return back()->withErrors([
                    'spent_total' => 'O total desta fatura é calculado pelos itens lançados no cartão.',
                ])->withInput()->with('open_statement_edit', [
                    'account_id' => $account->id,
                    'reference_year' => $referenceYear,
                    'reference_month' => $referenceMonth,
                ]);
            }
        }

        $meta->update([
            'due_date' => $validated['due_date'] ?? null,
            'spent_total' => ($meta->is_avulsa && array_key_exists('spent_total', $validated) && $validated['spent_total'] !== null)
                ? number_format((float) str_replace(',', '.', (string) $validated['spent_total']), 2, '.', '')
                : $meta->spent_total,
        ]);

        return back()->with('success', 'Fatura atualizada.');
    }

    public function attachPayment(Request $request, Account $account, int $referenceYear, int $referenceMonth)
    {
        $this->authorizeCreditCardAccount($account);

        $meta = CreditCardStatement::query()
            ->where('couple_id', Auth::user()->couple_id)
            ->where('account_id', $account->id)
            ->where('reference_month', $referenceMonth)
            ->where('reference_year', $referenceYear)
            ->first();

        if (! $meta && ! $this->cycleHasCardExpense($account, $referenceMonth, $referenceYear)) {
            abort(404);
        }

        $meta = $meta ?: $this->firstOrCreateMeta($account, $referenceMonth, $referenceYear);

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
        $cycleTotalFloat = $meta->is_avulsa ? (float) $meta->spent_total : (float) $cycleTotal;
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

    public function destroy(Account $account, int $referenceYear, int $referenceMonth)
    {
        $this->authorizeCreditCardAccount($account);

        $meta = CreditCardStatement::query()
            ->where('couple_id', Auth::user()->couple_id)
            ->where('account_id', $account->id)
            ->where('reference_month', $referenceMonth)
            ->where('reference_year', $referenceYear)
            ->firstOrFail();

        if (! $meta->is_avulsa) {
            abort(403);
        }

        if ($meta->paymentTransactions()->exists()) {
            return back()->withErrors([
                'payment' => 'Não é possível excluir uma fatura avulsa que já possui pagamentos vinculados.',
            ]);
        }

        $meta->delete();

        return back()->with('success', 'Fatura avulsa excluída.');
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
