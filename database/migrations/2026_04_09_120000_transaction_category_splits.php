<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_category_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index('transaction_id', 'tcs_transaction_idx');
        });

        DB::table('transactions')->orderBy('id')->chunkById(500, function ($rows) {
            $now = now();
            $inserts = [];
            foreach ($rows as $row) {
                $inserts[] = [
                    'transaction_id' => $row->id,
                    'category_id' => $row->category_id,
                    'amount' => $row->amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($inserts !== []) {
                DB::table('transaction_category_splits')->insert($inserts);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_category_splits');
    }
};
