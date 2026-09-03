<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Console\Command;

class GoogleDriveAuthCommand extends Command
{
    protected $signature = 'drive:auth';

    protected $description = 'Gera o GOOGLE_DRIVE_REFRESH_TOKEN para contas pessoais (@gmail.com)';

    public function handle(): int
    {
        $this->info('=== Autenticação do Google Drive (OAuth 2.0 - Conta Pessoal) ===');
        $this->line('Este comando irá gerar o Refresh Token que nunca expira para uso no backup.');

        $clientId = config('backup.google_drive.client_id') ?: $this->ask('1. Informe o GOOGLE_DRIVE_CLIENT_ID:');
        $clientSecret = config('backup.google_drive.client_secret') ?: $this->secret('2. Informe o GOOGLE_DRIVE_CLIENT_SECRET:');

        if (empty($clientId) || empty($clientSecret)) {
            $this->error('Client ID e Client Secret são obrigatórios.');
            return self::FAILURE;
        }

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        // OAuth Playground como redirect URI padrão para autorização manual rápida
        $client->setRedirectUri('https://developers.google.com/oauthplayground');
        $client->addScope(GoogleDriveService::DRIVE);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();

        $this->newLine();
        $this->info('Passo A: Certifique-se de que "https://developers.google.com/oauthplayground" está na lista de "URIs de redirecionamento autorizados" no seu Google Cloud Console.');
        $this->newLine();
        $this->info('Passo B: Abra a URL abaixo no navegador para autorizar sua conta @gmail.com:');
        $this->line($authUrl);
        $this->newLine();
        $this->info('Passo C: Ao autorizar, o Google irá redirecionar para a página do OAuth Playground.');
        $this->info('Copie o código presente no parâmetro "code=" da URL (ex: 4/0A...)');
        
        $authCode = $this->ask('Cole o código de autorização aqui:');

        if (empty($authCode)) {
            $this->error('Código não informado.');
            return self::FAILURE;
        }

        try {
            // Remove possíveis espaços ou parâmetros extras
            $code = trim(urldecode($authCode));
            if (str_contains($code, 'code=')) {
                parse_str(parse_url($code, PHP_URL_QUERY) ?? $code, $params);
                $code = $params['code'] ?? $code;
            }

            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                $this->error('Erro retornado pelo Google: ' . ($token['error_description'] ?? $token['error']));
                return self::FAILURE;
            }

            $refreshToken = $token['refresh_token'] ?? null;

            if (!$refreshToken) {
                $this->error('Aviso: O Google não retornou um refresh_token.');
                $this->line('Isso acontece se a conta já autorizou anteriormente sem revogar.');
                $this->line('Tente acessar https://myaccount.google.com/permissions, revogue o app e execute novamente.');
                return self::FAILURE;
            }

            $this->newLine();
            $this->info('✅ Sucesso! O Refresh Token foi gerado.');
            $this->newLine();
            $this->line('Adicione estas variáveis no arquivo .env (local e na Hostinger):');
            $this->newLine();
            $this->comment("GOOGLE_DRIVE_CLIENT_ID=\"{$clientId}\"");
            $this->comment("GOOGLE_DRIVE_CLIENT_SECRET=\"{$clientSecret}\"");
            $this->comment("GOOGLE_DRIVE_REFRESH_TOKEN=\"{$refreshToken}\"");
            $this->newLine();

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Falha ao processar o token: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
