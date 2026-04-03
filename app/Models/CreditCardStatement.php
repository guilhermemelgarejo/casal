<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Atualiza {@see $spent_total} na fatura desse ciclo, se existir registo.
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
     * Garante um registo de metadados para o ciclo (cartão + mês/ano de referência).
     * Na criação, define due_date com base no dia configurado no cartão (mês seguinte à referência).
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
                'payment_transaction_id' => null,
                'spent_total' => '0.00',
            ]
        );

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
        'payment_transaction_id',
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

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_transaction_id');
    }
}
