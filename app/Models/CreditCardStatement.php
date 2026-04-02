<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardStatement extends Model
{
    protected $fillable = [
        'couple_id',
        'account_id',
        'reference_month',
        'reference_year',
        'due_date',
        'paid_at',
        'payment_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'reference_month' => 'integer',
            'reference_year' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'date',
        ];
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_transaction_id');
    }
}
