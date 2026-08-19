<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DeployControllerTest extends TestCase
{
    public function test_retorna_500_se_deploy_token_nao_estiver_configurado(): void
    {
        Config::set('app.deploy_token', null);

        $response = $this->getJson('/system/deploy-hook');

        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error',
        ]);
    }

    public function test_retorna_403_se_token_for_invalido(): void
    {
        Config::set('app.deploy_token', 'token_secreto_super_seguro_123');

        $response = $this->getJson('/system/deploy-hook?token=token_errado');

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Acesso não autorizado: token de deploy inválido.',
        ]);
    }

    public function test_executa_deploy_com_token_valido_via_parametro(): void
    {
        Config::set('app.deploy_token', 'token_secreto_super_seguro_123');

        $response = $this->getJson('/system/deploy-hook?token=token_secreto_super_seguro_123');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'message',
            'duration_seconds',
            'timestamp',
            'tasks' => [
                'migrate',
                'storage_link',
                'optimize_clear',
                'cache_optimize',
            ],
        ]);
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_executa_deploy_com_token_valido_via_header(): void
    {
        Config::set('app.deploy_token', 'token_secreto_super_seguro_123');

        $response = $this->postJson('/system/deploy-hook', [], [
            'X-Deploy-Token' => 'token_secreto_super_seguro_123',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
        ]);
    }
}
