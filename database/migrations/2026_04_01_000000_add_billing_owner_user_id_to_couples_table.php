<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->foreignId('billing_owner_user_id')
                ->nullable()
                ->after('invite_code')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->dropForeign(['billing_owner_user_id']);
            $table->dropColumn('billing_owner_user_id');
        });
    }
};

