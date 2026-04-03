<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->decimal('spent_total', 15, 2)->default(0)->after('reference_year');
        });

        $statements = DB::table('credit_card_statements')->get();
        foreach ($statements as $s) {
            $sum = DB::table('transactions')
                ->where('couple_id', $s->couple_id)
                ->where('account_id', $s->account_id)
                ->where('reference_month', $s->reference_month)
                ->where('reference_year', $s->reference_year)
                ->where('type', 'expense')
                ->sum('amount');

            DB::table('credit_card_statements')
                ->where('id', $s->id)
                ->update(['spent_total' => number_format((float) $sum, 2, '.', '')]);
        }
    }

    public function down(): void
    {
        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->dropColumn('spent_total');
        });
    }
};
