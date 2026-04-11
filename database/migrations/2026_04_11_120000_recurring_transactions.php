<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('type');
            $table->string('funding');
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('payment_method')->nullable();
            $table->string('generation_mode');
            $table->unsignedTinyInteger('day_of_month');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['couple_id', 'is_active']);
        });

        Schema::create('recurring_transaction_category_splits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recurring_transaction_id');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('recurring_transaction_id', 'rt_cat_splits_rt_id_fk')
                ->references('id')
                ->on('recurring_transactions')
                ->cascadeOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('recurring_transaction_id')
                ->nullable()
                ->after('installment_parent_id');
            $table->foreign('recurring_transaction_id', 'transactions_rt_recurring_fk')
                ->references('id')
                ->on('recurring_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('transactions_rt_recurring_fk');
            $table->dropColumn('recurring_transaction_id');
        });

        Schema::dropIfExists('recurring_transaction_category_splits');
        Schema::dropIfExists('recurring_transactions');
    }
};
