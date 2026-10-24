<?php

namespace Tests\Feature;

use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class TermsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_termos_sao_publicos_e_destacam_dados_pendentes(): void
    {
        config(['duozen.legal.provider_name' => null, 'duozen.legal.provider_document' => null]);

        $response = $this->get(route('legal.terms'));

        $response->assertOk();
        $response->assertSee('Termos e Condições de Uso');
        $response->assertSee('Renovação automática.', false);
        $response->assertSee('art. 49 do Código de Defesa do Consumidor', false);
        $response->assertSee('[a preencher: CNPJ ou CPF]', false);
    }

    public function test_termos_exibem_identificacao_configurada_do_fornecedor(): void
    {
        config([
            'duozen.legal.provider_name' => 'DuoZen Tecnologia Ltda.',
            'duozen.legal.provider_document' => '00.000.000/0001-00',
            'duozen.legal.provider_address' => 'Rua Exemplo, 100, Porto Alegre/RS',
            'duozen.legal.contact_email' => 'contato@exemplo.com.br',
        ]);

        $response = $this->get(route('legal.terms'));

        $response->assertSee('DuoZen Tecnologia Ltda.');
        $response->assertSee('00.000.000/0001-00');
        $response->assertSee('mailto:contato@exemplo.com.br', false);
        $response->assertDontSee('a preencher', false);
    }

    public function test_cadastro_e_landing_apontam_para_os_termos(): void
    {
        $this->get(route('register'))->assertSee(route('legal.terms'), false);
        $this->get('/')->assertSee(route('legal.terms'), false);
    }
}
