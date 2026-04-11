<?php

namespace App\Support;

use App\Models\CreditCardStatement;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class CreditCardInvoiceReminders
{
    /**
     * Ciclos de fatura com valor a pagar, para o painel de lembretes (painel, Lançamentos, /recorrentes).
     * Apenas mês de referência até ao **mês civil seguinte** ao atual (inclui meses anteriores em aberto,
     * o mês atual e o próximo); ciclos mais à frente no calendário não entram.
     * Ordenação: vencidas primeiro, depois por data de vencimento efetiva, depois por período de referência.
     *
     * @return Collection<int, array{
     *     account_id: int,
     *     account_name: string,
     *     reference_month: int,
     *     reference_year: int,
     *     ref_label: string,
     *     remaining: float,
     *     is_overdue: bool,
     *     due_label: string|null,
     *     due_is_suggestion: bool,
     *     statements_url: string,
     * }>
     */
    public static function openStatementsForCouple(int $coupleId, Collection $cardAccounts, Carbon $now): Collection
    {
        if ($cardAccounts->isEmpty()) {
            return collect();
        }

        $cardIds = $cardAccounts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $accountsById = $cardAccounts->keyBy('id');

        $candidates = Transaction::query()
            ->where('couple_id', $coupleId)
            ->where('type', 'expense')
            ->whereIn('account_id', $cardIds)
            ->groupBy('account_id', 'reference_month', 'reference_year')
            ->selectRaw('account_id, reference_month, reference_year')
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $metaList = CreditCardStatement::query()
            ->where('couple_id', $coupleId)
            ->whereIn('account_id', $cardIds)
            ->with('paymentTransactions')
            ->get()
            ->keyBy(fn (CreditCardStatement $s) => $s->account_id.'-'.$s->reference_year.'-'.$s->reference_month);

        $nowStart = $now->copy()->startOfDay();
        $currentOrdinal = $now->year * 12 + $now->month;
        $maxRefOrdinalInclusive = $currentOrdinal + 1;

        $rows = [];
        foreach ($candidates as $c) {
            $accId = (int) $c->account_id;
            $refMonth = (int) $c->reference_month;
            $refYear = (int) $c->reference_year;
            $cycleOrdinal = $refYear * 12 + $refMonth;
            if ($cycleOrdinal > $maxRefOrdinalInclusive) {
                continue;
            }

            $account = $accountsById->get($accId);
            if ($account === null) {
                continue;
            }

            $key = $accId.'-'.$refYear.'-'.$refMonth;
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
            if ($remaining < 0.005) {
                continue;
            }

            $refLabel = sprintf('%02d/%d', $refMonth, $refYear);

            $dueCarbon = null;
            $dueIsSuggestion = false;
            if ($meta?->due_date) {
                $dueCarbon = Carbon::parse($meta->due_date)->copy()->startOfDay();
                $dueIsSuggestion = false;
            } else {
                $suggested = $account->defaultStatementDueDate($refMonth, $refYear);
                if ($suggested !== null) {
                    $dueCarbon = $suggested->copy()->startOfDay();
                    $dueIsSuggestion = true;
                }
            }

            $isOverdue = false;
            if ($dueCarbon !== null) {
                $isOverdue = $dueCarbon->lt($nowStart);
            } else {
                $isOverdue = $cycleOrdinal < $currentOrdinal;
            }

            $dueLabel = null;
            if ($dueCarbon !== null) {
                $dueLabel = ($dueIsSuggestion ? 'Sug. ' : 'Venc. ').$dueCarbon->format('d/m/Y');
            }

            $rows[] = [
                'account_id' => $accId,
                'account_name' => (string) $account->name,
                'reference_month' => $refMonth,
                'reference_year' => $refYear,
                'ref_label' => $refLabel,
                'remaining' => $remaining,
                'is_overdue' => $isOverdue,
                'due_label' => $dueLabel,
                'due_is_suggestion' => $dueIsSuggestion,
                'statements_url' => route('credit-card-statements.index', ['account_id' => $accId])
                    .'#statement-cycle-'.$accId.'-'.$refYear.'-'.$refMonth,
                '_due_ts' => $dueCarbon !== null ? $dueCarbon->getTimestamp() : PHP_INT_MAX,
                '_cycle_ord' => $cycleOrdinal,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['is_overdue'] !== $b['is_overdue']) {
                return $b['is_overdue'] <=> $a['is_overdue'];
            }
            if (($a['_due_ts'] ?? PHP_INT_MAX) !== ($b['_due_ts'] ?? PHP_INT_MAX)) {
                return ($a['_due_ts'] ?? PHP_INT_MAX) <=> ($b['_due_ts'] ?? PHP_INT_MAX);
            }

            return ($a['_cycle_ord'] ?? 0) <=> ($b['_cycle_ord'] ?? 0);
        });

        return collect($rows)->map(function (array $r) {
            unset($r['_due_ts'], $r['_cycle_ord']);

            return $r;
        })->values();
    }
}
