<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bases que já tinham corrido o schema antes da coluna `balance`: adiciona e fica a 0 até sincronizar (ex.: `php artisan accounts:sync-balances`).
     */
    public function up(): void
    {
        if (Schema::hasColumn('accounts', 'balance')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('credit_card_invoice_due_day');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('accounts', 'balance')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
