<?php

namespace App\Support;

class Billing
{
    /**
     * Cobrança obrigatória: Stripe configurado e faturamento não desativado.
     */
    public static function isEnforced(): bool
    {
        if (config('duozen.billing_disabled', false)) {
            return false;
        }

        $secret = config('cashier.secret');
        $price = config('duozen.stripe_price_id');

        return filled($secret) && filled($price);
    }
}
