<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Log::info('[Backup Schedule] Disparando rotina db:backup-drive pelo agendador...');
    try {
        $exitCode = Artisan::call('db:backup-drive');
        $output = Artisan::output();
        if ($exitCode === 0) {
            Log::info('[Backup Schedule] Rotina db:backup-drive concluída com sucesso.', ['output' => $output]);
        } else {
            Log::error('[Backup Schedule] Rotina db:backup-drive falhou com código: ' . $exitCode, ['output' => $output]);
        }
    } catch (\Throwable $e) {
        Log::error('[Backup Schedule] Exceção ao executar db:backup-drive: ' . $e->getMessage(), [
            'exception' => $e->getMessage(),
        ]);
    }
})->dailyAt('01:12')->name('db:backup-drive');
