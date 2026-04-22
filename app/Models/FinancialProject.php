<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialProject extends Model
{
    protected $fillable = [
        'couple_id',
        'name',
        'target_amount',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'financial_project_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FinancialProjectEntry::class, 'financial_project_id');
    }

    /**
     * Progresso: soma despesas ligadas ao projeto − soma receitas ligadas (retiradas).
     */
    public function savedProgress(): float
    {
        $in = (float) Transaction::query()
            ->where('couple_id', $this->couple_id)
            ->where('financial_project_id', $this->id)
            ->where('type', 'expense')
            ->sum('amount');

        $out = (float) Transaction::query()
            ->where('couple_id', $this->couple_id)
            ->where('financial_project_id', $this->id)
            ->where('type', 'income')
            ->sum('amount');

        $interest = (float) FinancialProjectEntry::query()
            ->where('couple_id', $this->couple_id)
            ->where('financial_project_id', $this->id)
            ->where('type', 'interest')
            ->sum('amount');

        return round(($in - $out) + $interest, 2);
    }

    public function remainingToTarget(): ?float
    {
        $target = $this->target_amount !== null ? (float) $this->target_amount : null;
        if ($target === null) {
            return null;
        }

        return max(0.0, round($target - $this->savedProgress(), 2));
    }

    public function progressPct(): ?float
    {
        $target = $this->target_amount !== null ? (float) $this->target_amount : null;
        if ($target === null || $target < 0.00001) {
            return null;
        }

        return min(100.0, round(($this->savedProgress() / $target) * 100.0, 2));
    }
}
