<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalActionButtonsTest extends TestCase
{
    use RefreshDatabase;

    private function coupleWithAccounts(int $regularCount = 2): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $accounts = [];
        for ($i = 1; $i <= $regularCount; $i++) {
            $accounts[] = Account::create([
                'couple_id' => $couple->id,
                'name' => "Conta Corrente {$i}",
                'kind' => Account::KIND_REGULAR,
                'color' => '#10B981',
                'balance' => '1000.00',
            ]);
        }

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Alimentação',
            'type' => 'expense',
            'color' => '#EF4444',
        ]);

        return [$couple, $user, $accounts, $category];
    }

    public function test_botoes_receita_e_despesa_e_modal_presentes_em_todas_as_telas(): void
    {
        [$couple, $user, $accounts, $category] = $this->coupleWithAccounts(2);

        $routes = [
            route('dashboard'),
            route('transactions.index'),
            route('reports.index'),
            route('accounts.index'),
            route('credit-card-statements.index'),
            route('cofrinhos.index'),
            route('recurring-transactions.index'),
            route('categories.index'),
            route('couple.index'),
            route('profile.edit'),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($user)->get($url);

            $this->assertSame(200, $response->status(), "Route {$url} did not return 200, returned {$response->status()}");
            $this->assertTrue(str_contains($response->getContent(), 'data-tx-open-preset="income"'), "Route {$url} is missing Receita preset");
            $this->assertTrue(str_contains($response->getContent(), 'data-tx-open-preset="expense"'), "Route {$url} is missing Despesa preset");
            $this->assertTrue(str_contains($response->getContent(), 'id="modalNewTransaction"'), "Route {$url} is missing modalNewTransaction");
            $this->assertTrue(str_contains($response->getContent(), 'id="modalAccountTransfer"'), "Route {$url} is missing modalAccountTransfer");
            $this->assertTrue(str_contains($response->getContent(), 'data-bs-target="#modalAccountTransfer"'), "Route {$url} is missing Transferir target");
        }
    }

    public function test_botao_transferir_oculto_quando_ha_apenas_uma_conta_corrente(): void
    {
        [$couple, $user, $accounts, $category] = $this->coupleWithAccounts(1);

        $routes = [
            route('dashboard'),
            route('transactions.index'),
            route('reports.index'),
            route('accounts.index'),
            route('categories.index'),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($user)->get($url);

            $this->assertSame(200, $response->status(), "Route {$url} did not return 200");
            $this->assertTrue(str_contains($response->getContent(), 'data-tx-open-preset="income"'), "Route {$url} is missing Receita preset");
            $this->assertTrue(str_contains($response->getContent(), 'data-tx-open-preset="expense"'), "Route {$url} is missing Despesa preset");
            $this->assertTrue(str_contains($response->getContent(), 'id="modalNewTransaction"'), "Route {$url} is missing modalNewTransaction");

            $this->assertFalse(str_contains($response->getContent(), 'data-bs-target="#modalAccountTransfer"'), "Route {$url} should NOT have Transferir target with 1 account");
            $this->assertFalse(str_contains($response->getContent(), 'id="modalAccountTransfer"'), "Route {$url} should NOT have modalAccountTransfer with 1 account");
        }
    }

    public function test_envio_de_receita_a_partir_de_tela_secundaria_redireciona_com_sucesso(): void
    {
        [$couple, $user, $accounts, $category] = $this->coupleWithAccounts(2);

        $incomeCat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Salário',
            'type' => 'income',
            'color' => '#10B981',
        ]);

        $reportsUrl = route('reports.index');

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'account',
            'account_id' => $accounts[0]->id,
            'description' => 'Bônus recebido',
            'amount' => '500.00',
            'type' => 'income',
            'date' => '2026-04-10',
            'payment_method' => 'Pix',
            'category_allocations' => [
                ['category_id' => $incomeCat->id, 'amount' => '500.00'],
            ],
        ], ['referer' => $reportsUrl]);

        $response->assertRedirect($reportsUrl);
        $response->assertSessionHas('success');
    }

    public function test_envio_de_transferencia_a_partir_de_tela_secundaria_redireciona_com_sucesso(): void
    {
        [$couple, $user, $accounts, $category] = $this->coupleWithAccounts(2);

        $cofrinhosUrl = route('cofrinhos.index');

        $response = $this->actingAs($user)->post(route('accounts.transfer'), [
            '_form' => 'account-transfer',
            'from_account_id' => $accounts[0]->id,
            'to_account_id' => $accounts[1]->id,
            'amount' => '200,00',
            'date' => '2026-04-10',
            'payment_method' => 'Pix',
            'description' => 'Transferência da tela de cofrinhos',
        ], ['referer' => $cofrinhosUrl]);

        $response->assertRedirect($cofrinhosUrl);
        $response->assertSessionHas('success');
    }
}
