<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contas fixas do projeto (ver `.cursor/rules/usuarios-existentes.mdc`).
 * Usuários: `firstOrCreate` por e-mail — não sobrescreve nome/senha de quem já existe.
 * Casal de desenvolvimento: criado/atualizado por `invite_code` fixo; categorias padrão só se o casal não tiver nenhuma.
 * `couple_id` só é preenchido quando está vazio (não desfaz casal que o usuário já tenha configurado).
 */
class ProtectedUsersSeeder extends Seeder
{
    private const DEV_INVITE_CODE = 'DuoZenDev1';

    public function run(): void
    {
        $accounts = [
            ['email' => 'guilherme.melgarejo@gmail.com', 'name' => 'Guilherme Melgarejo'],
            ['email' => 'tainarygg@gmail.com', 'name' => 'Tainary'],
        ];

        $couple = Couple::firstOrCreate(
            ['invite_code' => self::DEV_INVITE_CODE],
            [
                'name' => 'Casal DuoZen',
                'monthly_income' => 0,
                'spending_alert_threshold' => 80,
            ]
        );

        if ($couple->categories()->count() === 0) {
            $defaults = [
                ['name' => 'Alimentação', 'type' => 'expense', 'color' => '#ef4444'],
                ['name' => 'Moradia', 'type' => 'expense', 'color' => '#3b82f6'],
                ['name' => 'Transporte', 'type' => 'expense', 'color' => '#f59e0b'],
                ['name' => 'Lazer', 'type' => 'expense', 'color' => '#10b981'],
                [
                    'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
                    'type' => 'expense',
                    'color' => '#64748b',
                    'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
                ],
                [
                    'name' => Category::NAME_INTERNAL_TRANSFER_EXPENSE,
                    'type' => 'expense',
                    'color' => '#94a3b8',
                    'system_key' => Category::SYSTEM_KEY_INTERNAL_TRANSFER_EXPENSE,
                ],
                [
                    'name' => Category::NAME_INTERNAL_TRANSFER_INCOME,
                    'type' => 'income',
                    'color' => '#94a3b8',
                    'system_key' => Category::SYSTEM_KEY_INTERNAL_TRANSFER_INCOME,
                ],
                ['name' => 'Salário', 'type' => 'income', 'color' => '#8b5cf6'],
            ];

            foreach ($defaults as $default) {
                $couple->categories()->create($default);
            }
        }

        foreach ($accounts as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );

            if ($user->couple_id === null) {
                $user->couple_id = $couple->id;
                $user->save();
            }
        }
    }
}
