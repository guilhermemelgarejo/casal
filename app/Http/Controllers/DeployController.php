<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeployController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredToken = config('app.deploy_token');
        if (empty($configuredToken)) {
            return response()->json(['status' => 'error', 'message' => 'DEPLOY_TOKEN não está configurado.'], 500);
        }
        $providedToken = $request->header('X-Deploy-Token') ?? $request->input('token');
        if (! is_string($providedToken) || ! hash_equals((string) $configuredToken, $providedToken)) {
            Log::warning('Tentativa de acesso não autorizado ao deploy-hook.', ['ip' => $request->ip()]);

            return response()->json([
                'status' => 'error', 'message' => 'Acesso não autorizado: token de deploy inválido.',
            ], 403);
        }

        $started = microtime(true);
        $tasks = [];
        try {
            // Do not flush application cache: it may be stored in the database.
            foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled', 'config:cache', 'route:cache', 'view:cache'] as $command) {
                if (Artisan::call($command) !== 0) {
                    throw new RuntimeException('Falha no comando '.$command.'.');
                }
                $tasks[$command] = ['status' => 'success'];
            }
        } catch (Throwable $e) {
            Log::error('Falha no pós-deploy.', ['exception' => $e->getMessage()]);

            return response()->json([
                'status' => 'error', 'message' => 'O pós-deploy falhou. Consulte o log do servidor.', 'tasks' => $tasks,
            ], 500);
        }

        return response()->json([
            'status' => 'success', 'message' => 'Pós-deploy concluído sem alterações no banco de dados.',
            'duration_seconds' => round(microtime(true) - $started, 3),
            'timestamp' => now()->toIso8601String(), 'tasks' => $tasks,
        ]);
    }
}
