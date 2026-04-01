<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedTinyInteger('reference_month')->nullable()->after('date');
            $table->unsignedSmallInteger('reference_year')->nullable()->after('reference_month');

            $table->index(['couple_id', 'reference_year', 'reference_month'], 'transactions_couple_reference_idx');
        });

        $driver = DB::getDriverName();

        // Backfill para dados existentes: usa o mês/ano da própria data.
        if ($driver === 'sqlite') {
            DB::statement("
                UPDATE transactions
                SET
                    reference_month = CAST(strftime('%m', date) AS INTEGER),
                    reference_year = CAST(strftime('%Y', date) AS INTEGER)
                WHERE reference_month IS NULL OR reference_year IS NULL
            ");
        } else {
            DB::statement("
                UPDATE transactions
                SET
                    reference_month = MONTH(`date`),
                    reference_year = YEAR(`date`)
                WHERE reference_month IS NULL OR reference_year IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_couple_reference_idx');
            $table->dropColumn(['reference_month', 'reference_year']);
        });
    }
};

