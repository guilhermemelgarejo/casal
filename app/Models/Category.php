<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    /**
     * Chave estável na BD: identifica a categoria de quitação de fatura (não depende do nome).
     */
    public const SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT = 'credit_card_invoice_payment';

    /**
     * Nome por omissão ao criar o casal (texto mostrado na UI).
     */
    public const NAME_CREDIT_CARD_INVOICE_PAYMENT = 'Pagamento fatura cartão';

    protected $fillable = ['couple_id', 'name', 'type', 'color', 'icon', 'system_key'];

    public function isCreditCardInvoicePayment(): bool
    {
        return $this->system_key === self::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT;
    }

    public static function creditCardInvoicePaymentForCouple(int $coupleId): ?self
    {
        return static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT)
            ->first();
    }

    /**
     * Categorias que o utilizador pode escolher em orçamento e no formulário de Lançamentos.
     */
    public function scopeExcludingCreditCardInvoicePayment(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('system_key')
                ->orWhere('system_key', '<>', self::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT);
        });
    }

    public function couple()
    {
        return $this->belongsTo(Couple::class);
    }

    public function transactionCategorySplits()
    {
        return $this->hasMany(TransactionCategorySplit::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }
}
