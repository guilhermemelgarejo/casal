<?php

namespace Tests\Feature;

use App\Http\Controllers\Concerns\PreparesTransactionModalPayload;
use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\FinancialProject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialProjectDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private function setupCoupleWithUser(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Corrente Principal',
            'kind' => Account::KIND_REGULAR,
            'color' => '#0d6efd',
            'balance' => '5000.00',
        ]);

        Category::ensureSavingsCategoriesForCouple((int) $couple->id);

        return compact('couple', 'user', 'account');
    }

    public function test_cofrinho_is_active_by_default_on_store(): void
    {
        ['user' => $user] = $this->setupCoupleWithUser();

        $response = $this->actingAs($user)->post(route('cofrinhos.store'), [
            'name' => 'Reserva de Emergência',
            'target_amount' => '10000.00',
            'color' => '#0d9488',
        ]);

        $response->assertRedirect(route('cofrinhos.index'));

        $this->assertDatabaseHas('financial_projects', [
            'name' => 'Reserva de Emergência',
            'is_active' => true,
        ]);

        $project = FinancialProject::where('name', 'Reserva de Emergência')->first();
        $this->assertTrue($project->is_active);
    }

    public function test_cofrinho_can_be_deactivated_and_reactivated_via_update(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleWithUser();

        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Viagem Japão',
            'target_amount' => '20000.00',
            'is_active' => true,
        ]);

        // Desativar
        $this->actingAs($user)->put(route('cofrinhos.update', $project), [
            'name' => 'Viagem Japão',
            'target_amount' => '20000.00',
            'is_active' => 0,
        ])->assertRedirect(route('cofrinhos.index'));

        $this->assertFalse($project->fresh()->is_active);

        // Reativar
        $this->actingAs($user)->put(route('cofrinhos.update', $project), [
            'name' => 'Viagem Japão',
            'target_amount' => '20000.00',
            'is_active' => 1,
        ])->assertRedirect(route('cofrinhos.index'));

        $this->assertTrue($project->fresh()->is_active);
    }

    public function test_cofrinho_can_be_toggled_via_toggle_active_route(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleWithUser();

        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Casamento',
            'is_active' => true,
        ]);

        // Toggle 1: Ativo -> Inativo
        $response1 = $this->actingAs($user)->patch(route('cofrinhos.toggle-active', $project));
        $response1->assertRedirect(route('cofrinhos.index'));
        $response1->assertSessionHas('success', 'Cofrinho desativado com sucesso.');
        $this->assertFalse($project->fresh()->is_active);

        // Toggle 2: Inativo -> Ativo
        $response2 = $this->actingAs($user)->patch(route('cofrinhos.toggle-active', $project));
        $response2->assertRedirect(route('cofrinhos.index'));
        $response2->assertSessionHas('success', 'Cofrinho reativado com sucesso.');
        $this->assertTrue($project->fresh()->is_active);
    }

    public function test_deactivated_cofrinho_is_hidden_from_transaction_modal_payload_for_new_transactions(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleWithUser();

        $activeProject = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Cofrinho Ativo',
            'is_active' => true,
        ]);

        $inactiveProject = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Cofrinho Desativado',
            'is_active' => false,
        ]);

        $dummy = new class {
            use PreparesTransactionModalPayload {
                transactionModalPayload as public;
            }
        };

        $this->actingAs($user);
        $payload = $dummy->transactionModalPayload();

        $projectsInPayload = $payload['financialProjects'];
        $this->assertTrue($projectsInPayload->contains('id', $activeProject->id));
        $this->assertFalse($projectsInPayload->contains('id', $inactiveProject->id));
    }

    public function test_deactivated_cofrinho_is_included_in_transaction_modal_payload_when_editing_linked_transaction(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account] = $this->setupCoupleWithUser();

        $inactiveProject = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Cofrinho Antigo Desativado',
            'is_active' => false,
        ]);

        $investCat = Category::investmentsForCouple((int) $couple->id);

        $tx = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Aporte histórico',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-05-01',
            'reference_month' => 5,
            'reference_year' => 2026,
            'financial_project_id' => $inactiveProject->id,
        ]);

        $tx->syncCategorySplits([
            ['category_id' => $investCat->id, 'amount' => '100.00'],
        ]);

        session(['edit_transaction_id' => $tx->id]);

        $dummy = new class {
            use PreparesTransactionModalPayload {
                transactionModalPayload as public;
            }
        };

        $this->actingAs($user);
        $payload = $dummy->transactionModalPayload();

        $projectsInPayload = $payload['financialProjects'];
        $this->assertTrue($projectsInPayload->contains('id', $inactiveProject->id));
    }

    public function test_dashboard_blocks_prefill_for_deactivated_cofrinho(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleWithUser();

        $inactiveProject = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Reserva Desativada',
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'period' => '2026-05',
            'prefill_cofrinho' => $inactiveProject->id,
            'prefill_cofrinho_kind' => 'aporte',
        ]));

        $response->assertOk();
        $response->assertSee('Este cofrinho está desativado.', false);
        $response->assertSee('data-tx-cofrinho-prefill=""', false);
    }

    public function test_store_transaction_rejects_deactivated_cofrinho(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account] = $this->setupCoupleWithUser();

        $inactiveProject = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Cofrinho Desativado',
            'is_active' => false,
        ]);

        $investCat = Category::investmentsForCouple((int) $couple->id);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'account',
            'account_id' => $account->id,
            'description' => 'Tentativa de aporte em cofrinho inativo',
            'amount' => '150.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-05-10',
            'financial_project_id' => $inactiveProject->id,
            'category_allocations' => [
                ['category_id' => $investCat->id, 'amount' => '150.00'],
            ],
        ]);

        $response->assertSessionHasErrors('financial_project_id');
        $this->assertDatabaseMissing('transactions', [
            'description' => 'Tentativa de aporte em cofrinho inativo',
        ]);
    }

    public function test_update_transaction_keeps_existing_deactivated_cofrinho_without_error(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account] = $this->setupCoupleWithUser();

        $inactiveProject = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Cofrinho Histórico',
            'is_active' => false,
        ]);

        $investCat = Category::investmentsForCouple((int) $couple->id);

        $tx = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Aporte original',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-05-01',
            'reference_month' => 5,
            'reference_year' => 2026,
            'financial_project_id' => $inactiveProject->id,
        ]);

        $tx->syncCategorySplits([
            ['category_id' => $investCat->id, 'amount' => '100.00'],
        ]);

        // Edita apenas a descrição mantendo o cofrinho inativo existente
        $response = $this->actingAs($user)->put(route('transactions.update', $tx), [
            'description' => 'Aporte original corrigido',
            'amount' => '100.00',
            'date' => '2026-05-01',
            'financial_project_id' => $inactiveProject->id,
            'category_allocations' => [
                ['category_id' => $investCat->id, 'amount' => '100.00'],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('Aporte original corrigido', $tx->fresh()->description);
        $this->assertSame($inactiveProject->id, (int) $tx->fresh()->financial_project_id);
    }

    public function test_cannot_delete_cofrinho_with_history_and_shows_deactivate_suggestion(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account] = $this->setupCoupleWithUser();

        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Cofrinho com histórico',
            'is_active' => true,
        ]);

        $investCat = Category::investmentsForCouple((int) $couple->id);

        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Aporte vinculado',
            'amount' => '200.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-05-01',
            'reference_month' => 5,
            'reference_year' => 2026,
            'financial_project_id' => $project->id,
        ]);

        $response = $this->actingAs($user)->delete(route('cofrinhos.destroy', $project));
        $response->assertRedirect(route('cofrinhos.index'));
        $response->assertSessionHas('error', 'Não é possível excluir: há lançamentos ou rendimentos vinculados a este cofrinho. Você pode desativá-lo em vez de excluir.');

        $this->assertDatabaseHas('financial_projects', ['id' => $project->id]);
    }

    public function test_cofrinhos_index_renders_active_and_inactive_sections(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleWithUser();

        $active = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Viagem Ativa',
            'is_active' => true,
        ]);

        $inactive = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Reserva Desativada',
            'is_active' => false,
        ]);

        $btcActive = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Bitcoin Ativo',
            'asset_type' => 'crypto',
            'asset_code' => 'BTC',
            'asset_quantity' => 0.05,
            'asset_avg_price' => 350000.00,
            'is_active' => true,
        ]);

        $btcInactive = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Bitcoin Inativo',
            'asset_type' => 'crypto',
            'asset_code' => 'BTC',
            'asset_quantity' => 0.01,
            'asset_avg_price' => 300000.00,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->get(route('cofrinhos.index'));
        $response->assertOk();
        $response->assertSee('Viagem Ativa');
        $response->assertSee('Reserva Desativada');
        $response->assertSee('Bitcoin Ativo');
        $response->assertSee('Bitcoin Inativo');
        $response->assertSee('Cofrinhos desativados');
        $response->assertSee('cofrinhos-desativados-collapse');
    }
}
