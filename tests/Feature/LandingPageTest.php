<?php

namespace Tests\Feature;

use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_exibe_proposta_e_metadados_de_compartilhamento(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Mais planos juntos.');
        $response->assertSee('property="og:image"', false);
        $response->assertSee('images/social/duozen-og.png', false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
        $response->assertSee('application/ld+json', false);
    }

    public function test_instagram_oficial_aparece_por_padrao(): void
    {
        $response = $this->get('/');

        $response->assertSee('https://www.instagram.com/duozen.app/', false);
        $response->assertSee('@duozen.app');
    }

    public function test_preco_e_redes_so_aparecem_quando_configurados(): void
    {
        config([
            'duozen.marketing.social' => [],
            'duozen.marketing.social_handle' => null,
        ]);

        $response = $this->get('/');

        $response->assertDontSee('/mês por casal');
        $response->assertDontSee('lp-social', false);

        config([
            'duozen.marketing.price_amount' => 19.9,
            'duozen.marketing.social' => ['Instagram' => 'https://www.instagram.com/duozen.exemplo'],
            'duozen.marketing.social_handle' => '@duozen.exemplo',
        ]);

        $response = $this->get('/');

        $response->assertSee('R$ 19,90');
        $response->assertSee('/mês por casal');
        $response->assertSee('https://www.instagram.com/duozen.exemplo', false);
        $response->assertSee('"price":"19.90"', false);
        $response->assertSee('@duozen.exemplo');
    }
}
