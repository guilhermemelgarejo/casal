<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory;

    /**
     * @var array<int, list<int>>
     */
    protected static array $creditCardStatementIdsToSyncAfterDelete = [];

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            if ($transaction->type !== 'expense') {
                return;
            }
            $transaction->loadMissing('accountModel');
            $account = $transaction->accountModel;
            if (! $account || ! $account->isCreditCard()) {
                return;
            }
            CreditCardStatement::materializeForCycle(
                $account,
                (int) $transaction->reference_month,
                (int) $transaction->reference_year
            );
        });

        static::updated(function (Transaction $transaction) {
            $old = $transaction->getOriginal();

            if (($old['type'] ?? '') === 'expense' && ! empty($old['account_id'])) {
                $oldAccount = Account::find($old['account_id']);
                if ($oldAccount?->isCreditCard()) {
                    CreditCardStatement::refreshSpentTotalForCycle(
                        (int) $transaction->couple_id,
                        (int) $old['account_id'],
                        (int) $old['reference_month'],
                        (int) $old['reference_year']
                    );
                }
            }

            if ($transaction->type === 'expense' && $transaction->account_id) {
                $transaction->loadMissing('accountModel');
                if ($transaction->accountModel?->isCreditCard()) {
                    CreditCardStatement::materializeForCycle(
                        $transaction->accountModel,
                        (int) $transaction->reference_month,
                        (int) $transaction->reference_year
                    );
                }
            }
        });

        static::deleting(function (Transaction $transaction) {
            $ids = DB::table('credit_card_statement_payments')
                ->where('transaction_id', $transaction->id)
                ->pluck('credit_card_statement_id')
                ->all();

            if ($ids !== []) {
                self::$creditCardStatementIdsToSyncAfterDelete[$transaction->id] = $ids;
            }
        });

        static::deleted(function (Transaction $transaction) {
            $ids = self::$creditCardStatementIdsToSyncAfterDelete[$transaction->id] ?? [];
            unset(self::$creditCardStatementIdsToSyncAfterDelete[$transaction->id]);

            foreach ($ids as $statementId) {
                CreditCardStatement::query()->find($statementId)?->syncPaidMetadata();
            }

            if ($transaction->type !== 'expense' || ! $transaction->account_id) {
                return;
            }

            $account = Account::find($transaction->account_id);
            if (! $account?->isCreditCard()) {
                return;
            }

            CreditCardStatement::refreshSpentTotalForCycle(
                (int) $transaction->couple_id,
                (int) $transaction->account_id,
                (int) $transaction->reference_month,
                (int) $transaction->reference_year
            );
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

        $stmt = CreditCardStatement::query()
            ->where('couple_id', $this->couple_id)
            ->where('account_id', $this->account_id)
            ->where('reference_month', $this->reference_month)
            ->where('reference_year', $this->reference_year)
            ->first();

        return $stmt !== null && $stmt->isPaid();
    }

    /**
     * Faturas de cartão às quais este lançamento (conta corrente) está vinculado como pagamento.
     */
    public function creditCardStatementsPaidFor(): BelongsToMany
    {
        return $this->belongsToMany(CreditCardStatement::class, 'credit_card_statement_payments')
            ->withTimestamps();
    }

    /**
     * Exclui despesas que são apenas pagamento de fatura de cartão (não entram em totais / painel / orçamento).
     */
    public function scopeExcludingCreditCardInvoicePayments(Builder $query): Builder
    {
        return $query->whereDoesntHave('creditCardStatementsPaidFor');
    }
}
