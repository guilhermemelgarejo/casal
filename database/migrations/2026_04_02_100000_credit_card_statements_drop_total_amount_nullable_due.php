<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'due_date']);
        });

        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->date('due_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });

        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->after('reference_year');
            $table->date('due_date')->after('total_amount');
        });
    }
};
