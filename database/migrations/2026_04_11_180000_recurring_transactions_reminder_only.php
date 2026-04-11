<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('recurring_transactions')->update(['generation_mode' => 'reminder']);
    }

    public function down(): void
    {
        //
    }
};
