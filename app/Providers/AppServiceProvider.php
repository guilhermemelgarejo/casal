<?php

namespace App\Providers;

use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
use App\Support\BackupRetention;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

        Event::listen(Login::class, function (Login $event): void {
            Log::channel('access')->info('Login', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip' => request()->ip(),
                'at' => now()->toIso8601String(),
            ]);
        });

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

        // Identificação do fornecedor nas páginas legais; campos vazios ficam destacados como pendentes.
        View::composer(['legal.terms', 'legal.privacy'], function ($view) {
            $legal = config('duozen.legal', []);
            $field = fn (?string $value, string $label) => filled($value)
                ? e($value)
                : '<mark class="lp-legal__pending">[a preencher: '.e($label).']</mark>';

            $view->with([
                'providerName' => $field($legal['provider_name'] ?? null, 'nome empresarial ou nome completo'),
                'providerDocument' => $field($legal['provider_document'] ?? null, 'CNPJ ou CPF'),
                'providerAddress' => $field($legal['provider_address'] ?? null, 'endereço'),
                'contactEmail' => filled($legal['contact_email'] ?? null)
                    ? '<a href="mailto:'.e($legal['contact_email']).'">'.e($legal['contact_email']).'</a>'
                    : $field(null, 'e-mail de atendimento'),
                'backupRetention' => $field(
                    BackupRetention::days() !== null ? BackupRetention::days().' dias' : null,
                    'prazo de guarda dos backups'
                ),
            ]);
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
