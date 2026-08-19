<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class DeployController extends Controller
{
    /**
     * Handle the deployment hook request.
     */
    public function __invoke(Request $request, ?string $token = null): JsonResponse
    {
        $configuredToken = config('app.deploy_token') ?? env('DEPLOY_TOKEN');

        if (empty($configuredToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'DEPLOY_TOKEN não está configurado no arquivo .env do servidor.',
            ], 500);
        }

        $providedToken = $token
            ?? $request->header('X-Deploy-Token')
            ?? $request->input('token')
            ?? $request->query('token');

        if (!$providedToken || !hash_equals((string) $configuredToken, (string) $providedToken)) {
            Log::warning('Tentativa de acesso não autorizado ao deploy-hook.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado: token de deploy inválido.',
            ], 403);
        }

        $startTime = microtime(true);
        $logs = [];

        // 1. Executar migrações do banco de dados
        try {
            Artisan::call('migrate', ['--force' => true]);
            $logs['migrate'] = [
                'status' => 'success',
                'output' => trim(Artisan::output()),
            ];
        } catch (\Throwable $e) {
            $logs['migrate'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // 2. Criar symlink do storage se não existir
        try {
            $storagePublicPath = public_path('storage');
            $storageTargetPath = storage_path('app/public');
            if (!file_exists($storagePublicPath) && !is_link($storagePublicPath) && file_exists($storageTargetPath)) {
                @symlink($storageTargetPath, $storagePublicPath);
                $logs['storage_link'] = [
                    'status' => 'created',
                    'output' => 'Symlink do storage criado com sucesso via PHP nativo.',
                ];
            } else {
                $logs['storage_link'] = [
                    'status' => 'already_exists',
                    'output' => 'Symlink do storage já existe.',
                ];
            }
        } catch (\Throwable $e) {
            $logs['storage_link'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // 3. Limpar caches antigos
        try {
            Artisan::call('optimize:clear');
            $logs['optimize_clear'] = [
                'status' => 'success',
                'output' => trim(Artisan::output()),
            ];
        } catch (\Throwable $e) {
            $logs['optimize_clear'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // 4. Recriar caches em produção (config, rotas, views)
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            $logs['cache_optimize'] = [
                'status' => 'success',
                'output' => 'Configurações, rotas e views cacheadas com sucesso.',
            ];
        } catch (\Throwable $e) {
            $logs['cache_optimize'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        $duration = round(microtime(true) - $startTime, 3);

        Log::info('Deploy hook executado com sucesso.', [
            'duration_seconds' => $duration,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Processo de pós-deploy concluído com sucesso.',
            'duration_seconds' => $duration,
            'timestamp' => now()->toIso8601String(),
            'tasks' => $logs,
        ]);
    }
}
