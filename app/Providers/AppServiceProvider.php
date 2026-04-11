<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
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
