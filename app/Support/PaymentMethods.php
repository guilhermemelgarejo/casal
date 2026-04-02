<?php

namespace App\Support;

final class PaymentMethods
{
    /**
     * Rótulo legado ainda possível em linhas antigas de `transactions.payment_method`.
     * Novos lançamentos não usam mais este valor (crédito é identificado pelo cartão cadastrado).
     */
    public const LEGACY_CREDIT_CARD = 'Cartão de Crédito';

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
     * Alias histórico: lista canónica usada em contas e validações de conta.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return self::forRegularAccounts();
    }
}
