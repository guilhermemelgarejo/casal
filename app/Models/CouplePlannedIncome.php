<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouplePlannedIncome extends Model
{
    protected $table = 'couple_planned_income';

    protected $fillable = [
        'couple_id',
        'effective_from_year',
        'effective_from_month',
        'amount',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from_year' => 'integer',
            'effective_from_month' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /**
     * Valor de renda planejada vigente para o mês civil (y, m): maior effective_from <= (y,m), ou null.
     */
    public static function amountVigenteForMonth(int $coupleId, int $year, int $month): ?float
    {
        $needle = $year * 100 + $month;
        $row = static::query()
            ->where('couple_id', $coupleId)
            ->whereRaw('(effective_from_year * 100 + effective_from_month) <= ?', [$needle])
            ->orderByDesc('effective_from_year')
            ->orderByDesc('effective_from_month')
            ->orderByDesc('id')
            ->first();

        return $row !== null ? (float) $row->amount : null;
    }

    public static function recordVersion(
        int $coupleId,
        int $effectiveYear,
        int $effectiveMonth,
        float $amount,
        ?int $createdByUserId = null
    ): void {
        static::query()->create([
            'couple_id' => $coupleId,
            'effective_from_year' => $effectiveYear,
            'effective_from_month' => $effectiveMonth,
            'amount' => number_format($amount, 2, '.', ''),
            'created_by_user_id' => $createdByUserId,
        ]);
    }
}
