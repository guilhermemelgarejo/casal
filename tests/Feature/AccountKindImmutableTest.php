<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountKindImmutableTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_nao_altera_kind_mesmo_com_parametro_na_requisicao(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);

        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            '_form' => 'account-update-'.$account->id,
            'name' => 'Conta renomeada',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#222222',
        ]);

        $response->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame(Account::KIND_REGULAR, $account->kind);
        $this->assertSame('Conta renomeada', $account->name);
        $this->assertSame('#222222', $account->color);
    }

    public function test_cartao_nao_vira_conta_por_parametro_na_requisicao(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#333333',
        ]);

        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            '_form' => 'account-update-'.$account->id,
            'name' => 'Visa Gold',
            'kind' => Account::KIND_REGULAR,
            'color' => '#444444',
        ]);

        $response->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame(Account::KIND_CREDIT_CARD, $account->kind);
        $this->assertSame('Visa Gold', $account->name);
    }

    public function test_nao_permite_excluir_conta_com_lancamentos(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);

        $couple->transactions()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Depósito',
            'amount' => '100.00',
            'type' => 'income',
            'date' => '2026-08-01',
            'reference_month' => 8,
            'reference_year' => 2026,
        ]);

        $response = $this->actingAs($user)->delete(route('accounts.destroy', $account));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }
}
