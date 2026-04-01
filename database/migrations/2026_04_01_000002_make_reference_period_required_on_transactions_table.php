<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Garante preenchimento antes de endurecer constraints.
        if ($driver === 'sqlite') {
            DB::statement("
                UPDATE transactions
                SET
                    reference_month = CAST(strftime('%m', date) AS INTEGER),
                    reference_year = CAST(strftime('%Y', date) AS INTEGER)
                WHERE reference_month IS NULL OR reference_year IS NULL
            ");

            // SQLite não suporta ALTER COLUMN NOT NULL facilmente sem rebuild da tabela.
            // Mantemos nullable no schema, mas a aplicação passa a sempre preencher.
            return;
        }

        DB::statement("
            UPDATE transactions
            SET
                reference_month = MONTH(`date`),
                reference_year = YEAR(`date`)
            WHERE reference_month IS NULL OR reference_year IS NULL
        ");

        // MySQL/MariaDB
        DB::statement('ALTER TABLE `transactions` MODIFY `reference_month` TINYINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `transactions` MODIFY `reference_year` SMALLINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE `transactions` MODIFY `reference_month` TINYINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `transactions` MODIFY `reference_year` SMALLINT UNSIGNED NULL');
    }
};

