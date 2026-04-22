<?php

namespace App\Providers;

use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
}
