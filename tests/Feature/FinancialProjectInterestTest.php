<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Couple;
use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialProjectInterestTest extends TestCase
{
    use RefreshDatabase;

    private function seedCofrinhoSetup(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Mercado Pago',
            'kind' => Account::KIND_REGULAR,
            'color' => '#333333',
            'balance' => '123.45',
        ]);
        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Reserva',
            'target_amount' => '1000.00',
            'color' => '#0ea5e9',
        ]);

        return compact('couple', 'user', 'account', 'project');
    }

    public function test_store_interest_increases_saved_progress_without_changing_accounts(): void
    {
        ['user' => $user, 'account' => $account, 'project' => $project] = $this->seedCofrinhoSetup();

        $before = (float) $account->fresh()->balance;

        $this->actingAs($user)->post(route('cofrinhos.interest.store', $project), [
            'amount' => '10.50',
            'date' => '2026-04-22',
            'note' => 'Rendimento',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('financial_project_entries', [
            'financial_project_id' => $project->id,
            'type' => 'interest',
            'amount' => 10.5,
            'date' => '2026-04-22 00:00:00',
        ]);

        $this->assertSame(10.50, $project->fresh()->savedProgress());
        $this->assertSame($before, (float) $account->fresh()->balance);
    }

    public function test_movements_default_to_all_periods(): void
    {
        ['user' => $user, 'account' => $account, 'project' => $project] = $this->seedCofrinhoSetup();

        Transaction::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'description' => 'Aporte de março',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-03-10',
            'reference_month' => 3,
            'reference_year' => 2026,
        ]);

        FinancialProjectEntry::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'financial_project_id' => $project->id,
            'type' => 'interest',
            'amount' => '5.00',
            'date' => '2026-04-10',
            'note' => 'Juros de abril',
        ]);

        $this->actingAs($user)
            ->get(route('cofrinhos.movements', $project))
            ->assertOk()
            ->assertSee('Todo o período')
            ->assertSee('Aporte de março')
            ->assertSee('Juros de abril');
    }

    public function test_movements_filter_by_period_when_selected(): void
    {
        ['user' => $user, 'account' => $account, 'project' => $project] = $this->seedCofrinhoSetup();

        Transaction::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'description' => 'Aporte de março',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-03-10',
            'reference_month' => 3,
            'reference_year' => 2026,
        ]);
        Transaction::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'description' => 'Aporte de abril',
            'amount' => '150.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        $this->actingAs($user)
            ->get(route('cofrinhos.movements', ['cofrinho' => $project, 'period' => '2026-04']))
            ->assertOk()
            ->assertSee('Aporte de abril')
            ->assertDontSee('Aporte de março');
    }

    public function test_movements_are_paginated_in_50_records(): void
    {
        ['user' => $user, 'project' => $project] = $this->seedCofrinhoSetup();

        FinancialProjectEntry::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'financial_project_id' => $project->id,
            'type' => 'interest',
            'amount' => '1.00',
            'date' => '2026-04-10',
            'note' => 'Registro fora da primeira página',
        ]);

        for ($i = 1; $i <= 50; $i++) {
            FinancialProjectEntry::create([
                'couple_id' => $user->couple_id,
                'user_id' => $user->id,
                'financial_project_id' => $project->id,
                'type' => 'interest',
                'amount' => '1.00',
                'date' => '2026-04-10',
                'note' => "Registro visível {$i}",
            ]);
        }

        $this->actingAs($user)
            ->get(route('cofrinhos.movements', $project))
            ->assertOk()
            ->assertSee('51 registro(s)')
            ->assertSee('Registro visível 50')
            ->assertDontSee('Registro fora da primeira página');
    }

    public function test_can_delete_interest_and_it_shows_delete_button_on_movements_page(): void
    {
        ['user' => $user, 'project' => $project] = $this->seedCofrinhoSetup();

        $entry = FinancialProjectEntry::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'financial_project_id' => $project->id,
            'type' => 'interest',
            'amount' => '1.59',
            'date' => '2026-07-04',
            'note' => 'Rendimento teste',
        ]);

        $this->assertSame(1.59, $project->fresh()->savedProgress());

        // Verifica que o botão de exclusão aparece na página de movimentações
        $this->actingAs($user)
            ->get(route('cofrinhos.movements', $project))
            ->assertOk()
            ->assertSee('Rendimento teste')
            ->assertSee(route('cofrinhos.interest.destroy', $entry));

        // Exclui os juros
        $this->actingAs($user)
            ->from(route('cofrinhos.movements', $project))
            ->delete(route('cofrinhos.interest.destroy', $entry))
            ->assertRedirect(route('cofrinhos.movements', $project))
            ->assertSessionHas('success', 'Juros removidos.');

        $this->assertDatabaseMissing('financial_project_entries', [
            'id' => $entry->id,
        ]);

        $this->assertSame(0.00, $project->fresh()->savedProgress());
    }

    public function test_cannot_delete_interest_of_another_couple(): void
    {
        ['user' => $user, 'project' => $project] = $this->seedCofrinhoSetup();

        $otherCouple = Couple::factory()->create();
        $otherUser = User::factory()->create(['couple_id' => $otherCouple->id]);
        $otherProject = FinancialProject::create([
            'couple_id' => $otherCouple->id,
            'name' => 'Outro',
            'target_amount' => '100.00',
        ]);
        $entry = FinancialProjectEntry::create([
            'couple_id' => $otherCouple->id,
            'user_id' => $otherUser->id,
            'financial_project_id' => $otherProject->id,
            'type' => 'interest',
            'amount' => '1.00',
            'date' => '2026-04-22',
        ]);

        $this->actingAs($user)
            ->delete(route('cofrinhos.interest.destroy', $entry))
            ->assertStatus(404);
    }

    public function test_cannot_store_interest_in_project_of_another_couple(): void
    {
        ['user' => $user] = $this->seedCofrinhoSetup();

        $otherCouple = Couple::factory()->create();
        $otherProject = FinancialProject::create([
            'couple_id' => $otherCouple->id,
            'name' => 'Outro',
            'target_amount' => '100.00',
        ]);

        $this->actingAs($user)->post(route('cofrinhos.interest.store', $otherProject), [
            'amount' => '9.99',
            'date' => '2026-04-22',
            'note' => 'Tentativa indevida',
        ])->assertStatus(404);

        $this->assertDatabaseMissing('financial_project_entries', [
            'financial_project_id' => $otherProject->id,
            'amount' => 9.99,
        ]);
    }

    public function test_cannot_update_project_of_another_couple(): void
    {
        ['user' => $user] = $this->seedCofrinhoSetup();

        $otherCouple = Couple::factory()->create();
        $otherProject = FinancialProject::create([
            'couple_id' => $otherCouple->id,
            'name' => 'Outro',
            'target_amount' => '100.00',
            'color' => '#111111',
        ]);

        $this->actingAs($user)->put(route('cofrinhos.update', $otherProject), [
            'name' => 'Hack',
            'target_amount' => '999.99',
            'color' => '#222222',
        ])->assertStatus(404);

        $otherProject->refresh();
        $this->assertSame('Outro', $otherProject->name);
        $this->assertSame('100.00', number_format((float) $otherProject->target_amount, 2, '.', ''));
    }

    public function test_cannot_delete_project_with_interest_entries(): void
    {
        ['user' => $user, 'project' => $project] = $this->seedCofrinhoSetup();

        FinancialProjectEntry::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'financial_project_id' => $project->id,
            'type' => 'interest',
            'amount' => '15.00',
            'date' => '2026-08-01',
            'note' => 'Rendimento',
        ]);

        $this->actingAs($user)
            ->delete(route('cofrinhos.destroy', $project))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('financial_projects', ['id' => $project->id]);
    }

    public function test_profitability_indicator_is_calculated_and_displayed_on_fiat_cofrinho(): void
    {
        ['user' => $user, 'account' => $account, 'project' => $project] = $this->seedCofrinhoSetup();

        // Aporte: 1000.00
        Transaction::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'description' => 'Aporte inicial',
            'amount' => '1000.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-01-10',
            'reference_month' => 1,
            'reference_year' => 2026,
        ]);

        // Retirada: 200.00
        Transaction::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'description' => 'Retirada parcial',
            'amount' => '200.00',
            'payment_method' => 'Pix',
            'type' => 'income',
            'date' => '2026-02-10',
            'reference_month' => 2,
            'reference_year' => 2026,
        ]);

        // Juros: 80.00
        FinancialProjectEntry::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'financial_project_id' => $project->id,
            'type' => 'interest',
            'amount' => '80.00',
            'date' => '2026-03-01',
            'note' => 'Rendimento CDB',
        ]);

        $project->refresh();

        $this->assertSame(1000.00, $project->totalDeposits());
        $this->assertSame(200.00, $project->totalWithdrawals());
        $this->assertSame(80.00, $project->totalInterest());
        $this->assertSame(800.00, $project->netDeposited());
        $this->assertSame(880.00, $project->savedProgress());
        $this->assertSame(80.00, $project->profitOrLoss());
        $this->assertSame(8.00, $project->profitOrLossPct());

        $this->actingAs($user)
            ->get(route('cofrinhos.index'))
            ->assertOk()
            ->assertSee('Rentabilidade')
            ->assertSee('+R$ 80,00')
            ->assertSee('(+8,00%)');
    }

    public function test_profitability_zero_when_no_interest(): void
    {
        ['user' => $user, 'account' => $account, 'project' => $project] = $this->seedCofrinhoSetup();

        Transaction::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'description' => 'Aporte único',
            'amount' => '500.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-01-10',
            'reference_month' => 1,
            'reference_year' => 2026,
        ]);

        $project->refresh();

        $this->assertSame(500.00, $project->totalDeposits());
        $this->assertSame(0.00, $project->totalWithdrawals());
        $this->assertSame(0.00, $project->totalInterest());
        $this->assertSame(500.00, $project->netDeposited());
        $this->assertSame(500.00, $project->savedProgress());
        $this->assertSame(0.00, $project->profitOrLoss());
        $this->assertSame(0.00, $project->profitOrLossPct());

        $this->actingAs($user)
            ->get(route('cofrinhos.index'))
            ->assertOk()
            ->assertSee('Rentabilidade')
            ->assertSee('+R$ 0,00')
            ->assertSee('(+0,00%)');
    }

    public function test_profitability_resets_and_tracks_current_position_accurately_when_past_cycle_emptied(): void
    {
        ['user' => $user, 'account' => $account, 'project' => $project] = $this->seedCofrinhoSetup();

        // 1. Saldo inicial importado como Ajuste
        FinancialProjectEntry::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'financial_project_id' => $project->id,
            'type' => 'interest',
            'amount' => '3924.34',
            'date' => '2025-12-31',
            'note' => 'Ajuste',
        ]);

        // 2. Retirada que esvaziou a posição anterior
        Transaction::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'description' => 'Retirada total',
            'amount' => '3924.34',
            'payment_method' => 'Pix',
            'type' => 'income',
            'date' => '2026-06-24',
            'reference_month' => 6,
            'reference_year' => 2026,
        ]);

        // 3. Novo ciclo: Aporte de 10.000,00
        Transaction::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'description' => 'Aporte 10k',
            'amount' => '10000.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-07-10',
            'reference_month' => 7,
            'reference_year' => 2026,
        ]);

        // 4. Juros do novo ciclo: 109,13
        FinancialProjectEntry::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'financial_project_id' => $project->id,
            'type' => 'interest',
            'amount' => '109.13',
            'date' => '2026-08-01',
            'note' => 'Juros mês',
        ]);

        $project->refresh();

        $metrics = $project->fiatProfitMetrics();
        $this->assertSame(10109.13, $metrics['saved']);
        $this->assertSame(10000.00, $metrics['principal']);
        $this->assertSame(109.13, $metrics['profit']);
        $this->assertSame(1.09, $metrics['profit_pct']);

        $this->actingAs($user)
            ->get(route('cofrinhos.index'))
            ->assertOk()
            ->assertSee('+R$ 109,13')
            ->assertSee('(+1,09%)');
    }
}
