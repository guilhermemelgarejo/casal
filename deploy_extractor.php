<?php

/**
 * DuoZen Auto Deploy & Release Extractor
 * Este script é acionado de forma segura pelo GitHub Actions para descompactar o release.zip
 * e executar as migrações e caches do Laravel na Hostinger.
 */

// 1. Ler o DEPLOY_TOKEN do arquivo .env local
$envFile = __DIR__ . '/.env';
$configuredToken = null;

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'DEPLOY_TOKEN=' )) {
            $configuredToken = trim(substr($line, strlen('DEPLOY_TOKEN=')));
            $configuredToken = trim($configuredToken, "\"' \t\n\r\0\x0B");
            break;
        }
    }
}

$providedToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? $_GET['token'] ?? $_POST['token'] ?? null;

if (empty($configuredToken)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'DEPLOY_TOKEN não encontrado ou não configurado no arquivo .env da hospedagem.',
    ]);
    exit;
}

if (!$providedToken || !hash_equals((string) $configuredToken, (string) $providedToken)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Acesso não autorizado: token de deploy inválido.',
    ]);
    exit;
}

$startTime = microtime(true);
$logs = [];
$zipPath = __DIR__ . '/release.zip';

// 2. Extrair release.zip se existir
if (file_exists($zipPath)) {
    $zip = new ZipArchive();
    $res = $zip->open($zipPath);
    if ($res === true) {
        $zip->extractTo(__DIR__);
        $zip->close();
        @unlink($zipPath);
        $logs['unzip'] = 'release.zip descompactado e removido com sucesso.';
    } else {
        $logs['unzip_error'] = 'Falha ao abrir release.zip (Código de erro: ' . $res . ')';
    }
} else {
    $logs['unzip'] = 'Nenhum release.zip pendente de extração.';
}

// 3. Inicializar Laravel e executar comandos Artisan
try {
    if (file_exists(__DIR__ . '/vendor/autoload.php') && file_exists(__DIR__ . '/bootstrap/app.php')) {
        require __DIR__ . '/vendor/autoload.php';
        /** @var \Illuminate\Foundation\Application $app */
        $app = require_once __DIR__ . '/bootstrap/app.php';

        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        // Migrações do banco
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $logs['migrate'] = trim(\Illuminate\Support\Facades\Artisan::output());

        // Symlink do storage
        $storageLink = __DIR__ . '/public/storage';
        if (!file_exists($storageLink) && !is_link($storageLink)) {
            \Illuminate\Support\Facades\Artisan::call('storage:link');
            $logs['storage_link'] = trim(\Illuminate\Support\Facades\Artisan::output());
        } else {
            $logs['storage_link'] = 'Symlink do storage já existe.';
        }

        // Limpeza e geração de cache em produção
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        \Illuminate\Support\Facades\Artisan::call('config:cache');
        \Illuminate\Support\Facades\Artisan::call('route:cache');
        \Illuminate\Support\Facades\Artisan::call('view:cache');
        $logs['cache'] = 'Configurações, rotas e views cacheadas com sucesso.';
    } else {
        $logs['laravel_init'] = 'Arquivos do Laravel ainda não encontrados para execução de migrações.';
    }
} catch (\Throwable $e) {
    $logs['artisan_error'] = $e->getMessage();
}

$duration = round(microtime(true) - $startTime, 3);

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Deploy e extração concluídos com sucesso.',
    'duration_seconds' => $duration,
    'timestamp' => date('c'),
    'logs' => $logs,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
