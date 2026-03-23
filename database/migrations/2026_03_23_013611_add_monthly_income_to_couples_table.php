<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->decimal('monthly_income', 15, 2)->default(0)->after('invite_code');
        });
    }

    public function down(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->dropColumn('monthly_income');
        });
    }
};
