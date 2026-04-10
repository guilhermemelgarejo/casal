<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceFromTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_regular_e_receita_menos_despesa(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $catInc = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Salário',
            'type' => 'income',
            'color' => '#00aa00',
        ]);
        $catExp = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Gasto',
            'type' => 'expense',
            'color' => '#aa0000',
        ]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Entrada',
            'amount' => 100,
            'payment_method' => 'Pix',
            'type' => 'income',
            'date' => '2026-04-01',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $catInc->id, 'amount' => '100.00']]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Saída',
            'amount' => 30.5,
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-02',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $catExp->id, 'amount' => '30.50']]);

        $balances = Account::balancesFromTransactionsByAccountId([$account->id]);

        $this->assertEqualsWithDelta(69.5, $balances[$account->id], 0.001);
        $account->refresh();
        $this->assertEqualsWithDelta(69.5, (float) $account->balance, 0.001);
    }

    public function test_conta_sem_lancamentos_retorna_zero(): void
    {
        $couple = Couple::factory()->create();
        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Vazia',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);

        $balances = Account::balancesFromTransactionsByAccountId([$account->id]);

        $this->assertSame(0.0, $balances[$account->id]);
        $account->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $account->balance, 0.001);
    }

    public function test_excluir_lancamento_reverte_saldo_persistido(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $catExp = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Gasto',
            'type' => 'expense',
            'color' => '#aa0000',
        ]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);

        $tx = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Compra',
            'amount' => 25,
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-01',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $catExp->id, 'amount' => '25.00']]);

        $account->refresh();
        $this->assertEqualsWithDelta(-25.0, (float) $account->balance, 0.001);

        $tx->delete();

        $account->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $account->balance, 0.001);
    }
}
