<?php

namespace App\Models;

use Carbon\Carbon;
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
            if ($transaction->type === 'expense') {
                $transaction->loadMissing('accountModel');
                $account = $transaction->accountModel;
                if ($account && $account->isCreditCard()) {
                    CreditCardStatement::materializeForCycle(
                        $account,
                        (int) $transaction->reference_month,
                        (int) $transaction->reference_year
                    );
                }
            }

            Account::applyLedgerEffectToStoredBalance(
                $transaction->account_id !== null ? (int) $transaction->account_id : null,
                (int) $transaction->couple_id,
                $transaction->type,
                $transaction->amount,
                false
            );

            if ($transaction->type === 'expense' && $transaction->account_id) {
                $transaction->loadMissing('accountModel');
                if ($transaction->accountModel?->isCreditCard()) {
                    $transaction->accountModel->recalculateCreditCardLimitAvailable();
                }
            }
        });

        static::updated(function (Transaction $transaction) {
            $old = $transaction->getOriginal();

            Account::applyLedgerEffectToStoredBalance(
                isset($old['account_id']) && $old['account_id'] !== null ? (int) $old['account_id'] : null,
                (int) $transaction->couple_id,
                isset($old['type']) ? (string) $old['type'] : null,
                $old['amount'] ?? '0',
                true
            );
            Account::applyLedgerEffectToStoredBalance(
                $transaction->account_id !== null ? (int) $transaction->account_id : null,
                (int) $transaction->couple_id,
                $transaction->type,
                $transaction->amount,
                false
            );

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

            $cardIds = [];
            if (($old['type'] ?? '') === 'expense' && ! empty($old['account_id'])) {
                $oldAcc = Account::find($old['account_id']);
                if ($oldAcc?->isCreditCard()) {
                    $cardIds[] = $oldAcc->id;
                }
            }
            if ($transaction->type === 'expense' && $transaction->account_id) {
                $transaction->loadMissing('accountModel');
                if ($transaction->accountModel?->isCreditCard()) {
                    $cardIds[] = $transaction->accountModel->id;
                }
            }
            foreach (array_unique($cardIds) as $cid) {
                Account::query()->find($cid)?->recalculateCreditCardLimitAvailable();
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

            Account::applyLedgerEffectToStoredBalance(
                $transaction->account_id !== null ? (int) $transaction->account_id : null,
                (int) $transaction->couple_id,
                $transaction->type,
                $transaction->amount,
                true
            );

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

            $account->recalculateCreditCardLimitAvailable();
        });
    }

    protected $fillable = [
        'couple_id',
        'user_id',
        'account_id',
        'description',
        'amount',
        'payment_method',
        'type',
        'date',
        'reference_month',
        'reference_year',
        'installment_parent_id',
        'recurring_transaction_id',
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

    /**
     * @param  array<int, array{category_id: int, amount: string}>  $rows  amount com 2 casas decimais
     */
    public function syncCategorySplits(array $rows): void
    {
        $this->categorySplits()->delete();
        foreach ($rows as $row) {
            $this->categorySplits()->create([
                'category_id' => $row['category_id'],
                'amount' => $row['amount'],
            ]);
        }
    }

    public function categorySplits()
    {
        return $this->hasMany(TransactionCategorySplit::class)->orderBy('id');
    }

    public function accountModel()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function recurringTransaction()
    {
        return $this->belongsTo(RecurringTransaction::class);
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
     * Descrição sem o sufixo "(Parcela x/y)" usado nas parcelas do cartão.
     */
    public function baseDescriptionWithoutInstallmentSuffix(): string
    {
        $d = (string) $this->description;
        $base = preg_replace('/\s*\(Parcela\s+\d+\/\d+\)\s*$/u', '', $d);
        if ($base === '' || trim($base) === '') {
            return $d;
        }

        return $base;
    }

    /**
     * Sufixo " (Parcela x/y)" no fim da descrição, se existir (parcelas no cartão).
     */
    public function installmentParcelSuffixFromDescription(): ?string
    {
        if (preg_match('/(\s*\(Parcela\s+\d+\/\d+\))\s*$/u', (string) $this->description, $m)) {
            return $m[1];
        }

        return null;
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
     * Lançamento em conta corrente vinculado como pagamento de fatura de cartão.
     */
    public function isCreditCardInvoicePaymentTransaction(): bool
    {
        if ($this->relationLoaded('creditCardStatementsPaidFor')) {
            return $this->creditCardStatementsPaidFor->isNotEmpty();
        }

        return $this->creditCardStatementsPaidFor()->exists();
    }

    /**
     * Despesa no cartão cujo ciclo já tem pagamento ou está quitada — não permite alterar só o valor.
     */
    public function blocksAmountEditDueToCreditCardStatement(): bool
    {
        if ($this->type !== 'expense') {
            return false;
        }

        $this->loadMissing('accountModel');
        if (! $this->accountModel?->isCreditCard()) {
            return false;
        }

        if ($this->account_id === null || $this->reference_month === null || $this->reference_year === null) {
            return false;
        }

        $stmt = CreditCardStatement::query()
            ->where('couple_id', $this->couple_id)
            ->where('account_id', $this->account_id)
            ->where('reference_month', $this->reference_month)
            ->where('reference_year', $this->reference_year)
            ->first();

        return $stmt !== null && $stmt->blocksEditingCardExpenses();
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
     * Exclui despesas vinculadas à quitação de fatura (evita duplicar gasto no cartão + pagamento em agregações que tratam tudo junto).
     * Usado em **Orçamentos** e outras agregações em que a quitação de fatura não deve contar em duplicado com o gasto no cartão — **não** nos KPIs Receitas/Despesas/Saldo do painel (`whereMatchesDashboardKpiPeriod`).
     */
    public function scopeExcludingCreditCardInvoicePayments(Builder $query): Builder
    {
        return $query->whereDoesntHave('creditCardStatementsPaidFor');
    }

    /**
     * Mês de referência para os cartões **Receitas**, **Despesas** e **Saldo** do painel: toda receita;
     * despesas **efetivas em caixa** (conta que não é cartão de crédito **ou** lançamento de pagamento de fatura na conta corrente).
     * **Exclui** compras lançadas diretamente no cartão de crédito (ainda não “pagas” pela conta).
     */
    public function scopeWhereMatchesDashboardKpiPeriod(Builder $query, int $month, int $year): Builder
    {
        return $query
            ->where('reference_month', $month)
            ->where('reference_year', $year)
            ->where(function (Builder $q) {
                $q->where('type', 'income')
                    ->orWhere(function (Builder $q2) {
                        $q2->where('type', 'expense')
                            ->where(function (Builder $q3) {
                                $q3->whereHas('creditCardStatementsPaidFor')
                                    ->orWhereDoesntHave('accountModel', fn (Builder $a) => $a->where('kind', Account::KIND_CREDIT_CARD));
                            });
                    });
            });
    }

    /**
     * Período do filtro da página de lançamentos: conta corrente (e demais) por mês de referência;
     * despesas em cartão de crédito pela data da compra (campo date) no mês civil.
     */
    public function scopeWhereMatchesTransactionsListingPeriod(Builder $query, int $month, int $year): Builder
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $end = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        return $query->where(function (Builder $q) use ($month, $year, $start, $end) {
            $q->where(function (Builder $q2) use ($month, $year) {
                $q2->whereNot(function (Builder $q3) {
                    $q3->where('type', 'expense')
                        ->whereHas('accountModel', fn (Builder $a) => $a->where('kind', Account::KIND_CREDIT_CARD));
                })
                    ->where('reference_month', $month)
                    ->where('reference_year', $year);
            })->orWhere(function (Builder $q2) use ($start, $end) {
                $q2->where('type', 'expense')
                    ->whereHas('accountModel', fn (Builder $a) => $a->where('kind', Account::KIND_CREDIT_CARD))
                    ->whereBetween('date', [$start, $end]);
            });
        });
    }

    /**
     * Na listagem, esconde parcelas “filhas” do cartão (a linha da compra é a primeira parcela / raiz).
     */
    public function scopeWhereCreditCardInstallmentVisibleInList(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('installment_parent_id')
                ->orWhereNot(function (Builder $q2) {
                    $q2->where('type', 'expense')
                        ->whereHas('accountModel', fn (Builder $a) => $a->where('kind', Account::KIND_CREDIT_CARD));
                });
        });
    }
}
