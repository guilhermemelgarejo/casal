<?php

namespace App\Providers;

use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $this->registerDatabaseSafetyGuards();

        Route::bind('cofrinho', function (string $value) {
            $coupleId = Auth::user()?->couple_id;
            if (! $coupleId) {
                abort(403);
            }

            return FinancialProject::query()
                ->where('couple_id', $coupleId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('entry', function (string $value) {
            $coupleId = Auth::user()?->couple_id;
            if (! $coupleId) {
                abort(403);
            }

            return FinancialProjectEntry::query()
                ->where('couple_id', $coupleId)
                ->whereKey($value)
                ->firstOrFail();
        });

        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            $show = false;
            if ($user && $user->couple_id && session()->get('duozen_onboarding_tour') && $user->passesCoupleBillingGate()) {
                $show = true;
            }
            $view->with('showOnboardingTour', $show);
        });
    }

    private function registerDatabaseSafetyGuards(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $command = (string) $event->command;

            if (in_array($command, ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'], true)) {
                $this->guardAgainstDestructiveDatabaseCommand($command);
            }

            if ($command === 'migrate') {
                $this->guardAgainstRunningBaselineOnExistingDatabase();
            }
        });
    }

    private function guardAgainstDestructiveDatabaseCommand(string $command): void
    {
        if ((bool) env('DUOZEN_ALLOW_DESTRUCTIVE_DB_COMMANDS', false)) {
            return;
        }

        if ($this->isDisposableTestingDatabase()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Comando "%s" bloqueado: ele pode apagar dados. Use somente em banco descartavel ou defina DUOZEN_ALLOW_DESTRUCTIVE_DB_COMMANDS=true conscientemente.',
            $command
        ));
    }

    private function guardAgainstRunningBaselineOnExistingDatabase(): void
    {
        if ((bool) env('DUOZEN_ALLOW_LEGACY_BASELINE_MIGRATION', false)) {
            return;
        }

        if (! Schema::hasTable('couples') || ! Schema::hasTable('migrations')) {
            return;
        }

        $baselineAlreadyRan = DB::table('migrations')
            ->where('migration', '2026_04_23_000000_initial_schema')
            ->exists();

        if ($baselineAlreadyRan) {
            return;
        }

        throw new RuntimeException(
            'Migration baseline pendente em um banco existente. Para preservar dados, marque 2026_04_23_000000_initial_schema como executada antes de rodar migrate.'
        );
    }

    private function isDisposableTestingDatabase(): bool
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}", []);

        return ($config['driver'] ?? null) === 'sqlite'
            && ($config['database'] ?? null) === ':memory:';
    }
}
