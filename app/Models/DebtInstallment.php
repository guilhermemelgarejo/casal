<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtInstallment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'debt_id',
        'couple_id',
        'installment_number',
        'due_date',
        'original_amount',
        'amount',
        'paid_amount',
        'status',
        'paid_at',
        'transaction_id',
        'barcode',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'date',
            'original_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'installment_number' => 'integer',
        ];
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExtraordinaryAmortization(): bool
    {
        if ($this->debt && $this->debt->isInstallments() && $this->debt->total_installments) {
            return $this->installment_number > $this->debt->total_installments;
        }
        return false;
    }

    public function isOverdue(?Carbon $now = null): bool
    {
        if (! $this->isPending() || ! $this->due_date) {
            return false;
        }

        $now = $now ?? Carbon::now();
        return $this->due_date->startOfDay()->lt($now->startOfDay());
    }

    public function isDueToday(?Carbon $now = null): bool
    {
        if (! $this->isPending() || ! $this->due_date) {
            return false;
        }

        $now = $now ?? Carbon::now();
        return $this->due_date->isSameDay($now);
    }

    public function statusBadgeInfo(?Carbon $now = null): array
    {
        if ($this->isPaid()) {
            return [
                'label' => 'Pago',
                'class' => 'dz-badge-success',
                'color' => '#10b981',
            ];
        }

        if ($this->status === self::STATUS_CANCELLED) {
            return [
                'label' => 'Cancelado',
                'class' => 'dz-badge-secondary',
                'color' => '#64748b',
            ];
        }

        if ($this->isOverdue($now)) {
            return [
                'label' => 'Atrasado',
                'class' => 'dz-badge-danger',
                'color' => '#ef4444',
            ];
        }

        if ($this->isDueToday($now)) {
            return [
                'label' => 'Vence Hoje',
                'class' => 'dz-badge-warning',
                'color' => '#f59e0b',
            ];
        }

        return [
            'label' => 'A Vencer',
            'class' => 'dz-badge-info',
            'color' => '#3b82f6',
        ];
    }
}
