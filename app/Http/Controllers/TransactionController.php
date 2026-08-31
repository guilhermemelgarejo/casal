<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesTransactionModalPayload;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCardStatement;
use App\Models\FinancialProject;
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
    use PreparesTransactionModalPayload;

    private const SESSION_CREDIT_LIMIT_OVERFLOW_PENDING = 'credit_limit_overflow_pending';

    private const SESSION_CREDIT_LIMIT_OVERFLOW_PENDING_UPDATE = 'credit_limit_overflow_pending_tx_update';

    public function index(Request $request)
    {
        $couple = Auth::user()->couple;
        if (! $couple) {
            abort(403);
        }

        $validated = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'in:income,expense,all'],
            'search' => ['nullable', 'string', 'max:100'],
            'focus_transaction' => ['nullable', 'integer'],
        ]);

        if (! empty($validated['period'])) {
            [$year, $month] = array_map('intval', explode('-', $validated['period']));
        } else {
            $year = (int) now()->year;
            $month = (int) now()->month;
        }

        $period = sprintf('%04d-%02d', $year, $month);
        $periodDate = Carbon::createFromDate($year, $month, 1);
        $periodLabel = $periodDate->translatedFormat('F \d\e Y');
        $periodPrev = $periodDate->copy()->subMonth()->format('Y-m');
        $periodNext = $periodDate->copy()->addMonth()->format('Y-m');

        $filterAccountId = ! empty($validated['account_id']) ? (int) $validated['account_id'] : null;
        $filterCategoryId = ! empty($validated['category_id']) ? (int) $validated['category_id'] : null;
        $filterUserId = ! empty($validated['user_id']) ? (int) $validated['user_id'] : null;
        $filterType = ! empty($validated['type']) && $validated['type'] !== 'all' ? $validated['type'] : null;
        $searchQuery = ! empty($validated['search']) ? trim($validated['search']) : null;

        $query = $couple->transactions()
            ->with(['user', 'accountModel', 'categorySplits.category', 'creditCardStatementsPaidFor'])
            ->whereMatchesTransactionsListingPeriod($month, $year)
            ->whereCreditCardInstallmentVisibleInList();

        if ($filterAccountId !== null) {
            $query->where('account_id', $filterAccountId);
        }

        if ($filterUserId !== null) {
            $query->where('user_id', $filterUserId);
        }

        if ($filterType !== null) {
            $query->where('type', $filterType);
        }

        if ($filterCategoryId !== null) {
            $query->where(function ($q) use ($filterCategoryId) {
                $q->where('category_id', $filterCategoryId)
                  ->orWhereHas('categorySplits', fn ($sub) => $sub->where('category_id', $filterCategoryId));
            });
        }

        if ($searchQuery !== null) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('description', 'like', "%{$searchQuery}%")
                  ->orWhere('amount', 'like', "%{$searchQuery}%");
            });
        }

        $transactionsForPeriod = $query->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $focusTransactionId = isset($validated['focus_transaction']) ? (int) $validated['focus_transaction'] : null;
        $transactions = $transactionsForPeriod;
        if ($focusTransactionId !== null) {
            $focused = $transactionsForPeriod->firstWhere('id', $focusTransactionId);
            if ($focused instanceof Transaction) {
                $transactions = collect([$focused]);
            }
        }

        $installmentGroups = TransactionListingPresentation::installmentGroupsForPage($couple->id, $transactions);
        $installmentGroupsModalPayload = TransactionListingPresentation::installmentGroupsModalPayload($installmentGroups);
        $creditCardPurchaseRowMeta = TransactionListingPresentation::creditCardPurchaseRowMetaForPage($transactions, $installmentGroups);
        $transactionDeleteMeta = [];
        $transactionAmountEditMeta = [];
        foreach ($transactions as $txRow) {
            $transactionDeleteMeta[$txRow->id] = TransactionListingPresentation::transactionDeleteMeta($txRow, $installmentGroups);
            $transactionAmountEditMeta[$txRow->id] = TransactionListingPresentation::transactionAmountEditMeta($txRow);
        }

        $statsTransactions = $couple->transactions()
            ->whereMatchesDashboardPeriod($month, $year)
            ->select('type', 'amount')
            ->get();
        $totalIncome = (float) $statsTransactions->where('type', 'income')->sum('amount');
        $totalExpense = (float) $statsTransactions->where('type', 'expense')->sum('amount');
        $netResult = round($totalIncome - $totalExpense, 2);

        $modalPayload = $this->transactionModalPayload();
        $regularAccounts = $modalPayload['regularAccounts'] ?? collect();
        $canCreateAccountTransfer = $regularAccounts->count() >= 2;
        $transferPaymentMethods = PaymentMethods::forRegularAccounts();
        $txFormMode = $modalPayload['txFormMode'] ?? 'regular_only';

        $accounts = $couple->accounts()->orderBy('name')->get();
        $categories = $couple->categories()->orderBy('name')->get();
        $coupleUsers = $couple->users()->orderBy('name')->get();

        return view('transactions.index', array_merge(
            compact(
                'couple',
                'transactions',
                'transactionsForPeriod',
                'installmentGroups',
                'installmentGroupsModalPayload',
                'creditCardPurchaseRowMeta',
                'transactionDeleteMeta',
                'transactionAmountEditMeta',
                'month',
                'year',
                'period',
                'periodLabel',
                'periodPrev',
                'periodNext',
                'filterAccountId',
                'filterCategoryId',
                'filterUserId',
                'filterType',
                'searchQuery',
                'totalIncome',
                'totalExpense',
                'netResult',
                'canCreateAccountTransfer',
                'transferPaymentMethods',
                'txFormMode',
                'regularAccounts',
                'accounts',
                'categories',
                'coupleUsers'
            ),
            $modalPayload
        ));
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

        // Permite atualizar apenas o valor (clientes/testes antigos) reaproveitando a descrição atual.
        if (! $request->filled('description')) {
            $request->merge([
                'description' => $transaction->baseDescriptionWithoutInstallmentSuffix(),
            ]);
        }

        if (! $request->filled('date')) {
            $request->merge([
                'date' => $transaction->date?->format('Y-m-d') ?? date('Y-m-d'),
            ]);
        }

        $suffix = $transaction->installmentParcelSuffixFromDescription();
        $descriptionMax = $suffix !== null ? max(1, 255 - mb_strlen($suffix)) : 255;

        try {
            $request->validate([
                'amount' => ['required', 'numeric'],
                'description' => ['required', 'string', 'max:'.$descriptionMax],
                'date' => ['required', 'date'],
                'reference_month' => ['nullable', 'integer', 'min:1', 'max:12'],
                'reference_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
                'credit_limit_confirm_token' => ['nullable', 'string', 'size:64'],
                'category_allocations' => 'nullable|array|max:5',
                'category_allocations.*.category_id' => 'nullable|exists:categories,id',
                'category_allocations.*.amount' => 'nullable|numeric|min:0.01',
                'installment_scope' => ['nullable', 'string', Rule::in(['single', 'all'])],
                'financial_project_id' => ['nullable', 'integer'],
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
        $isRefund = $transaction->refund_of_transaction_id !== null || (float) $transaction->amount < -0.001;
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

        $newDate = Carbon::parse($request->date);
        $newDateStr = $newDate->toDateString();
        $oldDateStr = $transaction->date?->toDateString();
        $dateChanged = $oldDateStr !== $newDateStr;

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
        $financialProjectId = null;
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

            $transaction->loadMissing('accountModel');
            $fpResolved = $this->validateFinancialProjectForNewTransaction(
                $request,
                ['isCredit' => (bool) $transaction->accountModel?->isCreditCard()],
                $allocParsed['pairs'],
                (int) Auth::user()->couple_id,
                (string) $transaction->type,
                (int) ($transaction->financial_project_id ?? 0)
            );
            if (isset($fpResolved['errors'])) {
                return back()
                    ->withErrors($fpResolved['errors'])
                    ->withInput()
                    ->with('edit_transaction_id', $transaction->id);
            }
            $financialProjectId = $fpResolved['financial_project_id'] ?? null;
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

        if (! $hasCategoryAllocations) {
            // Permite transformar um lançamento existente em cofrinho sem mexer nas categorias.
            $transaction->loadMissing(['accountModel', 'categorySplits']);
            $pairs = $transaction->categorySplits->map(fn ($s) => [
                'category_id' => (int) $s->category_id,
                'cents' => (int) round(abs((float) $s->amount) * 100),
            ])->values()->all();
            $fpResolved = $this->validateFinancialProjectForNewTransaction(
                $request,
                ['isCredit' => (bool) $transaction->accountModel?->isCreditCard()],
                $pairs,
                (int) Auth::user()->couple_id,
                (string) $transaction->type,
                (int) ($transaction->financial_project_id ?? 0)
            );
            if (isset($fpResolved['errors'])) {
                return back()
                    ->withErrors($fpResolved['errors'])
                    ->withInput()
                    ->with('edit_transaction_id', $transaction->id);
            }
            $financialProjectId = $fpResolved['financial_project_id'] ?? null;
        }

        $transaction->loadMissing('accountModel');
        $isCredit = (bool) $transaction->accountModel?->isCreditCard();
        $installmentGroup = $this->installmentGroupTransactionsFor($transaction);
        $isInstallment = $installmentGroup->count() > 1;

        $oldRefMonth = (int) $transaction->reference_month;
        $oldRefYear = (int) $transaction->reference_year;
        $newRefMonth = $request->filled('reference_month') ? (int) $request->input('reference_month') : $oldRefMonth;
        $newRefYear = $request->filled('reference_year') ? (int) $request->input('reference_year') : $oldRefYear;
        $invoiceChanged = false;

        if ($isCredit) {
            $wantsInvoiceChange = ($request->filled('reference_month') && $newRefMonth !== $oldRefMonth)
                || ($request->filled('reference_year') && $newRefYear !== $oldRefYear);

            if ($wantsInvoiceChange) {
                if ($isInstallment) {
                    return back()
                        ->withErrors(['reference_month' => 'Não é possível alterar a fatura de compras parceladas.'])
                        ->withInput()
                        ->with('edit_transaction_id', $transaction->id);
                }

                $targetStmt = CreditCardStatement::query()
                    ->where('couple_id', Auth::user()->couple_id)
                    ->where('account_id', (int) $transaction->account_id)
                    ->where('reference_month', $newRefMonth)
                    ->where('reference_year', $newRefYear)
                    ->first();

                if ($targetStmt !== null) {
                    if ($targetStmt->is_avulsa) {
                        return back()
                            ->withErrors(['reference_month' => 'Não é possível mover para esta fatura: existe uma fatura avulsa neste ciclo.'])
                            ->withInput()
                            ->with('edit_transaction_id', $transaction->id);
                    }
                    if ($targetStmt->blocksEditingCardExpenses()) {
                        return back()
                            ->withErrors(['reference_month' => 'Não é possível mover para esta fatura: ela já possui pagamentos registrados ou está quitada.'])
                            ->withInput()
                            ->with('edit_transaction_id', $transaction->id);
                    }
                }

                $invoiceChanged = true;
            }
        }

        if (! $amountChanged && ! $descriptionChanged && ! $categoryChanged && ! $dateChanged && ! $invoiceChanged) {
            if ((int) ($transaction->financial_project_id ?? 0) !== (int) ($financialProjectId ?? 0)) {
                // Continua para persistir o cofrinho.
            } else {
                session()->forget('edit_transaction_id');
                $this->flashOpenInstallmentModalRootIfRequested($request, $transaction);

                return back()->with('success', 'Lançamento inalterado.');
            }
        }

        DB::transaction(function () use ($transaction, $newAmountFormatted, $splitRows, $newDescriptionFull, $amountChanged, $descriptionChanged, $categoryChanged, $applyToAllInstallments, $allocPairs, $financialProjectId, $dateChanged, $newDateStr, $newDate, $invoiceChanged, $newRefMonth, $newRefYear) {
            if ($descriptionChanged) {
                $transaction->description = $newDescriptionFull;
            }
            if ($amountChanged) {
                $transaction->amount = $newAmountFormatted;
            }
            if ((int) ($transaction->financial_project_id ?? 0) !== (int) ($financialProjectId ?? 0)) {
                $transaction->financial_project_id = $financialProjectId;
            }
            if ($invoiceChanged) {
                $transaction->reference_month = $newRefMonth;
                $transaction->reference_year = $newRefYear;
            }
            if ($dateChanged) {
                $transaction->date = $newDateStr;
                $transaction->loadMissing('accountModel');
                if (! $transaction->accountModel?->isCreditCard()) {
                    $transaction->reference_month = (int) $newDate->month;
                    $transaction->reference_year = (int) $newDate->year;
                }
            }
            if ($amountChanged || $descriptionChanged || $dateChanged || $invoiceChanged || (int) ($transaction->getOriginal('financial_project_id') ?? 0) !== (int) ($financialProjectId ?? 0)) {
                $transaction->save();
            }

            if ($dateChanged) {
                $transaction->loadMissing('accountModel');
                if ($transaction->accountModel?->isCreditCard()) {
                    $group = $this->installmentGroupTransactionsFor($transaction);
                    if ($group->count() > 1) {
                        foreach ($group as $tx) {
                            if ($tx->id !== $transaction->id) {
                                $tx->date = $newDateStr;
                                $tx->save();
                            }
                        }
                    }
                }
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
                            if ((int) ($tx->financial_project_id ?? 0) !== (int) ($financialProjectId ?? 0)) {
                                $tx->financial_project_id = $financialProjectId;
                                $tx->save();
                            }
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
            } elseif ($applyToAllInstallments) {
                // Caso o usuário só queira aplicar/remover o cofrinho no grupo, sem mexer nas categorias.
                $group = $this->installmentGroupTransactionsFor($transaction);
                foreach ($group as $tx) {
                    if ((int) ($tx->financial_project_id ?? 0) !== (int) ($financialProjectId ?? 0)) {
                        $tx->financial_project_id = $financialProjectId;
                        $tx->save();
                    }
                }
            }
        });

        session()->forget('edit_transaction_id');
        $this->flashOpenInstallmentModalRootIfRequested($request, $transaction);

        $msg = $this->transactionUpdateFlashMessage($amountChanged, $descriptionChanged, $categoryChanged, $dateChanged, $invoiceChanged);

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
            return 'Não é possível alterar o valor de uma transferência entre contas. Exclua os dois lançamentos e registre de novo.';
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
            return 'Não é possível alterar uma transferência entre contas. Exclua os dois lançamentos e registre de novo.';
        }

        if ($transaction->isCreditCardInvoicePaymentTransaction()) {
            return 'Não é possível alterar um pagamento de fatura. Exclua o lançamento em Faturas de cartão se precisar corrigir.';
        }

        if ($wantsAmountChange && $transaction->blocksAmountEditDueToCreditCardStatement()) {
            return 'Não é possível alterar o valor: esta fatura de cartão já tem pagamento registrado ou está quitada.';
        }

        return null;
    }

    private function transactionUpdateFlashMessage(
        bool $amountChanged,
        bool $descriptionChanged,
        bool $categoryChanged,
        bool $dateChanged = false,
        bool $invoiceChanged = false
    ): string {
        $changesCount = ($amountChanged ? 1 : 0) + ($descriptionChanged ? 1 : 0) + ($categoryChanged ? 1 : 0) + ($dateChanged ? 1 : 0) + ($invoiceChanged ? 1 : 0);
        if ($changesCount > 1) {
            return 'Lançamento atualizado.';
        }
        if ($invoiceChanged) {
            return 'Fatura do lançamento atualizada.';
        }
        if ($dateChanged) {
            return 'Data do lançamento atualizada.';
        }
        if ($amountChanged) {
            return 'Valor do lançamento atualizado.';
        }
        if ($descriptionChanged) {
            return 'Descrição atualizada.';
        }
        if ($categoryChanged) {
            return 'Categorias atualizadas.';
        }

        return 'Lançamento atualizado.';
    }

    /**
     * @return Collection<int, Transaction>
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
            $oldSplitCents[] = (int) round(abs((float) $sp->amount) * 100);
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
            'financial_project_id' => ['nullable', 'integer'],
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

        if (! $isRefund) {
            $fpResolved = $this->validateFinancialProjectForNewTransaction(
                $request,
                $ctx,
                $allocParsed['pairs'],
                (int) Auth::user()->couple_id,
                (string) $request->type
            );
            if (isset($fpResolved['errors'])) {
                return back()->withErrors($fpResolved['errors'])->withInput();
            }
            $financialProjectId = $fpResolved['financial_project_id'] ?? null;
        } else {
            $financialProjectId = null;
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
        DB::transaction(function () use ($ctx, &$installmentParentId, $request, $pairs, $recurringTemplateId, $isRefund, $refundOfId, $financialProjectId) {
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
                    'financial_project_id' => $financialProjectId,
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
     * Redireciona de volta para a tela de origem (sem prefill de recorrente/cofrinho na query,
     * para a modal de novo lançamento não reabrir após gravar).
     */
    private function redirectAfterSuccessfulTransactionStore(Request $request): RedirectResponse
    {
        $flash = ['success' => 'Lançamento realizado!'];

        $referer = $request->headers->get('referer') ?: url()->previous(route('transactions.index'));
        if (is_string($referer) && $referer !== '') {
            $parsed = parse_url($referer);
            if ($parsed !== false) {
                if (isset($parsed['host']) && $parsed['host'] !== $request->getHost()) {
                    return redirect()->route('transactions.index')->with($flash);
                }

                $scheme = isset($parsed['scheme']) ? $parsed['scheme'].'://' : '';
                $host = $parsed['host'] ?? '';
                $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
                $user = $parsed['user'] ?? '';
                $pass = isset($parsed['pass']) ? ':'.$parsed['pass'] : '';
                $auth = ($user !== '' || $pass !== '') ? "{$user}{$pass}@" : '';
                $path = $parsed['path'] ?? '/';

                $query = [];
                if (isset($parsed['query']) && $parsed['query'] !== '') {
                    parse_str($parsed['query'], $query);
                    unset($query['prefill_recurring'], $query['prefill_cofrinho'], $query['prefill_cofrinho_kind']);
                }

                $queryString = http_build_query($query);
                $fragment = isset($parsed['fragment']) && $parsed['fragment'] !== '' ? '#'.$parsed['fragment'] : '';

                $targetUrl = $scheme.$auth.$host.$port.$path.($queryString !== '' ? '?'.$queryString : '').$fragment;

                if ($targetUrl !== '') {
                    return redirect()->to($targetUrl)->with($flash);
                }
            }
        }

        return redirect()->route('transactions.index')->with($flash);
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
     * @param  array<int, array{category_id: int, cents: int}>  $pairs
     * @return array{errors: array<string, array<int, string>>}|array{financial_project_id: int|null}
     */
    private function validateFinancialProjectForNewTransaction(
        Request $request,
        array $ctx,
        array $pairs,
        int $coupleId,
        ?string $txTypeOverride = null,
        ?int $currentFpId = null
    ): array {
        $raw = $request->input('financial_project_id');
        $fpId = ($raw === null || $raw === '') ? null : (int) $raw;

        $categoryIds = array_values(array_unique(array_map(fn ($p) => (int) $p['category_id'], $pairs)));
        $categories = Category::query()
            ->where('couple_id', $coupleId)
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        $hasInv = false;
        $hasW = false;
        foreach ($pairs as $p) {
            $c = $categories->get((int) $p['category_id']);
            if ($c?->isInvestmentsCategory()) {
                $hasInv = true;
            }
            if ($c?->isPiggyBankWithdrawalCategory()) {
                $hasW = true;
            }
        }

        if ($fpId !== null) {
            $project = FinancialProject::query()
                ->where('couple_id', $coupleId)
                ->whereKey($fpId)
                ->first();
            if (! $project) {
                return ['errors' => ['financial_project_id' => ['Cofrinho inválido.']]];
            }
            if (! $project->is_active && ($currentFpId === null || $currentFpId !== (int) $project->id)) {
                return ['errors' => ['financial_project_id' => ['Este cofrinho está desativado.']]];
            }
            if ($ctx['isCredit']) {
                return ['errors' => ['financial_project_id' => ['Cofrinho só em conta corrente (não em cartão).']]];
            }
            if (! $hasInv && ! $hasW) {
                return ['errors' => ['financial_project_id' => ['Use projeto só com as categorias Investimentos ou Retirada de cofrinho.']]];
            }
        }

        $txType = $txTypeOverride ?? (string) $request->type;

        if ($hasInv) {
            if ($ctx['isCredit']) {
                return ['errors' => ['category_allocations' => ['Investimentos não se aplica em compras no cartão de crédito.']]];
            }
            if ($txType !== 'expense') {
                return ['errors' => ['category_allocations' => ['Investimentos exige um lançamento do tipo despesa.']]];
            }
        }
        if ($hasW) {
            if ($ctx['isCredit']) {
                return ['errors' => ['category_allocations' => ['Retirada de cofrinho não se aplica em compras no cartão de crédito.']]];
            }
            if ($txType !== 'income') {
                return ['errors' => ['category_allocations' => ['Retirada de cofrinho exige um lançamento do tipo receita.']]];
            }
            if ($fpId === null) {
                return ['errors' => ['financial_project_id' => ['Selecione o cofrinho para a retirada.']]];
            }
        }

        return ['financial_project_id' => $fpId];
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
     * Pula 1 mês da fatura para um parcelamento no cartão, adiando as parcelas
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
