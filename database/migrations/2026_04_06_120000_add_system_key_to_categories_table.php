<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('system_key', 64)->nullable()->after('icon');
        });

        $legacyNames = [
            'Pagamento fatura cartão',
            'Pagamento de fatura de cartão',
        ];

        $coupleIds = DB::table('categories')->distinct()->pluck('couple_id');
        foreach ($coupleIds as $coupleId) {
            $firstId = DB::table('categories')
                ->where('couple_id', $coupleId)
                ->whereIn('name', $legacyNames)
                ->whereNull('system_key')
                ->orderBy('id')
                ->value('id');
            if ($firstId !== null) {
                DB::table('categories')
                    ->where('id', $firstId)
                    ->update(['system_key' => 'credit_card_invoice_payment']);
            }
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['couple_id', 'system_key']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['couple_id', 'system_key']);
        });

        DB::table('categories')
            ->where('system_key', 'credit_card_invoice_payment')
            ->update(['system_key' => null]);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('system_key');
        });
    }
};
