<?php

namespace App\Models;

use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            'balance' => 'decimal:2',
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

    /**
     * Aplica ou reverte o efeito de um lançamento no saldo persistido (`accounts.balance`).
     * Só contas `regular`; cartões ignorados. Não está em `$fillable` — não exposto em formulários.
     */
    public static function applyLedgerEffectToStoredBalance(
        ?int $accountId,
        int $coupleId,
        ?string $type,
        mixed $amount,
        bool $reverse = false
    ): void {
        if ($accountId === null || ! in_array($type, ['income', 'expense'], true)) {
            return;
        }

        $normalized = str_replace(',', '.', (string) $amount);
        if (! is_numeric($normalized)) {
            return;
        }

        $amountStr = number_format((float) $normalized, 2, '.', '');
        $delta = $type === 'income' ? $amountStr : bcsub('0', $amountStr, 2);
        if ($reverse) {
            $delta = bcsub('0', $delta, 2);
        }

        DB::transaction(function () use ($accountId, $coupleId, $delta) {
            $account = self::query()
                ->whereKey($accountId)
                ->where('couple_id', $coupleId)
                ->where('kind', self::KIND_REGULAR)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                return;
            }

            $current = is_string($account->balance)
                ? $account->balance
                : number_format((float) $account->balance, 2, '.', '');
            $newBalance = bcadd($current, $delta, 2);
            $account->forceFill(['balance' => $newBalance])->saveQuietly();
        });
    }

    /**
     * Soma derivada dos lançamentos (receita − despesa) por conta. Usado por `accounts:sync-balances`
     * e testes; o valor de exibição em tempo real vem de `accounts.balance`.
     *
     * @param  iterable<int|string>  $accountIds
     * @return array<int, float> id da conta => saldo
     */
    public static function balancesFromTransactionsByAccountId(iterable $accountIds): array
    {
        /** @var Collection<int, int> $ids */
        $ids = collect($accountIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $rows = Transaction::query()
            ->whereIn('account_id', $ids->all())
            ->groupBy('account_id')
            ->selectRaw("account_id, SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as bal")
            ->get()
            ->keyBy(fn ($row) => (int) $row->account_id);

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = (float) ($rows->get($id)?->bal ?? 0);
        }

        return $out;
    }
}
