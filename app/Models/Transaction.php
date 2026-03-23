<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['couple_id', 'user_id', 'category_id', 'account_id', 'description', 'amount', 'payment_method', 'account', 'type', 'date'];

    protected $casts = [
        'date' => 'date',
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
}
