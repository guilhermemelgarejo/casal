<?php

namespace App\Support;

final class PaymentMethods
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'Dinheiro',
            'Cartão de Crédito',
            'Cartão de Débito',
            'Pix',
            'Boleto',
            'Outros',
        ];
    }
}
