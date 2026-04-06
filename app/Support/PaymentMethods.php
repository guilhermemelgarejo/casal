<?php

namespace App\Support;

final class PaymentMethods
{
    /**
     * Formas de pagamento para lançamentos em conta (não-cartão).
     * Crédito não entra aqui: o cartão é escolhido como registro próprio (`accounts.kind=credit_card`).
     *
     * @return list<string>
     */
    public static function forRegularAccounts(): array
    {
        return [
            'Dinheiro',
            'Cartão de Débito',
            'Pix',
            'Boleto',
        ];
    }

    /**
     * Alias: lista canónica usada em contas e validações de conta.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return self::forRegularAccounts();
    }
}
