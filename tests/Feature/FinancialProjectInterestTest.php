<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Couple;
use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
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
