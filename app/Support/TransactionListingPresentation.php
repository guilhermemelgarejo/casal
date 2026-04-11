<?php

namespace App\Support;

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
     * @return array<int, array{purchase_total: float, purchase_total_str: string, installment_count: int, base_description: string}>
     */
    public static function creditCardPurchaseRowMetaForPage(Collection $pageTransactions, Collection $installmentGroups): array
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
     * @return array<string, array{rootId: int, baseDescription: string, total_amount: float, total_amount_str: string, rows: list<array<string, mixed>>}>
     */
    public static function installmentGroupsModalPayload(Collection $installmentGroups): array
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
                    'update_url' => route('transactions.update', $t),
                    'destroy_url' => route('transactions.destroy', $t),
                    'edit' => self::transactionAmountEditMeta($t),
                    'delete' => self::transactionDeleteMeta($t, $installmentGroups),
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
}
