<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialProjectShowTest extends TestCase
{
    use RefreshDatabase;

    private function seedCofrinhoSetup(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Nubank Reserva',
            'kind' => Account::KIND_REGULAR,
            'color' => '#820ad1',
            'balance' => '5000.00',
        ]);
        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Viagem Japão',
            'target_amount' => '20000.00',
            'color' => '#10b981',
        ]);

        return compact('couple', 'user', 'account', 'project');
    }

    public function test_cannot_access_another_couples_cofrinho(): void
    {
        ['project' => $project] = $this->seedCofrinhoSetup();

        $otherCouple = Couple::factory()->create();
        $otherUser = User::factory()->create(['couple_id' => $otherCouple->id]);

        $this->actingAs($otherUser)
            ->get(route('cofrinhos.show', $project))
            ->assertNotFound();
    }

    public function test_show_displays_overview_with_both_evolution_charts_and_movements(): void
    {
        ['user' => $user, 'account' => $account, 'project' => $project] = $this->seedCofrinhoSetup();

        $categoryExpense = Category::create([
            'couple_id' => $user->couple_id,
            'name' => 'Investimentos',
            'type' => 'expense',
            'color' => '#10b981',
        ]);

        // Aporte de 5.000 em 2026-01-10
        $aporte = Transaction::create([
            'couple_id' => $user->couple_id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => '5000.00',
            'date' => '2026-01-10',
            'reference_month' => 1,
            'reference_year' => 2026,
            'payment_method' => 'pix',
            'description' => 'Aporte inicial Japão',
        ]);
        $aporte->categorySplits()->create([
            'category_id' => $categoryExpense->id,
            'amount' => '5000.00',
        ]);

        // Rendimento de 45.50 em 2026-02-01
        FinancialProjectEntry::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'financial_project_id' => $project->id,
            'type' => FinancialProjectEntry::TYPE_INTEREST,
            'amount' => '45.50',
            'date' => '2026-02-01',
            'note' => 'CDI Fevereiro',
        ]);

        $response = $this->actingAs($user)
            ->get(route('cofrinhos.show', $project));

        $response->assertOk();
        $response->assertSee('Viagem Japão');
        $response->assertSee('Evolução Global do Cofrinho');
        $response->assertSee('Evolução dos Juros e Rendimentos');
        $response->assertSee('Histórico de Movimentações');
        $response->assertSee('Aporte inicial Japão');
        $response->assertSee('CDI Fevereiro');
        $response->assertSee('R$ 5.045,50');
    }

    public function test_show_displays_empty_state_for_interest_chart_when_no_interest(): void
    {
        ['user' => $user, 'account' => $account, 'project' => $project] = $this->seedCofrinhoSetup();

        $categoryExpense = Category::create([
            'couple_id' => $user->couple_id,
            'name' => 'Investimentos',
            'type' => 'expense',
            'color' => '#10b981',
        ]);

        $aporte = Transaction::create([
            'couple_id' => $user->couple_id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => '2000.00',
            'date' => '2026-03-15',
            'reference_month' => 3,
            'reference_year' => 2026,
            'payment_method' => 'pix',
            'description' => 'Aporte sem juros',
        ]);
        $aporte->categorySplits()->create([
            'category_id' => $categoryExpense->id,
            'amount' => '2000.00',
        ]);

        $response = $this->actingAs($user)
            ->get(route('cofrinhos.show', $project));

        $response->assertOk();
        $response->assertSee('Evolução Global do Cofrinho');
        $response->assertSee('Nenhum rendimento registrado');
        $response->assertSee('Lançar primeiro rendimento');
    }

    public function test_show_filters_movements_by_period(): void
    {
        ['user' => $user, 'account' => $account, 'project' => $project] = $this->seedCofrinhoSetup();

        $categoryExpense = Category::create([
            'couple_id' => $user->couple_id,
            'name' => 'Investimentos',
            'type' => 'expense',
            'color' => '#10b981',
        ]);

        // Movimentação em Janeiro
        $janTx = Transaction::create([
            'couple_id' => $user->couple_id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => '1000.00',
            'date' => '2026-01-15',
            'reference_month' => 1,
            'reference_year' => 2026,
            'payment_method' => 'pix',
            'description' => 'Aporte de Janeiro',
        ]);
        $janTx->categorySplits()->create([
            'category_id' => $categoryExpense->id,
            'amount' => '1000.00',
        ]);

        // Movimentação em Fevereiro
        $febTx = Transaction::create([
            'couple_id' => $user->couple_id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => '2000.00',
            'date' => '2026-02-15',
            'reference_month' => 2,
            'reference_year' => 2026,
            'payment_method' => 'pix',
            'description' => 'Aporte de Fevereiro',
        ]);
        $febTx->categorySplits()->create([
            'category_id' => $categoryExpense->id,
            'amount' => '2000.00',
        ]);

        // Filtrar apenas 2026-02
        $response = $this->actingAs($user)
            ->get(route('cofrinhos.show', ['cofrinho' => $project, 'period' => '2026-02']));

        $response->assertOk();
        $response->assertSee('Aporte de Fevereiro');
        $response->assertDontSee('Aporte de Janeiro');
    }

    public function test_old_movements_route_returns_404(): void
    {
        ['user' => $user, 'project' => $project] = $this->seedCofrinhoSetup();

        $this->actingAs($user)
            ->get("/cofrinhos/{$project->id}/movimentacoes")
            ->assertNotFound();
    }

    public function test_card_links_navigate_to_cofrinho_show(): void
    {
        ['user' => $user, 'project' => $project] = $this->seedCofrinhoSetup();

        $response = $this->actingAs($user)->get(route('cofrinhos.index'));

        $response->assertOk();
        $html = $response->getContent();

        $showUrl = route('cofrinhos.show', $project);

        $this->assertStringContainsString('class="cofrinhos-project-card__header-link', $html);
        $this->assertStringContainsString('class="cofrinhos-project-card__body-link', $html);
        $this->assertStringContainsString('href="'.$showUrl.'"', $html);
        $this->assertStringContainsString('data-cofrinho-url="'.$showUrl.'"', $html);
    }

    public function test_can_update_cofrinho_target_with_brazilian_currency_format(): void
    {
        ['user' => $user, 'project' => $project] = $this->seedCofrinhoSetup();

        $response = $this->actingAs($user)->put(route('cofrinhos.update', $project), [
            'name' => 'Viagem Japão 2027',
            'target_amount' => '35.500,50',
            'color' => '#10b981',
            'is_active' => '1',
            '_redirect_to' => 'show',
        ]);

        $response->assertRedirect(route('cofrinhos.show', $project));
        $response->assertSessionHas('success', 'Cofrinho atualizado com sucesso.');

        $project->refresh();
        $this->assertSame('Viagem Japão 2027', $project->name);
        $this->assertEquals(35500.50, (float) $project->target_amount);
    }

    public function test_can_clear_cofrinho_target_amount(): void
    {
        ['user' => $user, 'project' => $project] = $this->seedCofrinhoSetup();

        $response = $this->actingAs($user)->put(route('cofrinhos.update', $project), [
            'name' => $project->name,
            'target_amount' => '',
            'color' => $project->color,
            'is_active' => '1',
            '_redirect_to' => 'show',
        ]);

        $response->assertRedirect(route('cofrinhos.show', $project));

        $project->refresh();
        $this->assertNull($project->target_amount);
    }

    public function test_updating_from_show_preserves_custom_asset_attributes(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->seedCofrinhoSetup();

        $assetProject = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Ações Petrobras',
            'asset_type' => FinancialProject::ASSET_TYPE_STOCK,
            'asset_code' => 'PETR4',
            'asset_quantity' => '100.00000000',
            'asset_avg_price' => '38.50',
            'target_amount' => '10000.00',
            'color' => '#0284c7',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('cofrinhos.update', $assetProject), [
            'name' => 'Ações Petrobras (PETR4)',
            'target_amount' => '15.000,00',
            'color' => '#0284c7',
            'is_active' => '1',
            '_redirect_to' => 'show',
        ]);

        $response->assertRedirect(route('cofrinhos.show', $assetProject));

        $assetProject->refresh();
        $this->assertSame('Ações Petrobras (PETR4)', $assetProject->name);
        $this->assertEquals(15000.00, (float) $assetProject->target_amount);
        $this->assertSame(FinancialProject::ASSET_TYPE_STOCK, $assetProject->asset_type);
        $this->assertSame('PETR4', $assetProject->asset_code);
        $this->assertEquals(100.0, (float) $assetProject->asset_quantity);
        $this->assertEquals(38.50, (float) $assetProject->asset_avg_price);
    }
}
