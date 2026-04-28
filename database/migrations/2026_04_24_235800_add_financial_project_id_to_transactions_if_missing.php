<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('transactions')
            || ! Schema::hasTable('financial_projects')
            || Schema::hasColumn('transactions', 'financial_project_id')
        ) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('financial_project_id')
                ->nullable()
                ->after('internal_transfer_group_id')
                ->constrained('financial_projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // A baseline atual já possui esta coluna; não removemos aqui para evitar
        // apagar uma coluna criada pela instalação inicial em rollbacks locais.
    }
};
