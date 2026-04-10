<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegularAccountBalanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_contas_exibe_saldo_formatado_para_conta_regular(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $cat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Teste',
            'type' => 'income',
            'color' => '#000000',
        ]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Nubank CC',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Pix recebido',
            'amount' => 250,
            'payment_method' => 'Pix',
            'type' => 'income',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $cat->id, 'amount' => '250.00']]);

        $response = $this->actingAs($user)->get(route('accounts.index'));

        $response->assertOk();
        $response->assertSee('Saldo atual', false);
        $response->assertSee('R$ 250,00', false);
        $response->assertSee('Nubank CC', false);
    }

    public function test_lancamentos_com_filtro_conta_regular_mostra_saldo_atual(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $cat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Teste',
            'type' => 'expense',
            'color' => '#000000',
        ]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta filtro',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Compra',
            'amount' => 40,
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-03-01',
            'reference_month' => 3,
            'reference_year' => 2026,
        ], [['category_id' => $cat->id, 'amount' => '40.00']]);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'month' => 4,
            'year' => 2026,
            'account_id' => $account->id,
        ]));

        $response->assertOk();
        $response->assertSee('Saldo atual desta conta', false);
        $response->assertSee('R$ -40,00', false);
    }
}
