<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->unsignedTinyInteger('reference_month');
            $table->unsignedSmallInteger('reference_year');
            $table->decimal('total_amount', 15, 2);
            $table->date('due_date');
            $table->date('paid_at')->nullable();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'reference_month', 'reference_year'], 'ccs_account_ref_unique');
            $table->unique('payment_transaction_id', 'ccs_payment_tx_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_statements');
    }
};
