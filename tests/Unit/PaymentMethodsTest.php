<?php

namespace Tests\Unit;

use App\Support\PaymentMethods;
use PHPUnit\Framework\TestCase;

class PaymentMethodsTest extends TestCase
{
    public function test_all_retorna_lista_nao_vazia_com_metodos_esperados(): void
    {
        $methods = PaymentMethods::all();

        $this->assertNotEmpty($methods);
        $this->assertContains('Pix', $methods);
        $this->assertContains('Dinheiro', $methods);
        $this->assertNotContains('Cartão de Crédito', $methods);
    }
}
