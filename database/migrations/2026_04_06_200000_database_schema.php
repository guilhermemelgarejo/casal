<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couples', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('invite_code')->unique();
            $table->decimal('monthly_income', 15, 2)->default(0);
            $table->decimal('spending_alert_threshold', 5, 2)->default(80);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->foreignId('couple_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::table('couples', function (Blueprint $table) {
            $table->foreignId('billing_owner_user_id')
                ->nullable()
                ->after('invite_code')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'stripe_status']);
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->string('meter_id')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('meter_event_name')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'stripe_price']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->string('system_key', 64)->nullable();
            $table->timestamps();

            $table->unique(['couple_id', 'system_key']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('regular');
            $table->string('color')->default('#4f46e5');
            $table->unsignedTinyInteger('credit_card_invoice_due_day')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->nullable();
            $table->string('type');
            $table->date('date');
            $table->unsignedTinyInteger('reference_month');
            $table->unsignedSmallInteger('reference_year');
            $table->foreignId('installment_parent_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['couple_id', 'reference_year', 'reference_month'], 'transactions_couple_reference_idx');
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->integer('month');
            $table->integer('year');
            $table->timestamps();
        });

        Schema::create('credit_card_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->unsignedTinyInteger('reference_month');
            $table->unsignedSmallInteger('reference_year');
            $table->decimal('spent_total', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'reference_month', 'reference_year'], 'ccs_account_ref_unique');
        });

        Schema::create('credit_card_statement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_statement_id')->constrained('credit_card_statements')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('transaction_id', 'ccsp_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('credit_card_statement_payments');
        Schema::dropIfExists('credit_card_statements');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');

        Schema::table('couples', function (Blueprint $table) {
            $table->dropForeign(['billing_owner_user_id']);
            $table->dropColumn('billing_owner_user_id');
        });

        Schema::dropIfExists('users');
        Schema::dropIfExists('couples');

        Schema::enableForeignKeyConstraints();
    }
};
