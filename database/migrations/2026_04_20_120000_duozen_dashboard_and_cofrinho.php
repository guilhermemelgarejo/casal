<?php

use App\Models\Category;
use App\Models\Couple;
use App\Models\CouplePlannedIncome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('couple_planned_income')) {
            Schema::drop('couple_planned_income');
        }
        if (Schema::hasTable('financial_projects')) {
            Schema::drop('financial_projects');
        }
        if (Schema::hasColumn('transactions', 'financial_project_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('financial_project_id');
            });
        }
        if (Schema::hasColumn('users', 'dashboard_widget_prefs')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('dashboard_widget_prefs');
            });
        }

        Schema::create('couple_planned_income', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('effective_from_year');
            $table->unsignedTinyInteger('effective_from_month');
            $table->decimal('amount', 12, 2);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['couple_id', 'effective_from_year', 'effective_from_month'], 'cpi_couple_effective_idx');
        });

        Schema::create('financial_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('target_amount', 12, 2)->nullable();
            $table->string('color', 32)->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('financial_project_id')->nullable()->after('internal_transfer_group_id')
                ->constrained('financial_projects')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard_widget_prefs')->nullable()->after('remember_token');
        });

        foreach (Couple::query()->cursor() as $couple) {
            Category::ensureSavingsCategoriesForCouple((int) $couple->id);
            $mi = $couple->monthly_income;
            if ($mi !== null && (float) $mi > 0) {
                $now = now();
                CouplePlannedIncome::query()->create([
                    'couple_id' => $couple->id,
                    'effective_from_year' => (int) $now->year,
                    'effective_from_month' => (int) $now->month,
                    'amount' => $mi,
                    'created_by_user_id' => null,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_widget_prefs');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_project_id');
        });

        Schema::dropIfExists('financial_projects');
        Schema::dropIfExists('couple_planned_income');
    }
};
