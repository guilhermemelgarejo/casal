<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialProjectEntry extends Model
{
    public const TYPE_INTEREST = 'interest';
    public const TYPE_ASSET_APORTE = 'asset_aporte';
    public const TYPE_ASSET_WITHDRAWAL = 'asset_withdrawal';
    public const TYPE_ASSET_ADJUSTMENT = 'asset_adjustment';

    protected $fillable = [
        'couple_id',
        'user_id',
        'financial_project_id',
        'type',
        'amount',
        'asset_quantity',
        'asset_unit_price',
        'asset_resulting_avg_price',
        'date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'asset_quantity' => 'decimal:8',
            'asset_unit_price' => 'decimal:4',
            'asset_resulting_avg_price' => 'decimal:4',
            'date' => 'date',
        ];
    }

    public function financialProject(): BelongsTo
    {
        return $this->belongsTo(FinancialProject::class, 'financial_project_id');
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
