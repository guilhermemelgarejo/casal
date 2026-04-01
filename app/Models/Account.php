<?php

namespace App\Models;

use App\Support\PaymentMethods;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    public const KIND_REGULAR = 'regular';
    public const KIND_CREDIT_CARD = 'credit_card';

    protected $fillable = ['couple_id', 'name', 'kind', 'color', 'allowed_payment_methods'];

    protected function casts(): array
    {
        return [
            'allowed_payment_methods' => 'array',
        ];
    }

    public static function kinds(): array
    {
        return [
            self::KIND_REGULAR,
            self::KIND_CREDIT_CARD,
        ];
    }

    public function isCreditCard(): bool
    {
        return $this->kind === self::KIND_CREDIT_CARD;
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

        // Conta tipada como cartão de crédito: deve aceitar somente crédito,
        // independentemente do que esteja salvo em allowed_payment_methods.
        if ($this->isCreditCard()) {
            return ['Cartão de Crédito'];
        }

        if ($allowed === null) {
            return array_values(array_filter($all, fn ($m) => $m !== 'Cartão de Crédito'));
        }

        $effective = array_values(array_intersect($all, $allowed));
        return array_values(array_filter($effective, fn ($m) => $m !== 'Cartão de Crédito'));
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
