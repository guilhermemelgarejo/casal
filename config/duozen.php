<?php

return [
    /*
    | Conta administrativa preexistente confirmada pelo responsável do projeto.
    | O vínculo exige ID, e-mail e criação anterior à atualização de segurança.
    | Não confirma e-mail no banco e não concede privilégios a novos cadastros.
    */
    'existing_admin_accounts' => [
        [
            'id' => 1,
            'email' => 'guilherme.melgarejo@gmail.com',
            'created_before' => '2026-10-24T01:00:00+00:00',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Casal administrador das assinaturas (área /admin/assinaturas)
    |--------------------------------------------------------------------------
    |
    | Opção legada ignorada na autorização. Use IDs explícitos ou e-mails
    | verificados. Novos membros de um casal nunca herdam administração.
    |
    */
    'subscription_admin_couple_id' => ($raw = env('DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID', '')) === '' ? null : (int) $raw,

    'admin_user_ids' => array_values(array_filter(array_map(
        'intval', explode(',', (string) env('DUOZEN_ADMIN_USER_IDS', ''))
    ), fn (int $id) => $id > 0)),

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
