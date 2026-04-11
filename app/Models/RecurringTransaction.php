<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringTransaction extends Model
{
    public const FUNDING_ACCOUNT = 'account';

    public const FUNDING_CREDIT_CARD = 'credit_card';

    /** @deprecated Mantido na BD; a app só usa lembrete + atalho manual (sem geração automática). */
    public const MODE_REMINDER = 'reminder';

    protected $fillable = [
        'couple_id',
        'description',
        'amount',
        'type',
        'funding',
        'account_id',
        'payment_method',
        'generation_mode',
        'day_of_month',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'day_of_month' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function categorySplits(): HasMany
    {
        return $this->hasMany(RecurringTransactionCategorySplit::class)->orderBy('id');
    }

    public function generatedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function effectiveDayInMonth(int $year, int $month): int
    {
        $dim = (int) Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $want = max(1, min(31, (int) $this->day_of_month));

        return min($want, $dim);
    }

    public function hasGeneratedForCalendarMonth(int $year, int $month): bool
    {
        return Transaction::query()
            ->where('recurring_transaction_id', $this->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->exists();
    }

    /**
     * Lembrete no painel e em /recorrentes: modelo ativo, ainda sem lançamento vinculado neste mês civil
     * (desde o dia 1). O atributo `day_of_month` continua a definir a data sugerida no pré-preenchimento em Lançamentos.
     */
    public function shouldShowReminder(Carbon $now): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $y = (int) $now->year;
        $m = (int) $now->month;
        if ($this->hasGeneratedForCalendarMonth($y, $m)) {
            return false;
        }

        return true;
    }

    /**
     * Lembrete considerado vencido para o mês civil de `$now`: o dia atual já passou do dia sugerido
     * (`day_of_month` limitado ao último dia do mês — ver {@see effectiveDayInMonth()}).
     */
    public function isReminderOverdueForCalendarMonth(Carbon $now): bool
    {
        $effective = $this->effectiveDayInMonth((int) $now->year, (int) $now->month);

        return (int) $now->day > $effective;
    }

    /**
     * Dados para o modal de edição do modelo (JSON no cliente; evita payload em data-attribute).
     *
     * @return array<string, mixed>
     */
    public function toEditModalPayload(): array
    {
        $this->loadMissing('categorySplits');

        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'type' => $this->type,
            'funding' => $this->funding,
            'account_id' => (int) $this->account_id,
            'payment_method' => $this->payment_method,
            'day_of_month' => (int) $this->day_of_month,
            'is_active' => $this->is_active ? 1 : 0,
            'splits' => $this->categorySplits->map(fn ($s) => [
                'category_id' => (int) $s->category_id,
                'amount' => number_format((float) $s->amount, 2, '.', ''),
            ])->values()->all(),
        ];
    }

    /**
     * Dados para pré-preencher o formulário de novo lançamento (atalho a partir do modelo).
     *
     * @return array<string, mixed>
     */
    public function toTransactionPrefillPayload(Carbon $now): array
    {
        $this->loadMissing('categorySplits');

        $y = (int) $now->year;
        $m = (int) $now->month;
        $day = $this->effectiveDayInMonth($y, $m);
        $date = Carbon::createFromDate($y, $m, $day)->toDateString();

        $splits = [];
        foreach ($this->categorySplits as $s) {
            $splits[] = [
                'category_id' => (int) $s->category_id,
                'amount' => number_format((float) $s->amount, 2, '.', ''),
            ];
        }

        return [
            'type' => $this->type,
            'description' => $this->description,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'date' => $date,
            'funding' => $this->funding,
            'payment_method' => $this->payment_method,
            'account_id' => (int) $this->account_id,
            'installments' => 1,
            'recurring_template_id' => $this->id,
            'splits' => $splits,
        ];
    }

    /**
     * @param  array<int, array{category_id: int, amount: string}>  $rows
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
}
