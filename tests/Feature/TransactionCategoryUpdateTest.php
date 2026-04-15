<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCategoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_updating_category_splits_of_an_existing_transaction(): void
    {
        $couple = \App\Models\Couple::query()->create([
            'name' => 'Casal teste',
            'invite_code' => 'INVITE1',
            'monthly_income' => 0,
            'spending_alert_threshold' => 0,
        ]);

        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::query()->create([
            'couple_id' => $couple->id,
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#0d6efd',
        ]);

        $catA = Category::query()->create([
            'couple_id' => $couple->id,
            'type' => 'expense',
            'name' => 'Mercado',
            'color' => '#ef4444',
        ]);
        $catB = Category::query()->create([
            'couple_id' => $couple->id,
            'type' => 'expense',
            'name' => 'Transporte',
            'color' => '#22c55e',
        ]);

        $tx = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Teste',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => now()->toDateString(),
            'reference_month' => (int) now()->month,
            'reference_year' => (int) now()->year,
        ]);
        $tx->syncCategorySplits([
            ['category_id' => $catA->id, 'amount' => '100.00'],
        ]);

        $this->actingAs($user)
            ->from(route('dashboard', ['period' => now()->format('Y-m')]))
            ->put(route('transactions.update', $tx), [
                'description' => 'Teste',
                'amount' => '100.00',
                'category_allocations' => [
                    ['category_id' => $catB->id, 'amount' => '100.00'],
                ],
            ])
            ->assertRedirect();

        $tx->refresh();
        $this->assertCount(1, $tx->categorySplits);
        $this->assertSame($catB->id, (int) $tx->categorySplits->first()->category_id);
        $this->assertSame('100.00', number_format((float) $tx->categorySplits->first()->amount, 2, '.', ''));
    }
}

