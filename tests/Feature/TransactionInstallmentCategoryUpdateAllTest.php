<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionInstallmentCategoryUpdateAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_apply_category_splits_to_all_installments_in_group(): void
    {
        $couple = \App\Models\Couple::query()->create([
            'name' => 'Casal teste',
            'invite_code' => 'INVITE2',
            'monthly_income' => 0,
            'spending_alert_threshold' => 0,
        ]);

        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::query()->create([
            'couple_id' => $couple->id,
            'name' => 'Cartão',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#0d6efd',
            'credit_card_invoice_due_day' => 10,
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

        $root = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra (Parcela 1/3)',
            'amount' => '10.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => now()->toDateString(),
            'reference_month' => (int) now()->month,
            'reference_year' => (int) now()->year,
            'installment_parent_id' => null,
        ]);
        $p2 = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra (Parcela 2/3)',
            'amount' => '20.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => now()->toDateString(),
            'reference_month' => (int) now()->month,
            'reference_year' => (int) now()->year,
            'installment_parent_id' => $root->id,
        ]);
        $p3 = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra (Parcela 3/3)',
            'amount' => '30.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => now()->toDateString(),
            'reference_month' => (int) now()->month,
            'reference_year' => (int) now()->year,
            'installment_parent_id' => $root->id,
        ]);

        $root->syncCategorySplits([['category_id' => $catA->id, 'amount' => '10.00']]);
        $p2->syncCategorySplits([['category_id' => $catA->id, 'amount' => '20.00']]);
        $p3->syncCategorySplits([['category_id' => $catA->id, 'amount' => '30.00']]);

        // Apply 25% / 75% on the root parcel, but request scope=all.
        $this->actingAs($user)
            ->put(route('transactions.update', $root), [
                'description' => $root->baseDescriptionWithoutInstallmentSuffix(),
                'amount' => '10.00',
                'installment_scope' => 'all',
                'category_allocations' => [
                    ['category_id' => $catA->id, 'amount' => '2.50'],
                    ['category_id' => $catB->id, 'amount' => '7.50'],
                ],
            ])
            ->assertRedirect();

        $root->refresh();
        $p2->refresh();
        $p3->refresh();

        $this->assertSame(['2.50', '7.50'], $root->categorySplits->pluck('amount')->map(fn ($x) => number_format((float) $x, 2, '.', ''))->all());
        $this->assertSame([$catA->id, $catB->id], $root->categorySplits->pluck('category_id')->map(fn ($x) => (int) $x)->all());

        $this->assertSame(['5.00', '15.00'], $p2->categorySplits->pluck('amount')->map(fn ($x) => number_format((float) $x, 2, '.', ''))->all());
        $this->assertSame([$catA->id, $catB->id], $p2->categorySplits->pluck('category_id')->map(fn ($x) => (int) $x)->all());

        $this->assertSame(['7.50', '22.50'], $p3->categorySplits->pluck('amount')->map(fn ($x) => number_format((float) $x, 2, '.', ''))->all());
        $this->assertSame([$catA->id, $catB->id], $p3->categorySplits->pluck('category_id')->map(fn ($x) => (int) $x)->all());
    }
}

