<?php

namespace App\Support;

use App\Models\CreditCardStatement;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Metadados de listagem de lançamentos (período por data de compra no cartão, parcelas, modais).
 */
class TransactionListingPresentation
{
    /**
     * Totais da compra no cartão e quantidade de parcelas para cada linha visível da listagem.
     *
     * @param  Collection<int, Transaction>  $pageTransactions
     * @param  Collection<string, Collection<int, Transaction>>  $installmentGroups
     * @return array<int, array{
     *     purchase_total: float,
     *     purchase_total_str: string,
     *     installment_count: int,
     *     base_description: string,
     *     refund_total: float,
     *     refund_total_str: string
     * }>
     */
    public static function creditCardPurchaseRowMetaForPage(Collection $pageTransactions, Collection $installmentGroups): array
    {
        $out = [];

        $refundTotalsByRoot = collect();
        if ($pageTransactions->isNotEmpty()) {
            $coupleId = (int) $pageTransactions->first()->couple_id;
            $rootIds = $pageTransactions
                ->filter(function (Transaction $t) {
                    $t->loadMissing('accountModel');
                    return $t->type === 'expense' && $t->accountModel?->isCreditCard();
                })
                ->map(fn (Transaction $t) => $t->installmentRootId())
                ->unique()
                ->values()
                ->all();

            if ($rootIds !== []) {
                $refundTotalsByRoot = Transaction::query()
                    ->where('couple_id', $coupleId)
                    ->whereIn('refund_of_transaction_id', $rootIds)
                    ->selectRaw('refund_of_transaction_id, SUM(amount) as refund_sum')
                    ->groupBy('refund_of_transaction_id')
                    ->pluck('refund_sum', 'refund_of_transaction_id');
            }
        }

        foreach ($pageTransactions as $t) {
            $t->loadMissing('accountModel');
            if ($t->type !== 'expense' || ! $t->accountModel?->isCreditCard()) {
                continue;
            }

            $rootKey = (string) $t->installmentRootId();
            $group = $installmentGroups->get($rootKey) ?? collect([$t]);
            $purchaseTotal = (float) $group->sum(fn (Transaction $x) => (float) $x->amount);
            $count = $group->count();
            $refundSum = (float) ($refundTotalsByRoot->get((int) $t->installmentRootId(), 0) ?? 0);
            $refundTotal = abs($refundSum);

            $out[$t->id] = [
                'purchase_total' => $purchaseTotal,
                'purchase_total_str' => number_format($purchaseTotal, 2, ',', '.'),
                'installment_count' => $count,
                'base_description' => $t->baseDescriptionWithoutInstallmentSuffix(),
                'refund_total' => $refundTotal,
                'refund_total_str' => number_format($refundTotal, 2, ',', '.'),
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, Transaction>  $pageTransactions
     * @return Collection<int, Collection<int, Transaction>>
     */
    public static function installmentGroupsForPage(int $coupleId, Collection $pageTransactions): Collection
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
            ->with('user')
            ->get();

        return $groupMembers->groupBy(fn (Transaction $t) => (string) $t->installmentRootId());
    }

    /**
     * Dados para modais de parcelamento (cartão) na listagem de lançamentos.
     *
     * @param  Collection<string, Collection<int, Transaction>>  $installmentGroups
     * @return array<string, array{
     *     rootId: int,
     *     baseDescription: string,
     *     total_amount: float,
     *     total_amount_str: string,
     *     refund_total: float,
     *     refund_total_str: string,
     *     rows: list<array<string, mixed>>
     * }>
     */
    public static function installmentGroupsModalPayload(Collection $installmentGroups): array
    {
        $out = [];

        $refundTotalsByRoot = collect();
        if ($installmentGroups->isNotEmpty()) {
            /** @var Transaction|null $any */
            $any = $installmentGroups->first()?->first();
            $coupleId = $any ? (int) $any->couple_id : null;
            $rootIds = $installmentGroups
                ->keys()
                ->map(fn ($k) => (int) $k)
                ->filter(fn (int $x) => $x > 0)
                ->values()
                ->all();
            if ($coupleId !== null && $rootIds !== []) {
                $refundTotalsByRoot = Transaction::query()
                    ->where('couple_id', $coupleId)
                    ->whereIn('refund_of_transaction_id', $rootIds)
                    ->selectRaw('refund_of_transaction_id, SUM(amount) as refund_sum')
                    ->groupBy('refund_of_transaction_id')
                    ->pluck('refund_sum', 'refund_of_transaction_id');
            }
        }

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

            // “Pular mês” só pode ser aplicado se TODAS as parcelas afetadas (da parcela clicada até o fim)
            // estiverem em ciclos sem pagamento registrado/quitado (inclui parcial).
            $blockedFlags = $sorted->map(function (Transaction $x): bool {
                if ($x->type !== 'expense' || $x->account_id === null) {
                    return false;
                }

                $refMonth = (int) ($x->reference_month ?? $x->date->month);
                $refYear = (int) ($x->reference_year ?? $x->date->year);

                $stmt = CreditCardStatement::query()
                    ->where('couple_id', (int) $x->couple_id)
                    ->where('account_id', (int) $x->account_id)
                    ->where('reference_month', $refMonth)
                    ->where('reference_year', $refYear)
                    ->first();

                return $stmt ? $stmt->blocksEditingCardExpenses() : false;
            })->values()->all();

            $rows = [];
            foreach ($sorted as $idx => $t) {
                $t->loadMissing(['accountModel', 'categorySplits', 'creditCardStatementsPaidFor', 'user']);
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
                    'type' => (string) $t->type,
                    'registered_by_name' => $t->user?->firstGivenName(),
                    'parcel_label' => ($idx + 1).'/'.$total,
                    'description' => $t->description,
                    'description_edit_base' => $t->baseDescriptionWithoutInstallmentSuffix(),
                    'date' => $t->date->format('d/m/Y'),
                    'ref_label' => $refLabel,
                    'statement_url' => $statementUrl,
                    'amount' => (float) $t->amount,
                    'amount_form' => number_format((float) $t->amount, 2, '.', ''),
                    'amount_str' => number_format((float) $t->amount, 2, ',', '.'),
                    'category_allocations' => $t->categorySplits()
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($sp) => [
                            'category_id' => (int) $sp->category_id,
                            'amount' => number_format((float) $sp->amount, 2, '.', ''),
                        ])
                        ->values()
                        ->all(),
                    'update_url' => route('transactions.update', $t),
                    'destroy_url' => route('transactions.destroy', $t),
                    'skip_month' => [
                        'allowed' => ! in_array(true, array_slice($blockedFlags, (int) $idx), true),
                    ],
                    'skip_url' => route('transactions.skip-installment-month', $t),
                    'edit' => self::transactionAmountEditMeta($t),
                    'delete' => self::transactionDeleteMeta($t, $installmentGroups),
                ];
            }

            $purchaseTotal = (float) $sorted->sum(fn (Transaction $t) => (float) $t->amount);
            $refundSum = (float) ($refundTotalsByRoot->get((int) $rootKey, 0) ?? 0);
            $refundTotal = abs($refundSum);

            $out[(string) $rootKey] = [
                'rootId' => (int) $rootKey,
                'baseDescription' => $baseDescription,
                'total_amount' => $purchaseTotal,
                'total_amount_str' => number_format($purchaseTotal, 2, ',', '.'),
                'refund_total' => $refundTotal,
                'refund_total_str' => number_format($refundTotal, 2, ',', '.'),
                'rows' => $rows,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<string, Collection<int, Transaction>>  $installmentGroups
     * @return array{paidInvoice: bool, peerCount: int, singleAllowed: bool}
     */
    public static function transactionDeleteMeta(Transaction $t, Collection $installmentGroups): array
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
    public static function transactionAmountEditMeta(Transaction $t): array
    {
        if ($t->internal_transfer_group_id) {
            return [
                'canEditAmount' => false,
                'blockedMessage' => 'Não é possível alterar o valor de uma transferência entre contas. Exclua os dois lançamentos e registre de novo.',
                'needsCreditLimitPrecheck' => false,
                'precheckUrl' => null,
            ];
        }

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

    /**
     * Dados para pré-preencher o formulário de novo lançamento a partir de um existente (atalho "Copiar").
     *
     * @return array<string, mixed>|null
     */
    public static function transactionCopyPrefillPayload(Transaction $t): ?array
    {
        if ($t->internal_transfer_group_id) {
            return null;
        }

        if ($t->isCreditCardInvoicePaymentTransaction()) {
            return null;
        }

        $t->loadMissing(['categorySplits', 'accountModel']);

        if ($t->categorySplits->isEmpty()) {
            return null;
        }

        $account = $t->accountModel;
        if ($account === null) {
            return null;
        }

        $isCard = $account->isCreditCard();
        if (! $isCard && ! $t->payment_method) {
            return null;
        }

        $amountAbs = abs((float) $t->amount);

        $splits = [];
        foreach ($t->categorySplits as $sp) {
            $splits[] = [
                'category_id' => (int) $sp->category_id,
                'amount' => number_format(abs((float) $sp->amount), 2, '.', ''),
            ];
        }

        $payload = [
            'type' => $t->type,
            'description' => $t->baseDescriptionWithoutInstallmentSuffix(),
            'amount' => number_format($amountAbs, 2, '.', ''),
            'date' => $t->date->toDateString(),
            'funding' => $isCard ? 'credit_card' : 'account',
            'account_id' => (int) $t->account_id,
            'installments' => 1,
            'splits' => $splits,
        ];

        if (! $isCard) {
            $payload['payment_method'] = (string) $t->payment_method;
        }

        if ($isCard && $t->reference_month !== null && $t->reference_year !== null) {
            $payload['reference_month'] = (int) $t->reference_month;
            $payload['reference_year'] = (int) $t->reference_year;
        }

        if ($t->recurring_transaction_id) {
            $payload['recurring_template_id'] = (int) $t->recurring_transaction_id;
        }

        return $payload;
    }
}
