<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\PaymentMethods;
use App\Support\TransactionCategorySplitDistribution;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    private const SESSION_CREDIT_LIMIT_OVERFLOW_PENDING = 'credit_limit_overflow_pending';

    private const SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE = 'credit_limit_overflow_pending_tx_update';

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
            ->with(['user', 'accountModel', 'categorySplits.category', 'creditCardStatementsPaidFor'])
            ->whereMatchesTransactionsListingPeriod($selectedMonth, $selectedYear)
            ->whereCreditCardInstallmentVisibleInList()
            ->when($filterAccountId !== null, fn ($q) => $q->where('account_id', $filterAccountId))
            ->latest()
            ->paginate(20);

        $installmentGroups = $this->installmentGroupsForTransactionPage($couple->id, $transactions->getCollection());
        $installmentGroupsModalPayload = $this->installmentGroupsModalPayload($installmentGroups);
        $creditCardPurchaseRowMeta = $this->creditCardPurchaseRowMetaForPage($transactions->getCollection(), $installmentGroups);
        $transactionDeleteMeta = [];
        $transactionAmountEditMeta = [];
        foreach ($transactions as $txRow) {
            $transactionDeleteMeta[$txRow->id] = $this->transactionDeleteMeta($txRow, $installmentGroups);
            $transactionAmountEditMeta[$txRow->id] = $this->transactionAmountEditMeta($txRow);
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
                'limit_tracked' => $a->tracksCreditCardLimit(),
                'limit_available_label' => $a->tracksCreditCardLimit()
                    ? number_format((float) $a->credit_card_limit_available, 2, ',', '.')
                    : null,
            ])->values()->all(),
        ];

        $referenceDefaultNext = Carbon::now()->startOfMonth()->addMonth();
        $refDefaultMonth = (int) $referenceDefaultNext->month;
        $refDefaultYear = (int) $referenceDefaultNext->year;

        $years = range($now->year - 5, $now->year + 5);

        $editTransactionModalMeta = null;
        $editTransactionIdSession = session('edit_transaction_id');
        if ($editTransactionIdSession !== null) {
            $editTx = Transaction::query()
                ->where('couple_id', $couple->id)
                ->whereKey((int) $editTransactionIdSession)
                ->with(['accountModel', 'categorySplits', 'creditCardStatementsPaidFor'])
                ->first();
            if ($editTx) {
                $editTransactionModalMeta = [
                    'id' => $editTx->id,
                    'action' => route('transactions.update', $editTx),
                    'amount' => old('amount', $editTx->amount),
                    'edit' => $this->transactionAmountEditMeta($editTx),
                ];
            }
        }

        return view('transactions.index', compact(
            'transactions',
            'categories',
            'accounts',
            'accountsSortedForFilter',
            'regularAccounts',
            'cardAccounts',
            'fundingOld',
            'paymentFlowOld',
            'txFormMode',
            'txAccountsPayload',
            'refDefaultMonth',
            'refDefaultYear',
            'selectedMonth',
            'selectedYear',
            'years',
            'filterAccountId',
            'filteredRegularAccountBalance',
            'transactionDeleteMeta',
            'transactionAmountEditMeta',
            'editTransactionModalMeta',
            'installmentGroupsModalPayload',
            'creditCardPurchaseRowMeta'
        ));
    }

    /**
     * Totais da compra no cartão e quantidade de parcelas para cada linha visível da listagem.
     *
     * @param  Collection<int, Transaction>  $pageTransactions
     * @param  Collection<string, Collection<int, Transaction>>  $installmentGroups
     * @return array<int, array{purchase_total: float, purchase_total_str: string, installment_count: int, base_description: string}>
     */
    private function creditCardPurchaseRowMetaForPage(Collection $pageTransactions, Collection $installmentGroups): array
    {
        $out = [];

        foreach ($pageTransactions as $t) {
            $t->loadMissing('accountModel');
            if ($t->type !== 'expense' || ! $t->accountModel?->isCreditCard()) {
                continue;
            }

            $rootKey = (string) $t->installmentRootId();
            $group = $installmentGroups->get($rootKey) ?? collect([$t]);
            $purchaseTotal = (float) $group->sum(fn (Transaction $x) => (float) $x->amount);
            $count = $group->count();

            $out[$t->id] = [
                'purchase_total' => $purchaseTotal,
                'purchase_total_str' => number_format($purchaseTotal, 2, ',', '.'),
                'installment_count' => $count,
                'base_description' => $t->baseDescriptionWithoutInstallmentSuffix(),
            ];
        }

        return $out;
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
     * Dados para modais de parcelamento (cartão) na página de lançamentos.
     *
     * @param  Collection<string, Collection<int, Transaction>>  $installmentGroups
     * @return array<string, array{rootId: int, baseDescription: string, total_amount: float, total_amount_str: string, rows: list<array<string, mixed>>}>
     */
    private function installmentGroupsModalPayload(Collection $installmentGroups): array
    {
        $out = [];

        foreach ($installmentGroups as $rootKey => $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $sorted = $group->sortBy(fn (Transaction $t) => [$t->date->timestamp, $t->id])->values();
            $first = $sorted->first();
            $first->loadMissing('accountModel');
            if (! $first->accountModel?->isCreditCard()) {
                continue;
            }

            $total = $sorted->count();
            $baseDescription = $first->baseDescriptionWithoutInstallmentSuffix();

            $rows = [];
            foreach ($sorted as $idx => $t) {
                $t->loadMissing(['accountModel', 'categorySplits', 'creditCardStatementsPaidFor']);
                $refMonth = (int) ($t->reference_month ?? $t->date->month);
                $refYear = (int) ($t->reference_year ?? $t->date->year);
                $refLabel = str_pad((string) $refMonth, 2, '0', STR_PAD_LEFT).'/'.$refYear;
                $cardAccountId = (int) $t->account_id;
                $statementUrl = route('credit-card-statements.index', [
                    'account_id' => $cardAccountId,
                    'reference_month' => $refMonth,
                    'reference_year' => $refYear,
                ]).'#statement-cycle-'.$cardAccountId.'-'.$refYear.'-'.$refMonth;

                $rows[] = [
                    'id' => $t->id,
                    'parcel_label' => ($idx + 1).'/'.$total,
                    'description' => $t->description,
                    'date' => $t->date->format('d/m/Y'),
                    'ref_label' => $refLabel,
                    'statement_url' => $statementUrl,
                    'amount' => (float) $t->amount,
                    'amount_form' => number_format((float) $t->amount, 2, '.', ''),
                    'amount_str' => number_format((float) $t->amount, 2, ',', '.'),
                    'update_url' => route('transactions.update', $t),
                    'destroy_url' => route('transactions.destroy', $t),
                    'edit' => $this->transactionAmountEditMeta($t),
                    'delete' => $this->transactionDeleteMeta($t, $installmentGroups),
                ];
            }

            $purchaseTotal = (float) $sorted->sum(fn (Transaction $t) => (float) $t->amount);

            $out[(string) $rootKey] = [
                'rootId' => (int) $rootKey,
                'baseDescription' => $baseDescription,
                'total_amount' => $purchaseTotal,
                'total_amount_str' => number_format($purchaseTotal, 2, ',', '.'),
                'rows' => $rows,
            ];
        }

        return $out;
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

    /**
     * @return array{
     *     canEditAmount: bool,
     *     blockedMessage: string|null,
     *     needsCreditLimitPrecheck: bool,
     *     precheckUrl: string|null
     * }
     */
    private function transactionAmountEditMeta(Transaction $t): array
    {
        if ($t->isCreditCardInvoicePaymentTransaction()) {
            return [
                'canEditAmount' => false,
                'blockedMessage' => 'Não é possível alterar o valor de um pagamento de fatura. Exclua o lançamento em Faturas de cartão se precisar corrigir.',
                'needsCreditLimitPrecheck' => false,
                'precheckUrl' => null,
            ];
        }

        if ($t->blocksAmountEditDueToCreditCardStatement()) {
            return [
                'canEditAmount' => false,
                'blockedMessage' => 'Não é possível alterar o valor: esta fatura de cartão já tem pagamento registrado ou está quitada.',
                'needsCreditLimitPrecheck' => false,
                'precheckUrl' => null,
            ];
        }

        $t->loadMissing('categorySplits');
        if ($t->categorySplits->isEmpty()) {
            return [
                'canEditAmount' => false,
                'blockedMessage' => 'Este lançamento não tem repartição por categoria; não é possível ajustar só o valor por aqui.',
                'needsCreditLimitPrecheck' => false,
                'precheckUrl' => null,
            ];
        }

        $t->loadMissing('accountModel');
        $needsPrecheck = $t->type === 'expense'
            && $t->accountModel?->isCreditCard()
            && $t->accountModel->tracksCreditCardLimit();

        return [
            'canEditAmount' => true,
            'blockedMessage' => null,
            'needsCreditLimitPrecheck' => $needsPrecheck,
            'precheckUrl' => $needsPrecheck ? route('transactions.credit-limit-precheck-update', $t) : null,
        ];
    }

    public function creditLimitPrecheckUpdate(Request $request, Transaction $transaction)
    {
        if ($transaction->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        if ($this->transactionAmountEditBlockedReason($transaction) !== null) {
            return response()->json([
                'message' => 'Não é possível alterar este lançamento.',
            ], 422);
        }

        try {
            $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Valor inválido.',
                'errors' => $e->errors(),
            ], 422);
        }

        $transaction->loadMissing('accountModel');
        $account = $transaction->accountModel;
        if ($transaction->type !== 'expense' || ! $account?->isCreditCard() || ! $account->tracksCreditCardLimit()) {
            return response()->json(['overflow' => false]);
        }

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $newFormatted = number_format((float) $amountNormalized, 2, '.', '');
        $oldFormatted = number_format((float) $transaction->amount, 2, '.', '');
        $delta = bcsub($newFormatted, $oldFormatted, 2);
        if (bccomp($delta, '0', 2) <= 0) {
            session()->forget(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE);

            return response()->json(['overflow' => false]);
        }

        $account->refresh();
        $outstanding = Account::outstandingCreditCardUtilizationAmount($account);
        $after = bcadd($outstanding, $delta, 2);
        $limit = number_format((float) $account->credit_card_limit_total, 2, '.', '');

        if (bccomp($after, $limit, 2) <= 0) {
            session()->forget(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE);

            return response()->json(['overflow' => false]);
        }

        $token = bin2hex(random_bytes(32));
        session([
            self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE => [
                'token' => $token,
                'transaction_id' => $transaction->id,
                'new_amount' => $newFormatted,
            ],
        ]);

        return response()->json([
            'overflow' => true,
            'token' => $token,
            'limit_total' => $limit,
            'outstanding_before' => $outstanding,
            'purchase_total' => $newFormatted,
            'projected_available' => bcsub($limit, $after, 2),
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        if ($transaction->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $blockReason = $this->transactionAmountEditBlockedReason($transaction);
        if ($blockReason !== null) {
            return back()->with('error', $blockReason);
        }

        try {
            $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'credit_limit_confirm_token' => ['nullable', 'string', 'size:64'],
            ]);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('edit_transaction_id', $transaction->id);
        }

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $newAmountCents = (int) round(((float) $amountNormalized) * 100);
        if ($newAmountCents < 1) {
            return back()
                ->withErrors(['amount' => 'Valor inválido.'])
                ->withInput()
                ->with('edit_transaction_id', $transaction->id);
        }

        $newAmountFormatted = number_format($newAmountCents / 100, 2, '.', '');

        $limitRedirect = $this->rejectCreditCardLimitIfUnconfirmedForUpdate(
            $request,
            $transaction,
            $newAmountFormatted
        );
        if ($limitRedirect !== null) {
            return $limitRedirect->with('edit_transaction_id', $transaction->id);
        }

        try {
            $splitRows = $this->categorySplitRowsScaledToAmount($transaction, $newAmountCents);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('edit_transaction_id', $transaction->id);
        }

        $oldAmountFormatted = number_format((float) $transaction->amount, 2, '.', '');
        if ($oldAmountFormatted === $newAmountFormatted) {
            session()->forget('edit_transaction_id');
            $this->flashOpenInstallmentModalRootIfRequested($request, $transaction);

            return back()->with('success', 'Valor inalterado.');
        }

        DB::transaction(function () use ($transaction, $newAmountFormatted, $splitRows) {
            $transaction->amount = $newAmountFormatted;
            $transaction->save();
            $transaction->syncCategorySplits($splitRows);
        });

        session()->forget('edit_transaction_id');
        $this->flashOpenInstallmentModalRootIfRequested($request, $transaction);

        return back()->with('success', 'Valor do lançamento atualizado.');
    }

    /**
     * Após salvar o valor a partir da modal de parcelas, reabrir essa modal na próxima carga.
     */
    private function flashOpenInstallmentModalRootIfRequested(Request $request, Transaction $transaction): void
    {
        if (! $request->boolean('return_from_installment_modal')) {
            return;
        }

        $rootId = $transaction->installmentRootId();
        $peerCount = Transaction::query()
            ->where('couple_id', $transaction->couple_id)
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)
                    ->orWhere('installment_parent_id', $rootId);
            })
            ->count();

        if ($peerCount > 1) {
            session()->flash('open_installment_modal_root', $rootId);
        }
    }

    private function transactionAmountEditBlockedReason(Transaction $transaction): ?string
    {
        if ($transaction->isCreditCardInvoicePaymentTransaction()) {
            return 'Não é possível alterar o valor de um pagamento de fatura. Exclua o lançamento em Faturas de cartão se precisar corrigir.';
        }

        if ($transaction->blocksAmountEditDueToCreditCardStatement()) {
            return 'Não é possível alterar o valor: esta fatura de cartão já tem pagamento registrado ou está quitada.';
        }

        $transaction->loadMissing('categorySplits');
        if ($transaction->categorySplits->isEmpty()) {
            return 'Este lançamento não tem repartição por categoria; não é possível ajustar só o valor por aqui.';
        }

        return null;
    }

    /**
     * @return array<int, array{category_id: int, amount: string}>
     */
    private function categorySplitRowsScaledToAmount(Transaction $transaction, int $newAmountCents): array
    {
        $splits = $transaction->categorySplits()->orderBy('id')->get();
        if ($splits->isEmpty()) {
            throw ValidationException::withMessages([
                'amount' => ['Repartição por categoria em falta.'],
            ]);
        }

        $oldSplitCents = [];
        foreach ($splits as $sp) {
            $oldSplitCents[] = (int) round(((float) $sp->amount) * 100);
        }

        $oldSum = array_sum($oldSplitCents);
        if ($oldSum < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Repartição por categoria inválida.'],
            ]);
        }

        $rows = [];
        $allocated = 0;
        $lastIdx = $splits->count() - 1;
        for ($i = 0; $i < $lastIdx; $i++) {
            $c = (int) intdiv($newAmountCents * $oldSplitCents[$i], $oldSum);
            $rows[] = [
                'category_id' => (int) $splits[$i]->category_id,
                'amount' => number_format($c / 100, 2, '.', ''),
            ];
            $allocated += $c;
        }

        $lastCents = $newAmountCents - $allocated;
        $rows[] = [
            'category_id' => (int) $splits[$lastIdx]->category_id,
            'amount' => number_format($lastCents / 100, 2, '.', ''),
        ];

        return $rows;
    }

    private function rejectCreditCardLimitIfUnconfirmedForUpdate(
        Request $request,
        Transaction $transaction,
        string $newAmountFormatted,
    ): ?RedirectResponse {
        $transaction->loadMissing('accountModel');
        $account = $transaction->accountModel;
        if ($transaction->type !== 'expense' || ! $account?->isCreditCard() || ! $account->tracksCreditCardLimit()) {
            session()->forget(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE);

            return null;
        }

        $oldFormatted = number_format((float) $transaction->amount, 2, '.', '');
        $delta = bcsub($newAmountFormatted, $oldFormatted, 2);
        if (bccomp($delta, '0', 2) <= 0) {
            session()->forget(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE);

            return null;
        }

        $account->refresh();
        $outstanding = Account::outstandingCreditCardUtilizationAmount($account);
        $after = bcadd($outstanding, $delta, 2);
        $limit = number_format((float) $account->credit_card_limit_total, 2, '.', '');

        if (bccomp($after, $limit, 2) <= 0) {
            session()->forget(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE);

            return null;
        }

        $pending = session(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE);
        $token = $request->input('credit_limit_confirm_token');

        if ($this->creditLimitOverflowUpdateMatches($pending, (int) $transaction->id, $newAmountFormatted, $token)) {
            session()->forget(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE);

            return null;
        }

        return back()->withErrors([
            'amount' => 'O limite do cartão exige confirmação no aviso antes de guardar. Confirme e tente de novo.',
        ])->withInput();
    }

    /**
     * @param  array<string, mixed>|null  $pending
     */
    private function creditLimitOverflowUpdateMatches(
        ?array $pending,
        int $transactionId,
        string $newAmountFormatted,
        mixed $token,
    ): bool {
        if (! is_array($pending) || ! isset($pending['token'], $pending['transaction_id'], $pending['new_amount'])) {
            return false;
        }

        if ((int) $pending['transaction_id'] !== $transactionId) {
            return false;
        }

        if (! hash_equals((string) $pending['token'], (string) ($token ?? ''))) {
            return false;
        }

        $pendingAmt = number_format((float) $pending['new_amount'], 2, '.', '');

        return hash_equals($pendingAmt, $newAmountFormatted);
    }

    public function store(Request $request)
    {
        $request->validate([
            'funding' => ['required', 'string', Rule::in(['account', 'credit_card'])],
            'category_allocations' => 'required|array|max:5',
            'category_allocations.*.category_id' => 'nullable|exists:categories,id',
            'category_allocations.*.amount' => 'nullable|numeric|min:0.01',
            'account_id' => 'required|exists:accounts,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['nullable', 'string', 'max:100', Rule::in(PaymentMethods::forRegularAccounts())],
            'installments' => 'nullable|integer|min:1|max:12',
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
            'reference_month' => 'nullable|integer|min:1|max:12',
            'reference_year' => 'nullable|integer|min:2000|max:2100',
            'credit_limit_confirm_token' => ['nullable', 'string', 'size:64'],
        ]);

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $amountCents = (int) round(((float) $amountNormalized) * 100);

        $allocParsed = $this->parseCategoryAllocations(
            $request,
            $amountCents,
            (string) $request->type,
            (int) Auth::user()->couple_id
        );
        if (isset($allocParsed['errors'])) {
            return back()->withErrors($allocParsed['errors'])->withInput();
        }

        $resolved = $this->resolveNewTransactionContext($request);
        if (isset($resolved['errors'])) {
            return back()->withErrors($resolved['errors'])->withInput();
        }
        $ctx = $resolved;

        $limitRedirect = $this->rejectCreditCardLimitIfUnconfirmed($request, $ctx, $allocParsed['pairs']);
        if ($limitRedirect !== null) {
            return $limitRedirect;
        }

        $installmentParentId = null;
        $pairs = $allocParsed['pairs'];
        DB::transaction(function () use ($ctx, &$installmentParentId, $request, $pairs) {
            $installments = $ctx['installments'];
            $baseCents = $ctx['baseCents'];
            $remainderCents = $ctx['remainderCents'];
            $startDate = $ctx['startDate'];
            $referenceBase = $ctx['referenceBase'];
            $baseDescription = $ctx['baseDescription'];
            $paymentMethod = $ctx['paymentMethod'];

            $parcelCentsList = [];
            for ($j = 0; $j < $installments; $j++) {
                $parcelCentsList[] = $baseCents + ($j === $installments - 1 ? $remainderCents : 0);
            }

            $perParcelSplits = TransactionCategorySplitDistribution::perParcel(
                $ctx['amountCents'],
                $pairs,
                $parcelCentsList
            );

            for ($i = 0; $i < $installments; $i++) {
                $parcelIndex = $i + 1;
                $cents = $parcelCentsList[$i];
                $parcelAmount = number_format($cents / 100, 2, '.', '');

                $ref = $referenceBase->copy()->addMonths($i);
                $data = [
                    'couple_id' => Auth::user()->couple_id,
                    'user_id' => Auth::id(),
                    'account_id' => $request->account_id,
                    'description' => $installments > 1
                        ? $baseDescription.' (Parcela '.$parcelIndex.'/'.$installments.')'
                        : $baseDescription,
                    'amount' => $parcelAmount,
                    'payment_method' => $paymentMethod,
                    'type' => $request->type,
                    'date' => $startDate->toDateString(),
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

                $splitRows = [];
                foreach ($perParcelSplits[$i] as $line) {
                    $splitRows[] = [
                        'category_id' => $line['category_id'],
                        'amount' => number_format($line['cents'] / 100, 2, '.', ''),
                    ];
                }
                $created->syncCategorySplits($splitRows);

                if ($installments > 1 && $i === 0) {
                    $installmentParentId = $created->id;
                }
            }
        });

        return back()->with('success', 'Lançamento realizado!');
    }

    /**
     * Verificação AJAX antes de gravar: devolve token de confirmação se o limite for ultrapassado.
     */
    public function creditLimitPrecheck(Request $request)
    {
        try {
            $request->validate([
                'funding' => ['required', 'string', Rule::in(['account', 'credit_card'])],
                'category_allocations' => 'required|array|max:5',
                'category_allocations.*.category_id' => 'nullable|exists:categories,id',
                'category_allocations.*.amount' => 'nullable|numeric|min:0.01',
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
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Os dados do lançamento não são válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $amountCents = (int) round(((float) $amountNormalized) * 100);

        $allocParsed = $this->parseCategoryAllocations(
            $request,
            $amountCents,
            (string) $request->type,
            (int) Auth::user()->couple_id
        );
        if (isset($allocParsed['errors'])) {
            return response()->json([
                'message' => 'Não foi possível validar o lançamento.',
                'errors' => $allocParsed['errors'],
            ], 422);
        }

        $resolved = $this->resolveNewTransactionContext($request);
        if (isset($resolved['errors'])) {
            return response()->json([
                'message' => 'Não foi possível validar o lançamento.',
                'errors' => $resolved['errors'],
            ], 422);
        }
        $ctx = $resolved;

        if (! $ctx['isCredit'] || $request->type !== 'expense') {
            return response()->json(['overflow' => false]);
        }

        $account = $ctx['account'];
        $account->refresh();
        if (! $account->tracksCreditCardLimit()) {
            return response()->json(['overflow' => false]);
        }

        $purchaseTotal = number_format((float) $ctx['amountNormalized'], 2, '.', '');
        $outstanding = Account::outstandingCreditCardUtilizationAmount($account);
        $after = bcadd($outstanding, $purchaseTotal, 2);
        $limit = number_format((float) $account->credit_card_limit_total, 2, '.', '');

        if (bccomp($after, $limit, 2) <= 0) {
            session()->forget(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING);

            return response()->json(['overflow' => false]);
        }

        $token = bin2hex(random_bytes(32));
        session([
            self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING => [
                'token' => $token,
                'account_id' => (int) $request->account_id,
                'purchase_total' => $purchaseTotal,
                'installments' => $ctx['installments'],
                'reference_month' => $ctx['referenceMonth'],
                'reference_year' => $ctx['referenceYear'],
                'category_allocations_signature' => $this->categoryAllocationsSignatureFromPairs($allocParsed['pairs']),
                'description' => $ctx['baseDescription'],
                'date' => $ctx['startDateStr'],
                'type' => (string) $request->type,
            ],
        ]);

        return response()->json([
            'overflow' => true,
            'token' => $token,
            'limit_total' => $limit,
            'outstanding_before' => $outstanding,
            'purchase_total' => $purchaseTotal,
            'projected_available' => bcsub($limit, $after, 2),
        ]);
    }

    /**
     * @return array{errors: array<string, array<int, string>>}|array<string, mixed>
     */
    private function resolveNewTransactionContext(Request $request): array
    {
        $account = Account::find($request->account_id);
        if (! $account || $account->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $funding = $request->input('funding');

        if ($funding === 'credit_card') {
            if (! $account->isCreditCard()) {
                return ['errors' => [
                    'account_id' => ['Selecione um cartão de crédito cadastrado.'],
                ]];
            }
            if ($request->filled('payment_method')) {
                return ['errors' => [
                    'payment_method' => ['Em cartão de crédito não informe forma de pagamento separada; o cartão já identifica o meio.'],
                ]];
            }
            $paymentMethod = null;
        } else {
            if ($account->isCreditCard()) {
                return ['errors' => [
                    'account_id' => ['Para Pix, débito, dinheiro etc., escolha uma conta (não um cartão de crédito).'],
                ]];
            }
            if (! $request->filled('payment_method')) {
                return ['errors' => [
                    'payment_method' => ['Selecione a forma de pagamento.'],
                ]];
            }
            if (! $account->allowsPaymentMethod($request->payment_method)) {
                return ['errors' => [
                    'payment_method' => ['Esta forma de pagamento não está habilitada para a conta selecionada.'],
                ]];
            }
            $paymentMethod = $request->payment_method;
        }

        $isCredit = $funding === 'credit_card';
        $installments = $isCredit ? (int) $request->input('installments', 1) : 1;
        if ($isCredit && $installments < 1) {
            return ['errors' => [
                'installments' => ['Informe a quantidade de parcelas.'],
            ]];
        }

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $amountCents = (int) round(((float) $amountNormalized) * 100);
        $baseCents = intdiv($amountCents, $installments);
        $remainderCents = $amountCents - ($baseCents * $installments);

        $startDate = Carbon::parse($request->date);
        $startDateStr = $startDate->toDateString();
        $baseDescription = (string) $request->description;

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

        return [
            'account' => $account,
            'funding' => $funding,
            'paymentMethod' => $paymentMethod,
            'isCredit' => $isCredit,
            'installments' => $installments,
            'amountNormalized' => $amountNormalized,
            'amountCents' => $amountCents,
            'baseCents' => $baseCents,
            'remainderCents' => $remainderCents,
            'startDate' => $startDate,
            'startDateStr' => $startDateStr,
            'baseDescription' => $baseDescription,
            'referenceMonth' => $referenceMonth,
            'referenceYear' => $referenceYear,
            'referenceBase' => $referenceBase,
        ];
    }

    /**
     * Bloqueia gravação se ultrapassar o limite sem token válido (confirmação via precheck + Swal).
     */
    private function rejectCreditCardLimitIfUnconfirmed(Request $request, array $ctx, array $allocationPairs): ?RedirectResponse
    {
        if (! $ctx['isCredit'] || $request->type !== 'expense') {
            return null;
        }

        $account = $ctx['account'];
        $account->refresh();
        if (! $account->tracksCreditCardLimit()) {
            return null;
        }

        $purchaseTotal = number_format((float) $ctx['amountNormalized'], 2, '.', '');
        $outstanding = Account::outstandingCreditCardUtilizationAmount($account);
        $after = bcadd($outstanding, $purchaseTotal, 2);
        $limit = number_format((float) $account->credit_card_limit_total, 2, '.', '');

        if (bccomp($after, $limit, 2) <= 0) {
            session()->forget(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING);

            return null;
        }

        if ($this->creditLimitOverflowProposalMatches(
            $request,
            session(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING),
            $purchaseTotal,
            $ctx['installments'],
            $ctx['referenceMonth'],
            $ctx['referenceYear'],
            $ctx['startDateStr'],
            $ctx['baseDescription'],
            $allocationPairs,
        )) {
            session()->forget(self::SESSION_CREDIT_LIMIT_OVERFLOW_PENDING);

            return null;
        }

        return back()->withErrors([
            'amount' => 'O limite do cartão exige confirmação no aviso antes de guardar. Recarregue a página e tente de novo.',
        ])->withInput();
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

    /**
     * Garante que o segundo envio corresponde ao mesmo pedido já avisado (evita confirmar com valores alterados).
     *
     * @param  array<string, mixed>|null  $pending
     */
    private function creditLimitOverflowProposalMatches(
        Request $request,
        ?array $pending,
        string $purchaseTotal,
        int $installments,
        int $referenceMonth,
        int $referenceYear,
        string $startDateStr,
        string $baseDescription,
        array $allocationPairs,
    ): bool {
        if (! is_array($pending) || ! isset($pending['token'], $pending['purchase_total'])) {
            return false;
        }

        if (! hash_equals($pending['token'], (string) $request->input('credit_limit_confirm_token', ''))) {
            return false;
        }

        $sigCurrent = $this->categoryAllocationsSignatureFromPairs($allocationPairs);
        if (! array_key_exists('category_allocations_signature', $pending)) {
            return false;
        }
        $sigOk = hash_equals((string) $pending['category_allocations_signature'], $sigCurrent);

        return (int) $pending['account_id'] === (int) $request->account_id
            && (string) $pending['purchase_total'] === $purchaseTotal
            && (int) $pending['installments'] === $installments
            && (int) $pending['reference_month'] === $referenceMonth
            && (int) $pending['reference_year'] === $referenceYear
            && $sigOk
            && (string) $pending['description'] === $baseDescription
            && (string) $pending['date'] === $startDateStr
            && ($pending['type'] ?? '') === (string) $request->type;
    }

    /**
     * @return array{errors: array<string, array<int, string>>}|array{pairs: array<int, array{category_id: int, cents: int}>}
     */
    private function parseCategoryAllocations(Request $request, int $amountCents, string $type, int $coupleId): array
    {
        if ($amountCents < 1) {
            return ['errors' => ['amount' => ['Valor inválido.']]];
        }

        $raw = $request->input('category_allocations', []);
        if (! is_array($raw)) {
            return ['errors' => ['category_allocations' => ['Dados de categorias inválidos.']]];
        }

        $pairs = [];
        $sum = 0;

        foreach ($raw as $row) {
            if (! is_array($row)) {
                return ['errors' => ['category_allocations' => ['Dados de categorias inválidos.']]];
            }
            $cid = isset($row['category_id']) ? (int) $row['category_id'] : 0;
            $amtStr = isset($row['amount']) ? trim((string) $row['amount']) : '';
            if ($cid < 1 && $amtStr === '') {
                continue;
            }
            if ($cid < 1 || $amtStr === '') {
                return ['errors' => ['category_allocations' => ['Cada linha utilizada precisa de categoria e valor maior que zero.']]];
            }
            $cRow = (int) round(((float) str_replace(',', '.', $amtStr)) * 100);
            if ($cRow < 1) {
                return ['errors' => ['category_allocations' => ['Cada linha utilizada precisa de categoria e valor maior que zero.']]];
            }

            $category = Category::find($cid);
            if (! $category || $category->couple_id !== $coupleId) {
                return ['errors' => ['category_allocations' => ['Categoria inválida.']]];
            }
            if ($category->isCreditCardInvoicePayment()) {
                return ['errors' => ['category_allocations' => ['Não é possível usar a categoria de quitação de fatura neste lançamento.']]];
            }
            if ($category->type !== $type) {
                return ['errors' => ['category_allocations' => ['Todas as categorias devem ser do mesmo tipo (Receita ou Despesa).']]];
            }

            $pairs[] = ['category_id' => $cid, 'cents' => $cRow];
            $sum += $cRow;
        }

        if (count($pairs) < 1) {
            return ['errors' => ['category_allocations' => ['Indique pelo menos uma categoria com valor.']]];
        }

        if (count($pairs) > 5) {
            return ['errors' => ['category_allocations' => ['No máximo 5 categorias por lançamento.']]];
        }

        if ($sum !== $amountCents) {
            return ['errors' => ['category_allocations' => ['A soma dos valores por categoria deve ser exatamente igual ao valor total do lançamento.']]];
        }

        return ['pairs' => $pairs];
    }

    /**
     * @param  array<int, array{category_id: int, cents: int}>  $pairs
     */
    private function categoryAllocationsSignatureFromPairs(array $pairs): string
    {
        $norm = array_map(fn ($p) => [
            'category_id' => (int) $p['category_id'],
            'cents' => (int) $p['cents'],
        ], $pairs);

        return json_encode($norm, JSON_UNESCAPED_UNICODE);
    }
}
