<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'couple_id',
        'user_id',
        'category_id',
        'account_id',
        'description',
        'amount',
        'payment_method',
        'account',
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
}
