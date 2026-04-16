<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('refund_of_transaction_id')
                ->nullable()
                ->after('installment_parent_id')
                ->constrained('transactions')
                ->nullOnDelete();

            $table->index('refund_of_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['refund_of_transaction_id']);
            $table->dropIndex(['refund_of_transaction_id']);
            $table->dropColumn('refund_of_transaction_id');
        });
    }
};

