<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionStoreRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function setupTestEnv(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Alimentação',
            'type' => 'expense',
            'color' => '#10B981',
        ]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Principal',
            'kind' => Account::KIND_REGULAR,
            'color' => '#3B82F6',
            'balance' => '1000.00',
        ]);

        return compact('couple', 'user', 'category', 'account');
    }

    public function test_redireciona_de_volta_para_tela_de_lancamentos_com_filtros(): void
    {
        ['user' => $user, 'category' => $category, 'account' => $account] = $this->setupTestEnv();

        $fromUrl = route('transactions.index', [
            'month' => 4,
            'year' => 2026,
            'account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)->from($fromUrl)->post(route('transactions.store'), [
            'funding' => 'account',
            'payment_method' => 'Pix',
            'account_id' => $account->id,
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '35.00'],
            ],
            'description' => 'Mercado',
            'amount' => '35.00',
            'type' => 'expense',
            'date' => '2026-04-15',
        ]);

        $response->assertSessionHas('success', 'Lançamento realizado!');
        $response->assertRedirect($fromUrl);
    }

    public function test_redireciona_de_volta_para_dashboard_com_periodo(): void
    {
        ['user' => $user, 'category' => $category, 'account' => $account] = $this->setupTestEnv();

        $fromUrl = route('dashboard', [
            'period' => '2026-05',
            'account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)->from($fromUrl)->post(route('transactions.store'), [
            'funding' => 'account',
            'payment_method' => 'Pix',
            'account_id' => $account->id,
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '50.00'],
            ],
            'description' => 'Jantar',
            'amount' => '50.00',
            'type' => 'expense',
            'date' => '2026-05-10',
        ]);

        $response->assertSessionHas('success', 'Lançamento realizado!');
        $response->assertRedirect($fromUrl);
    }

    public function test_redireciona_removendo_parametros_prefill_da_query(): void
    {
        ['user' => $user, 'category' => $category, 'account' => $account] = $this->setupTestEnv();

        $fromUrl = route('dashboard', [
            'period' => '2026-07',
            'prefill_recurring' => 99,
            'prefill_cofrinho' => 12,
            'prefill_cofrinho_kind' => 'aporte',
            'account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)->from($fromUrl)->post(route('transactions.store'), [
            'funding' => 'account',
            'payment_method' => 'Pix',
            'account_id' => $account->id,
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '100.00'],
            ],
            'description' => 'Aporte',
            'amount' => '100.00',
            'type' => 'expense',
            'date' => '2026-07-01',
        ]);

        $response->assertSessionHas('success', 'Lançamento realizado!');
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('prefill_recurring', $location);
        $this->assertStringNotContainsString('prefill_cofrinho', $location);
        $this->assertStringContainsString('period=2026-07', $location);
        $this->assertStringContainsString('account_id='.$account->id, $location);
    }

    public function test_fallback_quando_nao_ha_referer(): void
    {
        ['user' => $user, 'category' => $category, 'account' => $account] = $this->setupTestEnv();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'account',
            'payment_method' => 'Pix',
            'account_id' => $account->id,
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '10.00'],
            ],
            'description' => 'Café',
            'amount' => '10.00',
            'type' => 'expense',
            'date' => '2026-04-01',
        ]);

        $response->assertSessionHas('success', 'Lançamento realizado!');
        $response->assertRedirect(route('transactions.index'));
    }
}
