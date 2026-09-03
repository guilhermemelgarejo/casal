<?php

namespace App\Console\Commands;

use App\Mail\DatabaseBackupNotificationMail;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile;
use Ifsnop\Mysqldump\Mysqldump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use ZipArchive;

class BackupDatabaseToDrive extends Command
{
    protected $signature = 'db:backup-drive
                            {--notify-email= : Sobrescrever o e-mail de notificação}
                            {--no-email : Não enviar e-mail de notificação}';

    protected $description = 'Gera o dump do banco de dados, compacta e criptografa com AES-256 e envia para o Google Drive';

    public function handle(): int
    {
        $this->info('Iniciando rotina de backup do banco de dados...');
        Log::info('[Backup DB] Iniciando rotina de backup do banco de dados...');

        // 1. Validar configurações do Google Drive e ZIP
        $zipPassword = config('backup.zip_password');
        $googleFolderId = config('backup.google_drive.folder_id');
        $notifyEmail = $this->option('notify-email') ?: config('backup.notification_email');

        if (empty($googleFolderId)) {
            $msg = 'ERRO: A variável GOOGLE_DRIVE_FOLDER_ID não está configurada no .env.';
            Log::error("[Backup DB] {$msg}");
            $this->error($msg);
            return self::FAILURE;
        }

        // Validação da forma de autenticação (OAuth 2.0 vs Service Account)
        $clientId = config('backup.google_drive.client_id');
        $clientSecret = config('backup.google_drive.client_secret');
        $refreshToken = config('backup.google_drive.refresh_token');
        $isOAuth = !empty($clientId) && !empty($clientSecret) && !empty($refreshToken);

        if ($isOAuth) {
            Log::info('[Backup DB] Modo de autenticação: OAuth 2.0 (Conta Pessoal @gmail.com).');
        } else {
            $googleCredentialsPath = $this->resolveCredentialsPath(config('backup.google_drive.credentials_path'));
            if (!$googleCredentialsPath) {
                $msg = 'ERRO: Credenciais do Google Drive não configuradas. Preencha GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET e GOOGLE_DRIVE_REFRESH_TOKEN no .env (ou coloque o arquivo JSON da Service Account em storage/app/google-credentials.json).';
                Log::error("[Backup DB] {$msg}");
                $this->error($msg);
                return self::FAILURE;
            }
            Log::info("[Backup DB] Modo de autenticação: Service Account ({$googleCredentialsPath}).");
        }

        if (empty($zipPassword)) {
            Log::warning('[Backup DB] BACKUP_ZIP_PASSWORD não foi definida no .env. O ZIP será gerado sem senha.');
            $this->warn('AVISO: BACKUP_ZIP_PASSWORD não foi definida no .env. O ZIP será gerado sem criptografia por senha.');
        }

        $connectionName = config('database.default');
        $dbConfig = config("database.connections.{$connectionName}");

        $dateFormatted = now()->format('Y-m-d_H-i-s');
        $tempDir = storage_path('app/temp-backups');

        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $dbDisplayName = $dbConfig['database'] ?? $connectionName;
        $cleanDbName = preg_replace('/[^a-zA-Z0-9_-]/', '_', basename($dbDisplayName));
        
        $sqlFileName = "dump_{$cleanDbName}_{$dateFormatted}.sql";
        $sqlFilePath = "{$tempDir}/{$sqlFileName}";
        $zipFileName = "backup_{$cleanDbName}_{$dateFormatted}.zip";
        $zipFilePath = "{$tempDir}/{$zipFileName}";

        try {
            // 2. Realizar o dump do banco
            $this->line('1/4 - Gerando dump do banco de dados...');
            Log::info("[Backup DB] 1/4 - Gerando dump do banco ({$connectionName})...");
            $this->dumpDatabase($connectionName, $dbConfig, $sqlFilePath);

            // 3. Compactar e encriptar com AES-256
            $this->line('2/4 - Compactando e aplicando criptografia AES-256...');
            Log::info('[Backup DB] 2/4 - Compactando e aplicando criptografia AES-256...');
            $this->createEncryptedZip($sqlFilePath, $sqlFileName, $zipFilePath, $zipPassword);

            // Remove o arquivo SQL puro imediatamente
            @unlink($sqlFilePath);

            $fileSizeBytes = filesize($zipFilePath);
            $fileSizeFormatted = $this->formatBytes($fileSizeBytes);
            $this->info("Arquivo ZIP gerado com sucesso ({$fileSizeFormatted}).");
            Log::info("[Backup DB] ZIP gerado com sucesso: {$zipFileName} ({$fileSizeFormatted})");

            // 4. Upload para o Google Drive
            $this->line('3/4 - Enviando arquivo para o Google Drive...');
            Log::info("[Backup DB] 3/4 - Enviando arquivo para o Google Drive (Pasta ID: {$googleFolderId})...");
            $driveLink = $this->uploadToGoogleDrive($zipFilePath, $zipFileName, $googleFolderId);
            $this->info("Upload concluído! Link do arquivo: {$driveLink}");
            Log::info("[Backup DB] Upload concluído no Google Drive. Link: {$driveLink}");

            // 5. Envio do E-mail com o link
            if (!$this->option('no-email') && !empty($notifyEmail)) {
                $this->line("4/4 - Enviando e-mail de notificação para {$notifyEmail}...");
                Log::info("[Backup DB] 4/4 - Enviando e-mail de notificação para {$notifyEmail}...");
                Mail::to($notifyEmail)->send(new DatabaseBackupNotificationMail(
                    databaseName: (string) $dbDisplayName,
                    fileName: $zipFileName,
                    fileSize: $fileSizeFormatted,
                    driveLink: $driveLink,
                    createdAt: now()->format('d/m/Y H:i:s')
                ));
                $this->info('E-mail enviado com sucesso!');
                Log::info('[Backup DB] E-mail enviado com sucesso.');
            } else {
                $this->line('4/4 - Notificação por e-mail ignorada.');
            }

            $this->info('✅ Processo de backup concluído com sucesso!');
            Log::info('[Backup DB] ✅ Processo de backup concluído com sucesso!');
            return self::SUCCESS;

        } catch (Throwable $e) {
            $errorMsg = 'Falha na rotina de backup: ' . $e->getMessage();
            Log::error("[Backup DB] {$errorMsg}", [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->error($errorMsg);
            return self::FAILURE;
        } finally {
            // Limpeza preventiva de arquivos temporários locais
            if (file_exists($sqlFilePath)) {
                @unlink($sqlFilePath);
            }
            if (file_exists($zipFilePath)) {
                @unlink($zipFilePath);
            }
        }
    }

    protected function createGoogleDriveService(): GoogleDriveService
    {
        $client = new GoogleClient();
        $client->addScope(GoogleDriveService::DRIVE);

        $clientId = config('backup.google_drive.client_id');
        $clientSecret = config('backup.google_drive.client_secret');
        $refreshToken = config('backup.google_drive.refresh_token');

        if (!empty($clientId) && !empty($clientSecret) && !empty($refreshToken)) {
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->refreshToken($refreshToken);

            return new GoogleDriveService($client);
        }

        $credentialsPath = $this->resolveCredentialsPath(config('backup.google_drive.credentials_path'));
        if ($credentialsPath) {
            $client->setAuthConfig($credentialsPath);
            return new GoogleDriveService($client);
        }

        throw new Throwable('Credenciais do Google Drive não configuradas no .env.');
    }

    protected function resolveCredentialsPath(?string $configuredPath): ?string
    {
        $rawPath = $configuredPath ?: 'storage/app/google-credentials.json';

        $candidates = array_unique(array_filter([
            $rawPath,
            base_path($rawPath),
            storage_path($rawPath),
            storage_path('app/' . basename($rawPath)),
            base_path('storage/app/' . basename($rawPath)),
        ]));

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }

    protected function dumpDatabase(string $connection, array $config, string $destinationPath): void
    {
        $driver = $config['driver'] ?? $connection;

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 3306;
            $database = $config['database'] ?? '';
            $username = $config['username'] ?? 'root';
            $password = $config['password'] ?? '';

            $dumpSettings = [
                'add-drop-table' => true,
                'single-transaction' => true,
                'lock-tables' => false,
                'add-locks' => false,
                'extended-insert' => true,
                'disable-foreign-keys-check' => true,
                'skip-definer' => true,
            ];

            $dump = new Mysqldump("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, $dumpSettings);
            $dump->start($destinationPath);
            return;
        }

        if ($driver === 'sqlite') {
            $databasePath = $config['database'];
            if (!file_exists($databasePath)) {
                throw new Throwable("Banco SQLite não encontrado no caminho: {$databasePath}");
            }
            if (!copy($databasePath, $destinationPath)) {
                throw new Throwable("Falha ao copiar banco SQLite para o arquivo temporário.");
            }
            return;
        }

        throw new Throwable("Driver de banco de dados '{$driver}' não suportado para dump automatizado.");
    }

    protected function createEncryptedZip(string $sqlFilePath, string $internalFileName, string $zipFilePath, ?string $password): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Throwable("Não foi possível criar o arquivo ZIP em: {$zipFilePath}");
        }

        $zip->addFile($sqlFilePath, $internalFileName);

        if (!empty($password)) {
            $zip->setPassword($password);
            $zip->setEncryptionName($internalFileName, ZipArchive::EM_AES_256);
        }

        $zip->close();
    }

    protected function uploadToGoogleDrive(string $filePath, string $fileName, string $folderId): string
    {
        $service = $this->createGoogleDriveService();

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $content = file_get_contents($filePath);
        $uploadedFile = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => 'application/zip',
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink, name',
            'supportsAllDrives' => true,
        ]);

        return $uploadedFile->webViewLink ?: "https://drive.google.com/file/d/{$uploadedFile->id}/view";
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
