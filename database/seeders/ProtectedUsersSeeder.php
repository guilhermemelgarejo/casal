<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Garante contas fixas do projeto (ver `.cursor/rules/usuarios-existentes.mdc`).
 * firstOrCreate: não altera utilizadores que já existem (nome, senha, casal, etc.).
 */
class ProtectedUsersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['email' => 'guilherme.melgarejo@gmail.com', 'name' => 'Guilherme Melgarejo'],
            ['email' => 'tainarygg@gmail.com', 'name' => 'Tainary'],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
