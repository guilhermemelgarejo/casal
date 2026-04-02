<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Remove "Outros" das listas em conta; não altera users nem apaga lançamentos.
     */
    public function up(): void
    {
        Account::query()
            ->whereNotNull('allowed_payment_methods')
            ->where('kind', Account::KIND_REGULAR)
            ->each(function (Account $account) {
                $methods = $account->allowed_payment_methods;
                if (! is_array($methods)) {
                    return;
                }
                $filtered = array_values(array_filter(
                    $methods,
                    fn ($m) => $m !== 'Outros'
                ));
                $account->allowed_payment_methods = count($filtered) > 0 ? $filtered : null;
                $account->save();
            });
    }

    public function down(): void
    {
        // Sem reverter valores removidos.
    }
};
