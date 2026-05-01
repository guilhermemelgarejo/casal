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
}
