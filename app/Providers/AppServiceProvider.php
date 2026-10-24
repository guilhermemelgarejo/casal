<?php

namespace App\Providers;

use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
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

            if ($command === 'migrate' || str_starts_with($command, 'migrate:') || $command === 'db:wipe') {
                throw new RuntimeException('Migrations e alterações automáticas de estrutura são proibidas neste projeto.');
            }
        });
    }
}
