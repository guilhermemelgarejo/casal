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

    public const SYSTEM_KEY_INTERNAL_TRANSFER_EXPENSE = 'internal_transfer_expense';

    public const SYSTEM_KEY_INTERNAL_TRANSFER_INCOME = 'internal_transfer_income';

    /**
     * Nome padrão ao criar o casal (texto mostrado na UI).
     */
    public const NAME_CREDIT_CARD_INVOICE_PAYMENT = 'Pagamento fatura cartão';

    public const NAME_INTERNAL_TRANSFER_EXPENSE = 'Transferência entre contas (saída)';

    public const NAME_INTERNAL_TRANSFER_INCOME = 'Transferência entre contas (entrada)';

    protected $fillable = ['couple_id', 'name', 'type', 'color', 'icon', 'system_key'];

    public function isCreditCardInvoicePayment(): bool
    {
        return $this->system_key === self::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT;
    }

    public function isInternalTransferExpense(): bool
    {
        return $this->system_key === self::SYSTEM_KEY_INTERNAL_TRANSFER_EXPENSE;
    }

    public function isInternalTransferIncome(): bool
    {
        return $this->system_key === self::SYSTEM_KEY_INTERNAL_TRANSFER_INCOME;
    }

    public function isInternalTransferCategory(): bool
    {
        return $this->isInternalTransferExpense() || $this->isInternalTransferIncome();
    }

    /**
     * Categorias reservadas ao sistema (quitação de fatura, transferências entre contas).
     */
    public function isReservedSystemCategory(): bool
    {
        return $this->isCreditCardInvoicePayment() || $this->isInternalTransferCategory();
    }

    /**
     * Garante as duas categorias de transferência interna para o casal (idempotente).
     */
    public static function ensureInternalTransferCategoriesForCouple(int $coupleId): void
    {
        if (! static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_INTERNAL_TRANSFER_EXPENSE)
            ->exists()) {
            static::query()->create([
                'couple_id' => $coupleId,
                'name' => self::NAME_INTERNAL_TRANSFER_EXPENSE,
                'type' => 'expense',
                'color' => '#94a3b8',
                'system_key' => self::SYSTEM_KEY_INTERNAL_TRANSFER_EXPENSE,
            ]);
        }

        if (! static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_INTERNAL_TRANSFER_INCOME)
            ->exists()) {
            static::query()->create([
                'couple_id' => $coupleId,
                'name' => self::NAME_INTERNAL_TRANSFER_INCOME,
                'type' => 'income',
                'color' => '#94a3b8',
                'system_key' => self::SYSTEM_KEY_INTERNAL_TRANSFER_INCOME,
            ]);
        }
    }

    public static function internalTransferExpenseForCouple(int $coupleId): ?self
    {
        return static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_INTERNAL_TRANSFER_EXPENSE)
            ->first();
    }

    public static function internalTransferIncomeForCouple(int $coupleId): ?self
    {
        return static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_INTERNAL_TRANSFER_INCOME)
            ->first();
    }

    public static function creditCardInvoicePaymentForCouple(int $coupleId): ?self
    {
        return static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT)
            ->first();
    }

    /**
     * Categorias que o usuário pode escolher em orçamento e no formulário de Lançamentos.
     */
    public function scopeExcludingCreditCardInvoicePayment(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('system_key')
                ->orWhere('system_key', '<>', self::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT);
        });
    }

    /**
     * Categorias só usadas em transferências entre contas — fora de selectores de lançamento/orçamento.
     */
    public function scopeExcludingInternalTransferCategories(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('system_key')
                ->orWhereNotIn('system_key', [
                    self::SYSTEM_KEY_INTERNAL_TRANSFER_EXPENSE,
                    self::SYSTEM_KEY_INTERNAL_TRANSFER_INCOME,
                ]);
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
