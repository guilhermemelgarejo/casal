<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CreditCardStatement extends Model
{
    /**
     * Soma das despesas no cartão para o ciclo (valor persistido em {@see $spent_total}).
     */
    public static function sumCardExpensesForCycle(int $coupleId, int $accountId, int $referenceMonth, int $referenceYear): string
    {
        $sum = Transaction::query()
            ->where('couple_id', $coupleId)
            ->where('account_id', $accountId)
            ->where('reference_month', $referenceMonth)
            ->where('reference_year', $referenceYear)
            ->where('type', 'expense')
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * Atualiza {@see $spent_total} na fatura desse ciclo, se existir registro.
     */
    public static function refreshSpentTotalForCycle(int $coupleId, int $accountId, int $referenceMonth, int $referenceYear): void
    {
        $formatted = self::sumCardExpensesForCycle($coupleId, $accountId, $referenceMonth, $referenceYear);

        self::query()
            ->where('couple_id', $coupleId)
            ->where('account_id', $accountId)
            ->where('reference_month', $referenceMonth)
            ->where('reference_year', $referenceYear)
            ->update(['spent_total' => $formatted]);
    }

    /**
     * Garante um registro de metadados para o ciclo (cartão + mês/ano de referência).
     * Na criação, define due_date com base no dia configurado no cartão (mesmo mês da referência).
     * Se o registro já existia sem vencimento, preenche com a sugestão atual.
     * Atualiza sempre o total materializado ({@see $spent_total}) com a soma das despesas no cartão.
     */
    public static function materializeForCycle(Account $account, int $referenceMonth, int $referenceYear): self
    {
        $suggested = $account->defaultStatementDueDate($referenceMonth, $referenceYear);

        $meta = self::firstOrCreate(
            [
                'couple_id' => $account->couple_id,
                'account_id' => $account->id,
                'reference_month' => $referenceMonth,
                'reference_year' => $referenceYear,
            ],
            [
                'due_date' => $suggested?->toDateString(),
                'paid_at' => null,
                'spent_total' => '0.00',
            ]
        );

        if ($suggested !== null && $meta->due_date === null) {
            $meta->update(['due_date' => $suggested->toDateString()]);
        }

        self::refreshSpentTotalForCycle(
            $account->couple_id,
            $account->id,
            $referenceMonth,
            $referenceYear
        );

        return $meta->fresh();
    }

    protected $fillable = [
        'couple_id',
        'account_id',
        'reference_month',
        'reference_year',
        'spent_total',
        'due_date',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_month' => 'integer',
            'reference_year' => 'integer',
            'spent_total' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'date',
        ];
    }

    /**
     * Soma dos valores dos lançamentos de conta corrente vinculados como pagamento desta fatura.
     */
    public function paymentsTotal(): float
    {
        return (float) $this->paymentTransactions()->sum('amount');
    }

    public function remainingToPay(): float
    {
        $total = (float) $this->spent_total;

        return max(0, round($total - $this->paymentsTotal(), 2));
    }

    public function isFullyPaidByPayments(): bool
    {
        $total = (float) $this->spent_total;
        if ($total < 0.005) {
            return false;
        }

        return $this->paymentsTotal() + 0.005 >= $total;
    }

    /**
     * Fatura quitada: soma dos pagamentos cobre o total, ou data de pagamento manual sem lançamentos vinculados.
     */
    public function isPaid(): bool
    {
        if ($this->isFullyPaidByPayments()) {
            return true;
        }

        return $this->paid_at !== null && $this->paymentTransactions()->count() === 0;
    }

    public function hasPartialPayments(): bool
    {
        return $this->paymentTransactions()->exists() && ! $this->isFullyPaidByPayments();
    }

    /**
     * Impede editar apenas o valor das despesas desse ciclo: há pagamento vinculado ou fatura quitada.
     */
    public function blocksEditingCardExpenses(): bool
    {
        return $this->paymentTransactions()->exists() || $this->isPaid();
    }

    /**
     * Ajusta {@see paid_at} consoante os lançamentos vinculados (parcial vs quitada).
     */
    public function syncPaidMetadata(): void
    {
        $count = $this->paymentTransactions()->count();
        if ($count === 0) {
            $this->update(['paid_at' => null]);
        } elseif ($this->isFullyPaidByPayments()) {
            $latest = $this->paymentTransactions()
                ->reorder()
                ->orderByDesc('transactions.date')
                ->orderByDesc('transactions.id')
                ->first();

            $this->update([
                'paid_at' => $latest?->date
                    ? Carbon::parse($latest->date)->toDateString()
                    : null,
            ]);
        } else {
            $this->update(['paid_at' => null]);
        }

        $this->loadMissing('account');
        $this->account?->recalculateCreditCardLimitAvailable();
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function paymentTransactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'credit_card_statement_payments')
            ->withTimestamps();
    }
}
