<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->boolean('is_multiple')->default(false)->after('generation_mode');
            $table->unsignedTinyInteger('day_of_month')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropColumn('is_multiple');
            $table->unsignedTinyInteger('day_of_month')->nullable(false)->change();
        });
    }
};
