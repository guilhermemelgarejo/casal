<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCardStatement;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\PaymentMethods;
use App\Support\TransactionCategorySplitDistribution;
use App\Support\TransactionListingPresentation;
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

        // Permite atualizar apenas o valor (clientes/testes antigos) reaproveitando a descrição atual.
        if (! $request->filled('description')) {
            $request->merge([
                'description' => $transaction->baseDescriptionWithoutInstallmentSuffix(),
            ]);
        }

        $suffix = $transaction->installmentParcelSuffixFromDescription();
        $descriptionMax = $suffix !== null ? max(1, 255 - mb_strlen($suffix)) : 255;

        try {
            $request->validate([
                'amount' => ['required', 'numeric'],
                'description' => ['required', 'string', 'max:'.$descriptionMax],
                'credit_limit_confirm_token' => ['nullable', 'string', 'size:64'],
                'category_allocations' => 'nullable|array|max:5',
                'category_allocations.*.category_id' => 'nullable|exists:categories,id',
                'category_allocations.*.amount' => 'nullable|numeric|min:0.01',
                'installment_scope' => ['nullable', 'string', Rule::in(['single', 'all'])],
            ]);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('edit_transaction_id', $transaction->id);
        }

        $newDescriptionFull = $this->normalizedDescriptionForUpdate(
            $transaction,
            (string) $request->input('description')
        );

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $raw = (float) $amountNormalized;
        $isRefund = $transaction->refund_of_transaction_id !== null;
        $signed = $isRefund ? (-1 * abs($raw)) : abs($raw);
        $newAmountCentsAbs = (int) round(abs($signed) * 100);
        if ($newAmountCentsAbs < 1) {
            return back()
                ->withErrors(['amount' => 'Valor inválido.'])
                ->withInput()
                ->with('edit_transaction_id', $transaction->id);
        }

        $newAmountFormatted = number_format($signed, 2, '.', '');
        $oldAmountFormatted = number_format((float) $transaction->amount, 2, '.', '');
        $amountChanged = $oldAmountFormatted !== $newAmountFormatted;

        $descriptionChanged = (string) $transaction->description !== $newDescriptionFull;
        $hasCategoryAllocations = is_array($request->input('category_allocations'));

        $blockReason = $this->transactionEditBlockedReason($transaction, $amountChanged);
        if ($blockReason !== null) {
            return back()->with('error', $blockReason);
        }

        $limitRedirect = $this->rejectCreditCardLimitIfUnconfirmedForUpdate(
            $request,
            $transaction,
            $newAmountFormatted
        );
        if ($limitRedirect !== null) {
            return $limitRedirect->with('edit_transaction_id', $transaction->id);
        }

        $splitRows = null;
        $installmentScope = $request->input('installment_scope', 'single');
        $applyToAllInstallments = $installmentScope === 'all';
        $allocPairs = null;
        if ($hasCategoryAllocations) {
            $allocParsed = $this->parseCategoryAllocations(
                $request,
                $newAmountCentsAbs,
                (string) $transaction->type,
                (int) Auth::user()->couple_id
            );
            if (isset($allocParsed['errors'])) {
                return back()
                    ->withErrors($allocParsed['errors'])
                    ->withInput()
                    ->with('edit_transaction_id', $transaction->id);
            }

            $allocPairs = $allocParsed['pairs'];
            $splitSign = $signed < 0 ? -1 : 1;
            $splitRows = array_map(
                fn ($p) => [
                    'category_id' => (int) $p['category_id'],
                    'amount' => number_format((((int) $p['cents']) * $splitSign) / 100, 2, '.', ''),
                ],
                $allocParsed['pairs']
            );
        } elseif ($amountChanged) {
            try {
                $scaled = $this->categorySplitRowsScaledToAmount($transaction, $newAmountCentsAbs);
                $splitSign = $signed < 0 ? -1 : 1;
                $splitRows = array_map(function (array $r) use ($splitSign) {
                    $amt = (float) str_replace(',', '.', (string) ($r['amount'] ?? '0'));
                    $abs = abs($amt);

                    return [
                        'category_id' => (int) $r['category_id'],
                        'amount' => number_format($abs * $splitSign, 2, '.', ''),
                    ];
                }, $scaled);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput()->with('edit_transaction_id', $transaction->id);
            }
        }

        $categoryChanged = $splitRows !== null;
        if ($applyToAllInstallments && ! $categoryChanged) {
            $applyToAllInstallments = false;
        }

        if (! $amountChanged && ! $descriptionChanged && ! $categoryChanged) {
            session()->forget('edit_transaction_id');
            $this->flashOpenInstallmentModalRootIfRequested($request, $transaction);

            return back()->with('success', 'Lançamento inalterado.');
        }

        DB::transaction(function () use ($transaction, $newAmountFormatted, $splitRows, $newDescriptionFull, $amountChanged, $descriptionChanged, $categoryChanged, $applyToAllInstallments, $allocPairs) {
            if ($descriptionChanged) {
                $transaction->description = $newDescriptionFull;
            }
            if ($amountChanged) {
                $transaction->amount = $newAmountFormatted;
            }
            if ($amountChanged || $descriptionChanged) {
                $transaction->save();
            }
            if ($categoryChanged && $splitRows !== null) {
                if ($applyToAllInstallments) {
                    $group = $this->installmentGroupTransactionsFor($transaction);
                    if ($group->count() <= 1) {
                        $transaction->syncCategorySplits($splitRows);
                    } else {
                        $ratios = $this->categoryRatiosFromAllocPairs($allocPairs ?? []);
                        if ($ratios === []) {
                            $transaction->syncCategorySplits($splitRows);
                            return;
                        }
                        foreach ($group as $tx) {
                            $txAmtRaw = (float) str_replace(',', '.', (string) $tx->amount);
                            $txSign = $txAmtRaw < 0 ? -1 : 1;
                            $txAmountCentsAbs = (int) round(abs($txAmtRaw) * 100);
                            $rowsForTxAbs = $this->categorySplitRowsFromRatiosForAmountCents($ratios, $txAmountCentsAbs);
                            $rowsForTx = array_map(function (array $r) use ($txSign) {
                                $amt = (float) str_replace(',', '.', (string) ($r['amount'] ?? '0'));
                                $abs = abs($amt);

                                return [
                                    'category_id' => (int) $r['category_id'],
                                    'amount' => number_format($abs * $txSign, 2, '.', ''),
                                ];
                            }, $rowsForTxAbs);
                            $tx->syncCategorySplits($rowsForTx);
                        }
                    }
                } else {
                    $transaction->syncCategorySplits($splitRows);
                }
            }
        });

        session()->forget('edit_transaction_id');
        $this->flashOpenInstallmentModalRootIfRequested($request, $transaction);

        $msg = $this->transactionUpdateFlashMessage($amountChanged, $descriptionChanged, $categoryChanged);

        return back()->with('success', $msg);
    }

    private function normalizedDescriptionForUpdate(Transaction $transaction, string $baseInput): string
    {
        $base = trim($baseInput);
        $suffix = $transaction->installmentParcelSuffixFromDescription();

        return $suffix !== null ? $base.$suffix : $base;
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
        if ($transaction->internal_transfer_group_id) {
            return 'Não é possível alterar o valor de uma transferência entre contas. Exclua os dois lançamentos e registe de novo.';
        }

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

    private function transactionEditBlockedReason(Transaction $transaction, bool $wantsAmountChange): ?string
    {
        if ($transaction->internal_transfer_group_id) {
            return 'Não é possível alterar uma transferência entre contas. Exclua os dois lançamentos e registe de novo.';
        }

        if ($transaction->isCreditCardInvoicePaymentTransaction()) {
            return 'Não é possível alterar um pagamento de fatura. Exclua o lançamento em Faturas de cartão se precisar corrigir.';
        }

        if ($wantsAmountChange && $transaction->blocksAmountEditDueToCreditCardStatement()) {
            return 'Não é possível alterar o valor: esta fatura de cartão já tem pagamento registrado ou está quitada.';
        }

        return null;
    }

    private function transactionUpdateFlashMessage(bool $amountChanged, bool $descriptionChanged, bool $categoryChanged): string
    {
        if ($amountChanged && $descriptionChanged && $categoryChanged) {
            return 'Lançamento atualizado.';
        }
        if ($amountChanged && $descriptionChanged) {
            return 'Lançamento atualizado.';
        }
        if ($amountChanged && $categoryChanged) {
            return 'Valor e categorias atualizados.';
        }
        if ($descriptionChanged && $categoryChanged) {
            return 'Descrição e categorias atualizadas.';
        }
        if ($amountChanged) {
            return 'Valor do lançamento atualizado.';
        }
        if ($descriptionChanged) {
            return 'Descrição atualizada.';
        }

        return 'Categorias atualizadas.';
    }

    /**
     * @return \Illuminate\Support\Collection<int, Transaction>
     */
    private function installmentGroupTransactionsFor(Transaction $transaction): Collection
    {
        $rootId = $transaction->installmentRootId();

        return Transaction::query()
            ->where('couple_id', $transaction->couple_id)
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)
                    ->orWhere('installment_parent_id', $rootId);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, array{category_id: int, cents: int}>  $pairs
     * @return array<int, array{category_id: int, numerator: int, denominator: int}>
     */
    private function categoryRatiosFromAllocPairs(array $pairs): array
    {
        $sum = array_sum(array_map(fn ($p) => (int) $p['cents'], $pairs));
        if ($sum < 1) {
            return [];
        }

        return array_map(fn ($p) => [
            'category_id' => (int) $p['category_id'],
            'numerator' => (int) $p['cents'],
            'denominator' => $sum,
        ], $pairs);
    }

    /**
     * @param  array<int, array{category_id: int, numerator: int, denominator: int}>  $ratios
     * @return array<int, array{category_id: int, amount: string}>
     */
    private function categorySplitRowsFromRatiosForAmountCents(array $ratios, int $amountCents): array
    {
        if ($amountCents < 1 || count($ratios) < 1) {
            return [];
        }

        $rows = [];
        $allocated = 0;
        $lastIdx = count($ratios) - 1;
        for ($i = 0; $i < $lastIdx; $i++) {
            $r = $ratios[$i];
            $c = (int) intdiv($amountCents * (int) $r['numerator'], (int) $r['denominator']);
            $rows[] = [
                'category_id' => (int) $r['category_id'],
                'amount' => number_format($c / 100, 2, '.', ''),
            ];
            $allocated += $c;
        }

        $last = $ratios[$lastIdx];
        $lastCents = $amountCents - $allocated;
        $rows[] = [
            'category_id' => (int) $last['category_id'],
            'amount' => number_format($lastCents / 100, 2, '.', ''),
        ];

        return $rows;
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
            'amount' => 'O limite do cartão exige confirmação no aviso antes de salvar. Confirme e tente de novo.',
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
            'is_refund' => ['nullable', 'boolean'],
            'refund_of_transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'payment_method' => ['nullable', 'string', 'max:100', Rule::in(PaymentMethods::forRegularAccounts())],
            'installments' => 'nullable|integer|min:1|max:12',
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
            'reference_month' => 'nullable|integer|min:1|max:12',
            'reference_year' => 'nullable|integer|min:2000|max:2100',
            'credit_limit_confirm_token' => ['nullable', 'string', 'size:64'],
            'recurring_template_id' => [
                'nullable',
                'integer',
                Rule::exists('recurring_transactions', 'id')->where('couple_id', Auth::user()->couple_id),
            ],
        ]);

        $isRefund = (bool) $request->boolean('is_refund');

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $amountCentsAbs = (int) round(((float) $amountNormalized) * 100);

        $allocParsed = $this->parseCategoryAllocations(
            $request,
            $amountCentsAbs,
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

        if ($isRefund) {
            if (! $ctx['isCredit'] || (string) $request->type !== 'expense') {
                return back()->withErrors([
                    'amount' => 'Estorno é permitido apenas para despesas no cartão de crédito.',
                ])->withInput();
            }
            if ((int) $ctx['installments'] !== 1) {
                return back()->withErrors([
                    'installments' => 'Estorno não pode ser parcelado. Registre como um único lançamento (você pode lançar vários estornos se necessário).',
                ])->withInput();
            }
        }

        if ($ctx['isCredit'] && $request->type === 'expense') {
            $refBase = $ctx['referenceBase'];
            $cycles = [];
            for ($i = 0; $i < (int) $ctx['installments']; $i++) {
                $ref = $refBase->copy()->addMonths($i);
                $cycles[] = ['m' => (int) $ref->month, 'y' => (int) $ref->year];
            }
            $cycles = collect($cycles)->unique(fn ($c) => $c['y'].'-'.$c['m'])->values();

            $hasBlocked = CreditCardStatement::query()
                ->where('couple_id', Auth::user()->couple_id)
                ->where('account_id', (int) $request->account_id)
                ->where('is_avulsa', true)
                ->where(function ($q) use ($cycles) {
                    foreach ($cycles as $c) {
                        $q->orWhere(function ($qq) use ($c) {
                            $qq->where('reference_month', (int) $c['m'])
                                ->where('reference_year', (int) $c['y']);
                        });
                    }
                })
                ->exists();

            if ($hasBlocked) {
                return back()->withErrors([
                    'reference_month' => 'Não é possível lançar compras neste cartão: existe uma fatura avulsa no(s) ciclo(s) de referência envolvido(s).',
                ])->withInput();
            }
        }

        $limitRedirect = $isRefund
            ? null
            : $this->rejectCreditCardLimitIfUnconfirmed($request, $ctx, $allocParsed['pairs']);
        if ($limitRedirect !== null) {
            return $limitRedirect;
        }

        $installmentParentId = null;
        $pairs = $allocParsed['pairs'];
        $refundOfId = null;
        if ($isRefund && $request->filled('refund_of_transaction_id')) {
            $origin = Transaction::query()
                ->where('couple_id', Auth::user()->couple_id)
                ->whereKey((int) $request->input('refund_of_transaction_id'))
                ->first();
            if (! $origin) {
                return back()->withErrors([
                    'refund_of_transaction_id' => 'Compra original não encontrada.',
                ])->withInput();
            }
            $origin->loadMissing('accountModel');
            if (! $origin->accountModel?->isCreditCard() || (int) $origin->account_id !== (int) $request->account_id || (string) $origin->type !== 'expense') {
                return back()->withErrors([
                    'refund_of_transaction_id' => 'A compra original deve ser uma despesa no mesmo cartão.',
                ])->withInput();
            }
            $refundOfId = $origin->installmentRootId();
        }
        $recurringTemplateId = $request->filled('recurring_template_id')
            ? (int) $request->input('recurring_template_id')
            : null;
        DB::transaction(function () use ($ctx, &$installmentParentId, $request, $pairs, $recurringTemplateId, $isRefund, $refundOfId) {
            $installments = $ctx['installments'];
            $baseCents = $ctx['baseCents'];
            $remainderCents = $ctx['remainderCents'];
            $startDate = $ctx['startDate'];
            $referenceBase = $ctx['referenceBase'];
            $baseDescription = $ctx['baseDescription'];
            $paymentMethod = $ctx['paymentMethod'];
            $sign = $isRefund ? -1 : 1;

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
                $parcelAmount = number_format(($cents * $sign) / 100, 2, '.', '');

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

                if ($refundOfId !== null) {
                    $data['refund_of_transaction_id'] = $refundOfId;
                }

                if ($installments === 1 && $recurringTemplateId !== null && $parcelIndex === 1) {
                    $data['recurring_transaction_id'] = $recurringTemplateId;
                }

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
                        'amount' => number_format(($line['cents'] * $sign) / 100, 2, '.', ''),
                    ];
                }
                $created->syncCategorySplits($splitRows);

                if ($installments > 1 && $i === 0) {
                    $installmentParentId = $created->id;
                }
            }
        });

        return $this->redirectAfterSuccessfulTransactionStore($request);
    }

    /**
     * Redireciona sem `prefill_recurring` na query, para a modal de novo lançamento não reabrir após gravar.
     */
    private function redirectAfterSuccessfulTransactionStore(Request $request): RedirectResponse
    {
        $flash = ['success' => 'Lançamento realizado!'];

        $month = null;
        $year = null;
        $accountId = null;

        $referer = $request->headers->get('referer');
        if (is_string($referer)) {
            $path = (string) (parse_url($referer, PHP_URL_PATH) ?? '');
            $queryString = parse_url($referer, PHP_URL_QUERY);
            if (is_string($queryString) && $queryString !== '') {
                parse_str($queryString, $q);
                unset($q['prefill_recurring']);
                if (isset($q['month'])) {
                    $m = (int) $q['month'];
                    if ($m >= 1 && $m <= 12) {
                        $month = $m;
                    }
                }
                if (isset($q['year'])) {
                    $y = (int) $q['year'];
                    if ($y >= 2000 && $y <= 2100) {
                        $year = $y;
                    }
                }
                if (isset($q['account_id']) && $q['account_id'] !== '') {
                    $accountId = (int) $q['account_id'];
                }
                if (($month === null || $year === null) && isset($q['period']) && is_string($q['period'])) {
                    $periodParts = explode('-', $q['period']);
                    if (count($periodParts) >= 2) {
                        $py = (int) ($periodParts[0] ?? 0);
                        $pm = (int) ($periodParts[1] ?? 0);
                        if ($pm >= 1 && $pm <= 12 && $py >= 2000 && $py <= 2100) {
                            $month = $pm;
                            $year = $py;
                        }
                    }
                }
            }

            $trimPath = rtrim($path, '/') ?: '/';
            $isDashboard = $trimPath === '/dashboard' || str_ends_with($trimPath, '/dashboard');

            if ($isDashboard && $month !== null && $year !== null) {
                $params = array_filter(
                    [
                        'period' => sprintf('%04d-%02d', $year, $month),
                        'account_id' => $accountId,
                    ],
                    fn ($v) => $v !== null && $v !== ''
                );

                return redirect()->route('dashboard', $params)->with($flash);
            }

        }

        $d = Carbon::parse((string) $request->input('date'));

        return redirect()->route('dashboard', [
            'period' => sprintf('%04d-%02d', (int) $d->year, (int) $d->month),
        ])->with($flash);
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
                'is_refund' => ['nullable', 'boolean'],
                'recurring_template_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('recurring_transactions', 'id')->where('couple_id', Auth::user()->couple_id),
                ],
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

        if ($request->boolean('is_refund')) {
            return response()->json(['overflow' => false]);
        }

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
            'amount' => 'O limite do cartão exige confirmação no aviso antes de salvar. Recarregue a página e tente de novo.',
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

        if ($transaction->internal_transfer_group_id) {
            $peer = Transaction::query()
                ->where('couple_id', $transaction->couple_id)
                ->where('internal_transfer_group_id', $transaction->internal_transfer_group_id)
                ->where('id', '<>', $transaction->id)
                ->first();

            DB::transaction(function () use ($transaction, $peer) {
                if ($peer !== null) {
                    $peer->delete();
                }
                $transaction->delete();
            });

            return back()->with(
                'success',
                'Transferência excluída (os dois lançamentos foram removidos).'
            );
        }

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
     * Pula 1 mês da fatura para um parcelamento no cartão, deslocando as parcelas
     * a partir da parcela clicada (mantém a quantidade total).
     */
    public function skipInstallmentMonth(Request $request, Transaction $transaction)
    {
        if ($transaction->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $transaction->loadMissing('accountModel');
        if ($transaction->type !== 'expense' || ! $transaction->accountModel?->isCreditCard()) {
            return back()->with('error', 'Ação indisponível para este lançamento.');
        }

        $coupleId = (int) $transaction->couple_id;
        $rootId = $transaction->installmentRootId();

        $group = Transaction::query()
            ->where('couple_id', $coupleId)
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)
                    ->orWhere('installment_parent_id', $rootId);
            })
            ->get();

        if ($group->count() <= 1) {
            return back()->with('error', 'Ação disponível apenas para parcelamentos.');
        }

        $sorted = $group->sortBy(fn (Transaction $t) => [($t->date?->timestamp ?? 0), $t->id])->values();
        $idx = $sorted->search(fn (Transaction $t) => (int) $t->id === (int) $transaction->id);
        if ($idx === false || $idx < 0) {
            return back()->with('error', 'Parcela não encontrada no parcelamento.');
        }

        $affected = $sorted->slice($idx)->values();
        if ($affected->isEmpty()) {
            return back()->with('error', 'Nada para atualizar.');
        }

        // Bloqueio por ciclo atual (inclui fatura parcialmente paga).
        foreach ($affected as $t) {
            $refMonth = (int) ($t->reference_month ?? $t->date->month);
            $refYear = (int) ($t->reference_year ?? $t->date->year);

            $stmt = CreditCardStatement::query()
                ->where('couple_id', $coupleId)
                ->where('account_id', (int) $t->account_id)
                ->where('reference_month', $refMonth)
                ->where('reference_year', $refYear)
                ->first();

            if ($stmt && $stmt->blocksEditingCardExpenses()) {
                return back()->with(
                    'error',
                    'Não é possível pular mês: este parcelamento cai em um ciclo de fatura com pagamento registrado ou quitado (parcialmente paga também bloqueia).'
                );
            }
        }

        // Bloqueio por destino:
        // - não permitir cair em fatura avulsa;
        // - não permitir editar ciclos que já tenham pagamentos/quitada.
        $destCycles = $affected->map(function (Transaction $t) {
            $refMonth = (int) ($t->reference_month ?? $t->date->month);
            $refYear = (int) ($t->reference_year ?? $t->date->year);
            $ref = Carbon::createFromDate($refYear, $refMonth, 1)->addMonth();

            return [
                'account_id' => (int) $t->account_id,
                'reference_month' => (int) $ref->month,
                'reference_year' => (int) $ref->year,
            ];
        });

        $uniqueDest = $destCycles
            ->groupBy(fn ($x) => $x['account_id'].'-'.$x['reference_year'].'-'.$x['reference_month'])
            ->map(fn ($list) => $list->first());

        foreach ($uniqueDest as $item) {
            $stmt = CreditCardStatement::query()
                ->where('couple_id', $coupleId)
                ->where('account_id', (int) $item['account_id'])
                ->where('reference_month', (int) $item['reference_month'])
                ->where('reference_year', (int) $item['reference_year'])
                ->first();

            if (! $stmt) {
                continue;
            }

            if ($stmt->is_avulsa) {
                return back()->with(
                    'error',
                    'Não é possível pular mês: a cobrança cairia em um ciclo com fatura avulsa para este cartão.'
                );
            }

            if ($stmt->blocksEditingCardExpenses()) {
                return back()->with(
                    'error',
                    'Não é possível pular mês: o ciclo de destino da fatura já possui pagamento registrado/está quitado (inclui parcial).'
                );
            }
        }

        DB::transaction(function () use ($affected) {
            foreach ($affected as $t) {
                $refMonth = (int) ($t->reference_month ?? $t->date->month);
                $refYear = (int) ($t->reference_year ?? $t->date->year);

                $ref = Carbon::createFromDate($refYear, $refMonth, 1)->addMonth();
                $t->reference_month = (int) $ref->month;
                $t->reference_year = (int) $ref->year;
                $t->save();
            }
        });

        return back()->with('success', 'Mês da compra pulado com sucesso.');
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
            if ($category->isInternalTransferCategory()) {
                return ['errors' => ['category_allocations' => ['Não é possível usar categorias reservadas a transferências entre contas neste lançamento.']]];
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
