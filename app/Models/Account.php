<?php

namespace App\Models;

use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    public const KIND_REGULAR = 'regular';

    public const KIND_CREDIT_CARD = 'credit_card';

    protected $fillable = ['couple_id', 'name', 'kind', 'color', 'credit_card_invoice_due_day'];

    protected function casts(): array
    {
        return [
            'credit_card_invoice_due_day' => 'integer',
        ];
    }

    /**
     * Data de vencimento sugerida para o ciclo (mês de referência), usando o dia configurado neste cartão.
     * Mesmo mês civil da referência (ex.: ref. 06/2026 e dia 10 → 10/06/2026).
     */
    public function defaultStatementDueDate(int $referenceMonth, int $referenceYear): ?Carbon
    {
        if (! $this->isCreditCard() || $this->credit_card_invoice_due_day === null) {
            return null;
        }

        $day = (int) $this->credit_card_invoice_due_day;
        if ($day < 1 || $day > 31) {
            return null;
        }

        $tz = config('app.timezone');
        $base = Carbon::create($referenceYear, $referenceMonth, 1, 0, 0, 0, $tz);
        $dom = min($day, $base->daysInMonth);

        return $base->copy()->day($dom)->startOfDay();
    }

    /**
     * Regra antiga de vencimento (mês civil seguinte à referência).
     * Usada só para alinhar faturas já gravadas com a sugestão automática anterior.
     */
    public function legacyDefaultStatementDueDate(int $referenceMonth, int $referenceYear): ?Carbon
    {
        if (! $this->isCreditCard() || $this->credit_card_invoice_due_day === null) {
            return null;
        }

        $day = (int) $this->credit_card_invoice_due_day;
        if ($day < 1 || $day > 31) {
            return null;
        }

        $tz = config('app.timezone');
        $base = Carbon::create($referenceYear, $referenceMonth, 1, 0, 0, 0, $tz);
        $dueMonth = $base->copy()->addMonth();
        $dom = min($day, $dueMonth->daysInMonth);

        return $dueMonth->copy()->day($dom)->startOfDay();
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
     * Formas de pagamento para lançamentos em conta (não-cartão): lista canónica.
     * Cartões de crédito não têm forma extra: o próprio cartão identifica o meio.
     *
     * @return list<string>
     */
    public function getEffectivePaymentMethods(): array
    {
        if ($this->isCreditCard()) {
            return [];
        }

        return PaymentMethods::forRegularAccounts();
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

    public function creditCardStatements()
    {
        return $this->hasMany(CreditCardStatement::class, 'account_id');
    }
}
