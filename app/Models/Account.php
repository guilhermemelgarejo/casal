<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    protected $fillable = ['couple_id', 'name', 'color'];

    public function couple()
    {
        return $this->belongsTo(Couple::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }
}
