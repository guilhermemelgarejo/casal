<?php

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
$envFile = __DIR__ . '/.env';
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

if (!$providedToken || !hash_equals((string) $configuredToken, (string) $providedToken)) {
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
$zipPath = __DIR__ . '/release.zip';

if (PHP_VERSION_ID < 80400) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Configure PHP 8.4 ou superior antes de publicar esta versão. Nenhum arquivo foi extraído.']);
    exit;
}

$deployLock = fopen(__DIR__.'/storage/framework/deploy.lock', 'c+');
if ($deployLock === false || !flock($deployLock, LOCK_EX | LOCK_NB)) {
    http_response_code(409);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Já existe um deploy em andamento.']);
    exit;
}

// 3. Extrair release.zip
if (file_exists($zipPath)) {
    $zip = new ZipArchive();
    $res = $zip->open($zipPath);
    if ($res === true) {
        if (!$zip->extractTo(__DIR__)) {
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

$ftpSyncFile = __DIR__ . '/.ftp-deploy-sync-state.json';
if (file_exists($ftpSyncFile)) {
    @unlink($ftpSyncFile);
    $logs['cleanup_sync_state'] = 'Arquivo .ftp-deploy-sync-state.json removido.';
}

// 5. Limpar caches compilados antigos de views e bootstrap
$oldViews = glob(__DIR__ . '/storage/framework/views/*.php');
if ($oldViews) {
    foreach ($oldViews as $viewFile) {
        @unlink($viewFile);
    }
    $logs['clear_compiled_views'] = count($oldViews) . ' views compiladas antigas removidas.';
}

$oldCache = glob(__DIR__ . '/bootstrap/cache/*.php');
if ($oldCache) {
    foreach ($oldCache as $cacheFile) {
        if (basename($cacheFile) !== '.gitignore') {
            @unlink($cacheFile);
        }
    }
    $logs['clear_bootstrap_cache'] = 'Bootstrap cache antigo limpo.';
}

// 6. Criar symlink do storage via PHP nativo
$storageTarget = __DIR__ . '/storage/app/public';
$storageLink = __DIR__ . '/public/storage';
if (!file_exists($storageLink) && !is_link($storageLink) && file_exists($storageTarget)) {
    try {
        @symlink($storageTarget, $storageLink);
        $logs['storage_link'] = 'Symlink do storage criado com sucesso.';
    } catch (\Throwable $e) {
        $logs['storage_link_error'] = $e->getMessage();
    }
}

// 7. Inicializar Laravel e executar comandos Artisan
try {
    if (file_exists(__DIR__ . '/vendor/autoload.php') && file_exists(__DIR__ . '/bootstrap/app.php')) {
        require __DIR__ . '/vendor/autoload.php';
        /** @var \Illuminate\Foundation\Application $app */
        $app = require_once __DIR__ . '/bootstrap/app.php';

        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        // Limpeza e recriação de caches em produção
        try {
            // Não limpar o cache da aplicação: ele pode estar no banco de dados.
            foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled', 'config:cache', 'route:cache', 'view:cache'] as $command) {
                if (\Illuminate\Support\Facades\Artisan::call($command) !== 0) {
                    throw new RuntimeException('Falha no comando '.$command.'.');
                }
            }
            $logs['cache'] = 'Caches de configuração, rotas e views gerados com sucesso.';
        } catch (\Throwable $e) {
            $logs['cache_error'] = $e->getMessage();
        }
    } else {
        throw new RuntimeException('Arquivos essenciais do Laravel ausentes após a extração.');
    }
} catch (\Throwable $e) {
    $logs['laravel_init_error'] = $e->getMessage();
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
