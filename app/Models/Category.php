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

    public const SYSTEM_KEY_INVESTMENTS = 'investments_expense';

    public const SYSTEM_KEY_PIGGY_BANK_WITHDRAWAL = 'piggy_bank_withdrawal_income';

    /**
     * Nome padrão ao criar o casal (texto mostrado na UI).
     */
    public const NAME_CREDIT_CARD_INVOICE_PAYMENT = 'Pagamento fatura cartão';

    public const NAME_INTERNAL_TRANSFER_EXPENSE = 'Transferência entre contas (saída)';

    public const NAME_INTERNAL_TRANSFER_INCOME = 'Transferência entre contas (entrada)';

    public const NAME_INVESTMENTS = 'Investimentos';

    public const NAME_PIGGY_BANK_WITHDRAWAL = 'Retirada de cofrinho';

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

    public function isInvestmentsCategory(): bool
    {
        return $this->system_key === self::SYSTEM_KEY_INVESTMENTS;
    }

    public function isPiggyBankWithdrawalCategory(): bool
    {
        return $this->system_key === self::SYSTEM_KEY_PIGGY_BANK_WITHDRAWAL;
    }

    /**
     * Categorias de cofrinho (Investimentos / retirada) — não editáveis nem excluíveis, mas entram em orçamento.
     */
    public function isCofrinhoSystemCategory(): bool
    {
        return $this->isInvestmentsCategory() || $this->isPiggyBankWithdrawalCategory();
    }

    /**
     * Bloqueio de edição/exclusão na UI de categorias (reservadas + cofrinho).
     */
    public function isImmutableSystemCategory(): bool
    {
        return $this->isReservedSystemCategory() || $this->isCofrinhoSystemCategory();
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

    public static function investmentsForCouple(int $coupleId): ?self
    {
        return static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_INVESTMENTS)
            ->first();
    }

    public static function piggyBankWithdrawalForCouple(int $coupleId): ?self
    {
        return static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_PIGGY_BANK_WITHDRAWAL)
            ->first();
    }

    /**
     * Garante categorias de sistema para cofrinho (Investimentos + Retirada de cofrinho).
     */
    public static function ensureSavingsCategoriesForCouple(int $coupleId): void
    {
        static::ensureInternalTransferCategoriesForCouple($coupleId);

        if (! static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_INVESTMENTS)
            ->exists()) {
            static::query()->create([
                'couple_id' => $coupleId,
                'name' => self::NAME_INVESTMENTS,
                'type' => 'expense',
                'color' => '#0d9488',
                'system_key' => self::SYSTEM_KEY_INVESTMENTS,
            ]);
        }

        if (! static::query()
            ->where('couple_id', $coupleId)
            ->where('system_key', self::SYSTEM_KEY_PIGGY_BANK_WITHDRAWAL)
            ->exists()) {
            static::query()->create([
                'couple_id' => $coupleId,
                'name' => self::NAME_PIGGY_BANK_WITHDRAWAL,
                'type' => 'income',
                'color' => '#0d9488',
                'system_key' => self::SYSTEM_KEY_PIGGY_BANK_WITHDRAWAL,
            ]);
        }
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
