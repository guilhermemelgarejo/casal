<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Casal administrador das assinaturas (área /admin/assinaturas)
    |--------------------------------------------------------------------------
    |
    | Todos os usuários com este couple_id são tratados como admin de
    | assinaturas (além dos e-mails em admin_emails).
    |
    | Vazio no .env desativa esta regra (útil em testes). Por padrão: 1.
    |
    */
    'subscription_admin_couple_id' => ($raw = env('DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID', '1')) === '' ? null : (int) $raw,

    /*
    |--------------------------------------------------------------------------
    | E-mails de administrador (área gerencial de assinaturas)
    |--------------------------------------------------------------------------
    |
    | Lista separada por vírgulas. Estes usuários acessam /admin/assinaturas
    | e são isentos de cobrança quando o faturamento está ativo.
    |
    */
    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DUOZEN_ADMIN_EMAILS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Isentos de assinatura (sem Stripe)
    |--------------------------------------------------------------------------
    |
    | E-mails que podem usar a app sem plano ativo (ex.: contas internas).
    |
    */
    'billing_exempt_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DUOZEN_BILLING_EXEMPT_EMAILS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Período de teste (dias), com cartão no registro via Stripe Checkout
    |--------------------------------------------------------------------------
    |
    | Stripe exige janela mínima ~48h para Checkout com trial; use pelo menos 3.
    |
    */
    'trial_days' => max(3, (int) env('DUOZEN_TRIAL_DAYS', 14)),

    /*
    |--------------------------------------------------------------------------
    | Price ID mensal no Stripe (ex.: price_xxx)
    |--------------------------------------------------------------------------
    */
    'stripe_price_id' => env('STRIPE_PRICE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Desativar exigência de assinatura (testes / desenvolvimento)
    |--------------------------------------------------------------------------
    */
    'billing_disabled' => filter_var(env('DUOZEN_BILLING_DISABLED', false), FILTER_VALIDATE_BOOLEAN),
];
