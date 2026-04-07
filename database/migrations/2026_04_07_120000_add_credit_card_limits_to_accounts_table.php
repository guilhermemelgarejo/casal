<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bases criadas antes das colunas de limite de cartão.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('accounts', 'credit_card_limit_total')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->decimal('credit_card_limit_total', 15, 2)->nullable()->after('balance');
            });
        }

        if (! Schema::hasColumn('accounts', 'credit_card_limit_available')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->decimal('credit_card_limit_available', 15, 2)->nullable()->after('credit_card_limit_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounts', 'credit_card_limit_available')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('credit_card_limit_available');
            });
        }

        if (Schema::hasColumn('accounts', 'credit_card_limit_total')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('credit_card_limit_total');
            });
        }
    }
};
