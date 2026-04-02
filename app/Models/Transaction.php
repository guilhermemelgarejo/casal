<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Transaction $transaction) {
            CreditCardStatement::query()
                ->where('payment_transaction_id', $transaction->id)
                ->update([
                    'payment_transaction_id' => null,
                    'paid_at' => null,
                ]);
        });
    }

    protected $fillable = [
        'couple_id',
        'user_id',
        'category_id',
        'account_id',
        'description',
        'amount',
        'payment_method',
        'account',
        'type',
        'date',
        'reference_month',
        'reference_year',
        'installment_parent_id',
    ];

    protected $casts = [
        'date' => 'date',
        'reference_month' => 'integer',
        'reference_year' => 'integer',
    ];

    public function couple()
    {
        return $this->belongsTo(Couple::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function accountModel()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Primeira parcela do mesmo parcelamento (autorelacionamento).
     */
    public function installmentParent()
    {
        return $this->belongsTo(self::class, 'installment_parent_id');
    }

    /**
     * Demais parcelas do mesmo parcelamento.
     */
    public function installmentChildren()
    {
        return $this->hasMany(self::class, 'installment_parent_id');
    }

    /**
     * ID da primeira parcela do grupo (raiz do parcelamento no cartão).
     */
    public function installmentRootId(): int
    {
        return (int) ($this->installment_parent_id ?? $this->id);
    }

    /**
     * Despesa no cartão cujo mês/ano de referência corresponde a uma fatura já marcada como paga (metadados).
     */
    public function isInPaidCreditCardInvoiceCycle(): bool
    {
        if ($this->account_id === null || $this->reference_month === null || $this->reference_year === null) {
            return false;
        }

        return CreditCardStatement::query()
            ->where('couple_id', $this->couple_id)
            ->where('account_id', $this->account_id)
            ->where('reference_month', $this->reference_month)
            ->where('reference_year', $this->reference_year)
            ->whereNotNull('paid_at')
            ->exists();
    }

    /**
     * Fatura de cartão paga por este lançamento (conta corrente), se existir.
     */
    public function creditCardStatementPaidFor(): HasOne
    {
        return $this->hasOne(CreditCardStatement::class, 'payment_transaction_id');
    }

    /**
     * Exclui despesas que são apenas pagamento de fatura de cartão (não entram em totais / painel / orçamento).
     */
    public function scopeExcludingCreditCardInvoicePayments(Builder $query): Builder
    {
        return $query->whereDoesntHave('creditCardStatementPaidFor');
    }
}
