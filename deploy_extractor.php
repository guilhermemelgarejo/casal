<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

/**
 * DuoZen Auto Deploy & Release Extractor
 * Descompacta release.zip, limpa arquivos temporários, limpa caches sem alterar o banco de dados.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

// 1. Ler o DEPLOY_TOKEN do .env (suporta espaços antes e depois do igual)
$envFile = __DIR__.'/.env';
$configuredToken = null;

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#')) {
            continue;
        }
        if (preg_match('/^DEPLOY_TOKEN\s*=\s*(.*)$/i', $line, $matches)) {
            $configuredToken = trim($matches[1], " \"'\t\n\r\0\x0B");
            break;
        }
    }
}

// 2. Obter token fornecido (com prioridade no header para preservar caracteres especiais como + e &)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$headerToken = $headers['X-Deploy-Token']
    ?? $headers['x-deploy-token']
    ?? $headers['X-DEPLOY-TOKEN']
    ?? $_SERVER['HTTP_X_DEPLOY_TOKEN']
    ?? null;

$providedToken = $headerToken ?? $_POST['token'] ?? null;
$providedToken = is_string($providedToken) ? trim($providedToken, " \"'\t\n\r\0\x0B") : null;

if (empty($configuredToken)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'DEPLOY_TOKEN não encontrado ou vazio no arquivo .env da hospedagem.',
    ]);
    exit;
}

if (! $providedToken || ! hash_equals((string) $configuredToken, (string) $providedToken)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Acesso não autorizado: o token enviado pelo GitHub não é igual ao DEPLOY_TOKEN do .env.',
    ]);
    exit;
}

$startTime = microtime(true);
$logs = [];
$zipPath = __DIR__.'/release.zip';
$phase = 'preflight';
$deployId = bin2hex(random_bytes(6));

// Report a safe stage/reference even if the shared host kills PHP with a fatal
// error. Full diagnostics stay in the hosting PHP log, never in public CI logs.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (function_exists('set_time_limit')) {
    @set_time_limit(150);
}
register_shutdown_function(function () use (&$phase, $deployId): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[DuoZen deploy '.$deployId.'] '.$phase.': '.$error['message']);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Falha na etapa '.$phase.'. Consulte o log PHP da hospedagem com a referência '.$deployId.'.',
            'logs' => ['stage' => $phase, 'reference' => $deployId, 'fatal_type' => $error['type']],
        ]);
    }
});

if (PHP_VERSION_ID < 80400) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Configure PHP 8.4 ou superior antes de publicar esta versão. Nenhum arquivo foi extraído.']);
    exit;
}

if (! class_exists(ZipArchive::class)) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Ative a extensão ZIP do PHP antes de publicar. Nenhum arquivo foi extraído.']);
    exit;
}

if (! is_file($zipPath)) {
    http_response_code(409);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'O pacote release.zip não está disponível para esta publicação.']);
    exit;
}

$deployLock = fopen(__DIR__.'/storage/framework/deploy.lock', 'c+');
if ($deployLock === false || ! flock($deployLock, LOCK_EX | LOCK_NB)) {
    http_response_code(409);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Já existe um deploy em andamento.']);
    exit;
}

$phase = 'verify_checksum';
$expectedHash = strtolower(trim((string) ($_SERVER['HTTP_X_RELEASE_SHA256'] ?? '')));
if (! preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Informe o SHA-256 do pacote. Nenhum arquivo foi extraído.']);
    exit;
}
$actualHash = hash_file('sha256', $zipPath);
if (! is_string($actualHash) || ! hash_equals($expectedHash, $actualHash)) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'A integridade do pacote não confere. Nenhum arquivo foi extraído.']);
    exit;
}

// 3. Extrair release.zip
$phase = 'extract';
if (file_exists($zipPath)) {
    $zip = new ZipArchive;
    $res = $zip->open($zipPath, ZipArchive::CHECKCONS);
    if ($res === true) {
        if (! $zip->extractTo(__DIR__)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Falha ao extrair release.zip.']);
            exit;
        }
        $zip->close();
        @unlink($zipPath);
        $logs['unzip'] = 'release.zip descompactado e removido com sucesso.';
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Falha ao abrir release.zip.']);
        exit;
    }
} else {
    $logs['unzip'] = 'Nenhum release.zip pendente de extração.';
}

// 4. Limpeza forçada de arquivos residuais e temporários
if (file_exists($zipPath)) {
    @unlink($zipPath);
}

$ftpSyncFile = __DIR__.'/.ftp-deploy-sync-state.json';
if (file_exists($ftpSyncFile)) {
    @unlink($ftpSyncFile);
    $logs['cleanup_sync_state'] = 'Arquivo .ftp-deploy-sync-state.json removido.';
}

// 5. Limpar caches compilados antigos de views e bootstrap
$phase = 'clear_compiled_files';
$oldViews = glob(__DIR__.'/storage/framework/views/*.php');
if ($oldViews) {
    foreach ($oldViews as $viewFile) {
        @unlink($viewFile);
    }
    $logs['clear_compiled_views'] = count($oldViews).' views compiladas antigas removidas.';
}

$oldCache = glob(__DIR__.'/bootstrap/cache/*.php');
if ($oldCache) {
    foreach ($oldCache as $cacheFile) {
        if (basename($cacheFile) !== '.gitignore') {
            @unlink($cacheFile);
        }
    }
    $logs['clear_bootstrap_cache'] = 'Bootstrap cache antigo limpo.';
}

// 6. Criar symlink do storage via PHP nativo
$storageTarget = __DIR__.'/storage/app/public';
$storageLink = __DIR__.'/public/storage';
if (! file_exists($storageLink) && ! is_link($storageLink) && file_exists($storageTarget)) {
    // Shared hosting may disable symlink(). Do not fail an otherwise completed
    // release, or copy user uploads on every deploy to work around this limit.
    if (! function_exists('symlink')) {
        $logs['storage_link_warning'] = 'A hospedagem não permite criar links simbólicos. Configure o acesso ao disco público no painel caso a aplicação utilize arquivos de storage/app/public.';
    } else {
        try {
            if (@symlink($storageTarget, $storageLink)) {
                $logs['storage_link'] = 'Symlink do storage criado com sucesso.';
            } else {
                $logs['storage_link_warning'] = 'O link do disco público não pôde ser criado. Verifique as permissões da hospedagem se utilizar arquivos de storage/app/public.';
            }
        } catch (Throwable $e) {
            error_log('[DuoZen deploy '.$deployId.'] storage_link: '.$e->getMessage());
            $logs['storage_link_warning'] = ['message' => 'Link do disco público indisponível nesta hospedagem.', 'exception' => get_class($e), 'reference' => $deployId];
        }
    }
}

// 7. Inicializar Laravel e executar comandos Artisan
$phase = 'bootstrap';
try {
    if (file_exists(__DIR__.'/vendor/autoload.php') && file_exists(__DIR__.'/bootstrap/app.php')) {
        require __DIR__.'/vendor/autoload.php';
        /** @var Application $app */
        $app = require_once __DIR__.'/bootstrap/app.php';

        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();

        // Limpeza e recriação de caches em produção
        try {
            // Não limpar o cache da aplicação: ele pode estar no banco de dados.
            foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled', 'config:cache', 'route:cache', 'view:cache'] as $command) {
                $phase = 'cache:'.$command;
                if (Artisan::call($command) !== 0) {
                    throw new RuntimeException('Falha no comando '.$command.'.');
                }
            }
            $logs['cache'] = 'Caches de configuração, rotas e views gerados com sucesso.';
        } catch (Throwable $e) {
            error_log('[DuoZen deploy '.$deployId.'] '.$phase.': '.$e->getMessage());
            $logs['cache_error'] = ['command' => $command, 'exception' => get_class($e), 'reference' => $deployId];
        }
    } else {
        throw new RuntimeException('Arquivos essenciais do Laravel ausentes após a extração.');
    }
} catch (Throwable $e) {
    error_log('[DuoZen deploy '.$deployId.'] '.$phase.': '.$e->getMessage());
    $logs['laravel_init_error'] = ['exception' => get_class($e), 'reference' => $deployId];
}

$duration = round(microtime(true) - $startTime, 3);
$hasErrors = count(array_filter(array_keys($logs), fn ($key) => str_ends_with($key, '_error'))) > 0;
http_response_code($hasErrors ? 500 : 200);

header('Content-Type: application/json');
echo json_encode([
    'status' => $hasErrors ? 'error' : 'success',
    'message' => $hasErrors ? 'O deploy falhou. Consulte os logs.' : 'Deploy concluído sem alterações no banco de dados.',
    'duration_seconds' => $duration,
    'timestamp' => date('c'),
    'logs' => $logs,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
