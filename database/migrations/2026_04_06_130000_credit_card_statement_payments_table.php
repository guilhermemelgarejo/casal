<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_statement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_statement_id')->constrained('credit_card_statements')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('transaction_id', 'ccsp_transaction_unique');
        });

        $now = now();
        $rows = DB::table('credit_card_statements')
            ->whereNotNull('payment_transaction_id')
            ->get(['id', 'payment_transaction_id']);

        foreach ($rows as $row) {
            DB::table('credit_card_statement_payments')->insert([
                'credit_card_statement_id' => $row->id,
                'transaction_id' => $row->payment_transaction_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->dropForeign(['payment_transaction_id']);
            $table->dropUnique('ccs_payment_tx_unique');
            $table->dropColumn('payment_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->foreignId('payment_transaction_id')->nullable()->after('paid_at')->constrained('transactions')->nullOnDelete();
            $table->unique('payment_transaction_id', 'ccs_payment_tx_unique');
        });

        $rows = DB::table('credit_card_statement_payments')->get(['credit_card_statement_id', 'transaction_id']);
        foreach ($rows as $row) {
            DB::table('credit_card_statements')
                ->where('id', $row->credit_card_statement_id)
                ->whereNull('payment_transaction_id')
                ->update(['payment_transaction_id' => $row->transaction_id]);
        }

        Schema::dropIfExists('credit_card_statement_payments');
    }
};
