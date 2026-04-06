<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

class SyncAccountBalances extends Command
{
    protected $signature = 'accounts:sync-balances';

    protected $description = 'Recalcula accounts.balance das contas correntes a partir dos lançamentos (repara divergências)';

    public function handle(): int
    {
        $this->info('A sincronizar saldos persistidos…');

        $regular = Account::query()->where('kind', Account::KIND_REGULAR)->orderBy('id')->get();

        foreach ($regular as $account) {
            $sums = Account::balancesFromTransactionsByAccountId([$account->id]);
            $target = number_format($sums[$account->id] ?? 0.0, 2, '.', '');
            $account->forceFill(['balance' => $target])->saveQuietly();
        }

        $this->info('Concluído ('.$regular->count().' contas).');

        return self::SUCCESS;
    }
}
