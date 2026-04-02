<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Contas regulares passam a usar implicitamente todas as formas de PaymentMethods::forRegularAccounts() (null na coluna).
     */
    public function up(): void
    {
        DB::table('accounts')
            ->where('kind', Account::KIND_REGULAR)
            ->update(['allowed_payment_methods' => null]);
    }

    public function down(): void
    {
        // irreversível sem backup dos valores anteriores
    }
};
