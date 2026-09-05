<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    use HasFactory;

    public const TYPE_INSTALLMENTS = 'installments';
    public const TYPE_FREE = 'free';

    protected $fillable = [
        'couple_id',
        'user_id',
        'name',
        'creditor',
        'type',
        'total_amount',
        'installment_amount',
        'total_installments',
        'due_day',
        'start_date',
        'default_account_id',
        'default_category_id',
        'color',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'total_installments' => 'integer',
            'due_day' => 'integer',
            'start_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(DebtInstallment::class)->orderBy('installment_number');
    }

    public function paidInstallments(): HasMany
    {
        return $this->hasMany(DebtInstallment::class)
            ->where('status', DebtInstallment::STATUS_PAID)
            ->orderBy('installment_number');
    }

    public function pendingInstallments(): HasMany
    {
        return $this->hasMany(DebtInstallment::class)
            ->where('status', DebtInstallment::STATUS_PENDING)
            ->orderBy('installment_number');
    }

    public function isInstallments(): bool
    {
        return $this->type === self::TYPE_INSTALLMENTS;
    }

    public function isFree(): bool
    {
        return $this->type === self::TYPE_FREE;
    }

    public function totalPaid(): float
    {
        return (float) $this->installments()
            ->where('status', DebtInstallment::STATUS_PAID)
            ->sum('amount');
    }

    public function remainingBalance(): float
    {
        if ($this->isInstallments() && $this->installments()->exists()) {
            return (float) $this->pendingInstallments()->sum('amount');
        }

        $paid = $this->totalPaid();
        $total = (float) $this->total_amount;
        return max(0.0, $total - $paid);
    }

    public function progressPercentage(): float
    {
        $paid = $this->totalPaid();
        $remaining = $this->remainingBalance();
        $total = $paid + $remaining;

        if ($total <= 0.0001) {
            $total = (float) $this->total_amount;
        }

        if ($total <= 0.0001) {
            return 0.0;
        }

        $pct = ($paid / $total) * 100.0;
        return (float) min(100.0, round($pct, 1));
    }

    public function paidCount(): int
    {
        $q = $this->installments()->where('status', DebtInstallment::STATUS_PAID);
        if ($this->isInstallments() && $this->total_installments) {
            $q->where('installment_number', '<=', $this->total_installments);
        }
        return $q->count();
    }

    public function pendingCount(): int
    {
        $q = $this->pendingInstallments();
        if ($this->isInstallments() && $this->total_installments) {
            $q->where('installment_number', '<=', $this->total_installments);
        }
        return $q->count();
    }

    public function extraordinaryAmortizationsCount(): int
    {
        if ($this->isInstallments() && $this->total_installments) {
            return $this->installments()
                ->where('installment_number', '>', $this->total_installments)
                ->count();
        }
        return 0;
    }

    public function totalCount(): int
    {
        if ($this->isInstallments() && $this->total_installments) {
            return (int) $this->total_installments;
        }
        return $this->installments()->count();
    }

    public function isPaidOff(): bool
    {
        if ($this->isInstallments() && $this->total_installments > 0) {
            $hasInstallments = $this->installments()->exists();
            $pendingCount = $this->pendingInstallments()->count();
            return $hasInstallments && $pendingCount === 0;
        }

        return $this->remainingBalance() <= 0.009 && (float) $this->total_amount > 0;
    }

    public function nextPendingInstallment(): ?DebtInstallment
    {
        return $this->pendingInstallments()
            ->orderBy('due_date')
            ->first();
    }

    /**
     * Gera todas as parcelas prévias da dívida com cálculo preciso de datas e distribuição de centavos.
     */
    public function generateScheduledInstallments(): void
    {
        if (! $this->isInstallments() || ! $this->total_installments || $this->total_installments < 1) {
            return;
        }

        $totalCount = (int) $this->total_installments;
        $totalCents = (int) round(((float) $this->total_amount) * 100);

        if ($this->installment_amount !== null && (float) $this->installment_amount > 0) {
            $baseCents = (int) round(((float) $this->installment_amount) * 100);
            $parcelCentsList = array_fill(0, $totalCount, $baseCents);
            // Se total_amount foi informado diferente da soma simples, ajusta no final
            $sum = $baseCents * $totalCount;
            if ($totalCents > 0 && abs($sum - $totalCents) <= 50) {
                $parcelCentsList[$totalCount - 1] += ($totalCents - $sum);
            }
        } else {
            $baseCents = intdiv($totalCents, $totalCount);
            $remainder = $totalCents - ($baseCents * $totalCount);
            $parcelCentsList = [];
            for ($j = 0; $j < $totalCount; $j++) {
                $parcelCentsList[] = $baseCents + ($j === $totalCount - 1 ? $remainder : 0);
            }
        }

        $startDate = $this->start_date ? Carbon::parse($this->start_date) : Carbon::today();
        $targetDueDay = $this->due_day ?: $startDate->day;

        for ($i = 0; $i < $totalCount; $i++) {
            $ref = $startDate->copy()->addMonthsNoOverflow($i);
            $dim = $ref->daysInMonth;
            $effectiveDay = min($targetDueDay, $dim);
            $dueDate = Carbon::createFromDate($ref->year, $ref->month, $effectiveDay);

            $instAmt = number_format($parcelCentsList[$i] / 100, 2, '.', '');
            $this->installments()->create([
                'couple_id' => $this->couple_id,
                'installment_number' => $i + 1,
                'due_date' => $dueDate->toDateString(),
                'original_amount' => $instAmt,
                'amount' => $instAmt,
                'status' => DebtInstallment::STATUS_PENDING,
            ]);
        }
    }
}
