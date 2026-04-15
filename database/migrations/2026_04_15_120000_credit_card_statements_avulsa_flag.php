<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->boolean('is_avulsa')
                ->default(false)
                ->after('paid_at')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->dropIndex(['is_avulsa']);
            $table->dropColumn('is_avulsa');
        });
    }
};

