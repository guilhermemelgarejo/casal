<?php

namespace App\Models;

use App\Support\PaymentMethods;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    protected $fillable = ['couple_id', 'name', 'color', 'allowed_payment_methods'];

    protected function casts(): array
    {
        return [
            'allowed_payment_methods' => 'array',
        ];
    }

    /**
     * Formas permitidas para lançamentos. null = todas (legado / padrão).
     *
     * @return list<string>
     */
    public function getEffectivePaymentMethods(): array
    {
        $all = PaymentMethods::all();
        $allowed = $this->allowed_payment_methods;
        if ($allowed === null) {
            return $all;
        }

        return array_values(array_intersect($all, $allowed));
    }

    public function allowsPaymentMethod(?string $method): bool
    {
        if ($method === null || $method === '') {
            return true;
        }

        return in_array($method, $this->getEffectivePaymentMethods(), true);
    }

    public function couple()
    {
        return $this->belongsTo(Couple::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }
}
