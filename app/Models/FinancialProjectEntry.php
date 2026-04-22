<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialProjectEntry extends Model
{
    protected $fillable = [
        'couple_id',
        'user_id',
        'financial_project_id',
        'type',
        'amount',
        'date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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
