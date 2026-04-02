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
     * Formas permitidas para lançamentos em conta (não-cartão). null = todas as formas de conta.
     * Cartões de crédito não têm "forma de pagamento" extra: o próprio cartão identifica o meio.
     *
     * @return list<string>
     */
    public function getEffectivePaymentMethods(): array
    {
        if ($this->isCreditCard()) {
            return [];
        }

        $pool = PaymentMethods::forRegularAccounts();
        $allowed = $this->allowed_payment_methods;

        if ($allowed === null) {
            return $pool;
        }

        return array_values(array_intersect($pool, $allowed));
    }

    public function allowsPaymentMethod(?string $method): bool
    {
        if ($this->isCreditCard()) {
            return $method === null || $method === '';
        }

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
