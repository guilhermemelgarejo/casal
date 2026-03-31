<?php

use App\Http\Middleware\EnsureCasalAdmin;
use App\Http\Middleware\EnsureCoupleBillingActive;
use App\Http\Middleware\EnsureHasCouple;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'has-couple' => EnsureHasCouple::class,
            'couple-billing' => EnsureCoupleBillingActive::class,
            'duozen-admin' => EnsureCasalAdmin::class,
            'casal-admin' => EnsureCasalAdmin::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'stripe/*',
        ]);

        $middleware->redirectUsersTo(fn () => route('dashboard', absolute: false));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
