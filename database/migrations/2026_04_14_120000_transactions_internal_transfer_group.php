<?php

use App\Models\Category;
use App\Models\Couple;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('internal_transfer_group_id', 36)->nullable()->after('recurring_transaction_id');
            $table->index('internal_transfer_group_id');
        });

        foreach (Couple::query()->cursor() as $couple) {
            Category::ensureInternalTransferCategoriesForCouple((int) $couple->id);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['internal_transfer_group_id']);
            $table->dropColumn('internal_transfer_group_id');
        });
    }
};
