<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Casal administrador das assinaturas (área /admin/assinaturas)
    |--------------------------------------------------------------------------
    |
    | Todos os utilizadores com este couple_id são tratados como admin de
    | assinaturas (além dos e-mails em admin_emails).
    |
    | Variável nova: DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID
    | Compat: CASAL_SUBSCRIPTION_ADMIN_COUPLE_ID
    |
    | Vazio no .env desativa esta regra (útil em testes). Por omissão: 1.
    |
    */
    'subscription_admin_couple_id' => ($raw = env('DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID', env('CASAL_SUBSCRIPTION_ADMIN_COUPLE_ID', '1'))) === '' ? null : (int) $raw,

    /*
    |--------------------------------------------------------------------------
    | E-mails de administrador (área gerencial de assinaturas)
    |--------------------------------------------------------------------------
    |
    | Lista separada por vírgulas. Estes utilizadores acedem a /admin/assinaturas
    | e são isentos de cobrança quando o faturamento está ativo.
    |
    | Variável nova: DUOZEN_ADMIN_EMAILS
    | Compat: CASAL_ADMIN_EMAILS
    |
    */
    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DUOZEN_ADMIN_EMAILS', env('CASAL_ADMIN_EMAILS', '')))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Isentos de assinatura (sem Stripe)
    |--------------------------------------------------------------------------
    |
    | E-mails que podem usar a app sem plano ativo (ex.: contas internas).
    |
    | Variável nova: DUOZEN_BILLING_EXEMPT_EMAILS
    | Compat: CASAL_BILLING_EXEMPT_EMAILS
    |
    */
    'billing_exempt_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DUOZEN_BILLING_EXEMPT_EMAILS', env('CASAL_BILLING_EXEMPT_EMAILS', '')))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Período de teste (dias), com cartão no registo via Stripe Checkout
    |--------------------------------------------------------------------------
    |
    | Stripe exige janela mínima ~48h para Checkout com trial; use pelo menos 3.
    |
    | Variável nova: DUOZEN_TRIAL_DAYS
    | Compat: CASAL_TRIAL_DAYS
    |
    */
    'trial_days' => max(3, (int) env('DUOZEN_TRIAL_DAYS', env('CASAL_TRIAL_DAYS', 14))),

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
    |
    | Variável nova: DUOZEN_BILLING_DISABLED
    | Compat: CASAL_BILLING_DISABLED
    |
    */
    'billing_disabled' => filter_var(env('DUOZEN_BILLING_DISABLED', env('CASAL_BILLING_DISABLED', false)), FILTER_VALIDATE_BOOLEAN),
];
