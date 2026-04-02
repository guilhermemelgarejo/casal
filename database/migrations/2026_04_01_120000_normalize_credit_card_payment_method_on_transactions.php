<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove o rótulo legado "Cartão de Crédito" de payment_method: o cartão fica só em account_id + kind.
     * Não apaga utilizadores nem outras tabelas.
     */
    public function up(): void
    {
        DB::table('transactions')
            ->where('payment_method', 'Cartão de Crédito')
            ->update(['payment_method' => null]);

        DB::table('accounts')
            ->where('kind', 'credit_card')
            ->update(['allowed_payment_methods' => null]);
    }

    public function down(): void
    {
        // Não restauramos payment_method por transação (perderíamos a distinção cartão vs conta).
    }
};
