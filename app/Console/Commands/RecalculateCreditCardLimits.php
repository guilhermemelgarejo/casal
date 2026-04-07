<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

class RecalculateCreditCardLimits extends Command
{
    protected $signature = 'accounts:recalc-credit-card-limits';

    protected $description = 'Recalcula credit_card_limit_available em todos os cartões (útil após migração ou reparação)';

    public function handle(): int
    {
        $this->info('A recalcular limites disponíveis dos cartões…');

        $cards = Account::query()->where('kind', Account::KIND_CREDIT_CARD)->orderBy('id')->get();
        foreach ($cards as $card) {
            $card->recalculateCreditCardLimitAvailable();
        }

        $this->info('Concluído ('.$cards->count().' cartões).');

        return self::SUCCESS;
    }
}
